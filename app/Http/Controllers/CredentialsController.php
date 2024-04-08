<?php

namespace App\Http\Controllers;

use AlexaCRM\WebAPI\ClientFactory;
use AlexaCRM\WebAPI\OData\OnlineSettings;
use AlexaCRM\Xrm\ColumnSet;
use AlexaCRM\Xrm\Entity;
use AlexaCRM\Xrm\Query\OrderType;
use AlexaCRM\Xrm\Query\QueryByAttribute;
use App\Models\Credential;
use App\Services\DynamicsConnectionService;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use AlexaCRM\Xrm\Query\FilterExpression;
use AlexaCRM\Xrm\Query\ConditionExpression;
use AlexaCRM\Xrm\Query\FetchExpression;
use App\Events\SendResponseEvent;
use Carbon\Carbon;
use Exception;
use Illuminate\Log\Logger;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;


class CredentialsController extends Controller
{

    /**
     * Handle the incoming request.
     */

    public function store(Request $request)
    {

        Credential::create([
            'user_id' => auth()->id(),
            'tak_url' => $request->tak_url,
            'tak_login' => $request->tak_login,
            'tak_password' => $request->tak_password,
            'sharepoint_url' => $request->sharepoint_url,
            'sharepoint_client_id' => $request->sharepoint_client_id,
            'sharepoint_client_secret' => $request->sharepoint_client_secret,
            'sharepoint_tenant_id' => $request->sharepoint_tenant_id,
            'dynamics_url' => $request->dynamics_url,
            'dynamics_client_id' => $request->dynamics_client_id,
            'dynamics_client_secret' => $request->dynamics_client_secret,
        ]);
        return response()->json(200);
    }
    public function index() {

        $credentials = Credential::where('user_id', auth()->user()->id)->get();
        return response()->json([
            'credentials', $credentials
        ], 202);
    }
    public function execute()
    {
        Log::info('hello execute');
        // Replace these values with your Dynamics 365 details
        $organizationUri = Credential::first()->dynamics_url;
        $applicationId = Credential::first()->dynamics_client_id;
        $applicationSecret = Credential::first()->dynamics_client_secret;

        // Connect to Dynamics
        $service = $this->connect($organizationUri, $applicationId, $applicationSecret);
        event(new SendResponseEvent('Connecting to Microsoft Dynamics'));
        Log::info('connection has been set');
        //get bearer token
        event(new SendResponseEvent('Connecting to Tak Server'));
        $token = $this->getBearerToken();

        event(new SendResponseEvent('Retrieving Contacts from Tak'));
        $users = $this->getContactsFromTak($token);

        //compare contacts from Dynamics with contacts from TAK then create new contacts in Dynamics
        $this->compareContacts($service, $users);

        event(new SendResponseEvent('Retrieving Incidents'));
        $incidents = $this->getAllIncidents($service);
        foreach ($incidents as $incident) {
            event(new SendResponseEvent('Updating missing person informations'));
            $filePath = $this->updateMissingPerson($service, $incident['Attributes']['incidentid']);
            event(new SendResponseEvent('Uploading file to tak server'));
            $this->uploadFileToTakServer($token, $filePath, $incident['Attributes']['ticketnumber']);
        }
        //get all cases with download feature
        event(new SendResponseEvent('Retrieving incidents with Download Zip Feature'));

        $cases = $this->getIncidentWithDownloadZipFeature($service);

        foreach ($cases as $case) {
            if ($token) {
                $missionFolderName = $case['Attributes']['title'] . '' .  strtoupper(str_replace('-', '', $case['Attributes']['incidentid']));
                //         //validate folder name
                event(new SendResponseEvent('Generating Folder Name'));

                $folderName = $this->validateFolderName($missionFolderName);
                //         //create a tak mission //
                event(new SendResponseEvent('Creating mission in tak server'));
                $this->createMission($case['Attributes']['ticketnumber'], $case['Attributes']['title'] ,$token);

                //         // fetch zip file
                $zip = $this->fetchZipFile(Str::lower($case['Attributes']['ticketnumber']),  $token);

                //         // fetch kml file
                // $kml = $this->fetchKmlFile(Str::lower($case['Attributes']['ticketnumber']),  $token);
                $accessToken = $this->getAccessToken();
                if ($zip) {
                    Log::info('we have a zip file');
                    $this->uploadFileToSharePoint($zip, $folderName, 'zip', $accessToken);
                }
                // if ($kml) {
                //     $this->uploadFileToSharePoint($kml, $folderName, 'kml', $accessToken);
                // }
            }
        }
    }
    private function connect($url, $applicationId, $applicationSecret)
    {
        try {
            $settings = new OnlineSettings();
            $settings->instanceURI = $url;
            $settings->applicationID = $applicationId;
            $settings->applicationSecret = $applicationSecret;

            return ClientFactory::createOnlineClient(
                $url,
                $applicationId,
                $applicationSecret,
            );
        } catch (\Exception $ex) {
            // Handle exceptions
            Log::error($ex->getMessage());
            Log::error($ex->getTraceAsString());
            return null;
        }
    }
    private function getContactsFromTak($token)
    {
        Log::info('hello from tak contacts');
        $baseUrl = Credential::first()->tak_url . '/user-management/api/list-users';
        $response = Http::withoutVerifying()->withHeaders(['Authorization' => 'Bearer ' . $token])->get($baseUrl);
        if ($response->successful()) {
            $users = json_decode($response->getBody(), true);
            return $users;
        } else {
            // Handle unsuccessful response
            return $response->json()['error_description'] ?? 'Unknown Error';
        }
    }
    private function getBearerToken()
    {
        Log::info('hello bearer token');
        try {
            $baseUrl = Credential::first()->tak_url . '/oauth/token';

            $response = Http::withoutVerifying()->get($baseUrl, [
                'grant_type' => 'password',
                'username' => Credential::first()->tak_login,
                'password' => Credential::first()->tak_password,
            ]);

            // Check if the response was successful
            if ($response->successful()) {
                $responseJson = json_decode($response->getBody(), true);
                return $responseJson['access_token'];
            } else {
                // Handle unsuccessful response
                $statusCode = $response->status();
                $errorMessage = $response->json()['error_description'] ?? 'Unknown Error';
                throw new Exception("Request failed with status $statusCode: $errorMessage");
            }
        } catch (Exception $e) {
            // Handle any exceptions
            dd($e->getMessage());
        }
    }


    private function compareContacts($service, $users)
    {
        Log::info('hello compare contacts');
        try {
            foreach ($users as $user) {
                $contactEmail = null;
                $contactEmail = $this->getContactByEmail($service, $user['username'] . '@tak.com');
                if ($contactEmail != $user['username'] . '@tak.com' && $contactEmail == 'No email') {
                    $this->createNewContact($service, $user['username']);
                    $contactid = $this->getContactId($service, $user['username'] . '@tak.com');
                    $this->createVolunteer($service, $user, $contactid);
                }
            }
            return response()->json(['message' => 'Contacts have been created successfully.'], 200);
        } catch (\Exception $ex) {
            // Handle exceptions
            Log::error($ex->getMessage());
            Log::error($ex->getTraceAsString());
            return response()->json(['error' => 'An error occurred.'], 500);
        }
    }
    private function getContactByEmail($service, $takEmail)
    {
        try {
            Log::info('hello from get contact by email');
            Log::info($takEmail);
            $email = null;
            $fetchXML = <<<FETCHXML
            <fetch mapping="logical"> 
                <entity name="contact">
                    <attribute name="contactid" />
                    <attribute name="emailaddress1" />
                    <attribute name="createdon" />
                    <filter type="and">
                        <condition attribute="emailaddress1" operator="eq" value="{$takEmail}" />
                    </filter>
                </entity>
            </fetch>
            FETCHXML;
            $fetchExpression = new FetchExpression($fetchXML);
            $collection = $service->RetrieveMultiple($fetchExpression);
            $records = json_decode(json_encode($collection), true);
            $entities = $records['Entities'];

            if (isset($entities[0]["Attributes"]) && isset($entities[0]["Attributes"]["emailaddress1"])) {
                $email = $entities[0]["Attributes"]["emailaddress1"];
            } else {
                $email = "No email";
            }
            return $email;
        } catch (\Exception $ex) {
            // Handle exceptions
            Log::error($ex->getMessage());
            Log::error($ex->getTraceAsString());
            return response()->json(['error' => 'An error occurred.'], 500);
        }
    }
    private function createNewContact($service, $username)
    {
        try {
            //retrieve contacts from Dynamics

            $contact = new Entity('contact');
            $contact['emailaddress1'] = $username . '@tak.com';

            if (preg_match('/^(\w+)_+(\w+)$/', $username, $matches)) {
                $contact['firstname'] = $matches[1];
                $contact['lastname'] = $matches[2];
            } else {
                $contact['firstname'] = substr($username, 0, 1);
                $contact['lastname'] = substr($username, 1);
            }
            $service->Create($contact);
            return $contact;
        } catch (\Exception $ex) {
            // Handle exceptions
            Log::error($ex->getMessage());
            Log::error($ex->getTraceAsString());
            return response()->json(['error' => 'An error occurred.'], 500);
        }
    }
    private function getContactId($service, $email)
    {
        $fetchXML = <<<FETCHXML
            <fetch mapping="logical"> 
                <entity name="contact">
                    <attribute name="contactid" />
                    <attribute name="emailaddress1" />
                    <filter type="and">
                        <condition attribute="emailaddress1" operator="eq" value="{$email}" />
                </filter>
                </entity>
            </fetch>
            FETCHXML;
        $fetchExpression = new \AlexaCRM\Xrm\Query\FetchExpression($fetchXML);
        $collection = $service->RetrieveMultiple($fetchExpression);
        $records = json_decode(json_encode($collection), true);
        $entities = $records['Entities'];
        $contactid = $entities[0]["Attributes"]["contactid"];
        return $contactid;
    }
    private function getAccessToken()
    {
        $clientId = Credential::first()->sharepoint_client_id;
        $clientSecret = Credential::first()->sharepoint_client_secret;
        $tenantId = Credential::first()->sharepoint_tenant_id;
        $url = "https://accounts.accesscontrol.windows.net/$tenantId/oauth2/token";
        $response = Http::asForm()->post($url, [
            'grant_type' => 'client_credentials',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ]);
        $responseJson = json_decode($response->getBody(), true);
        Log::info('hello access token');
        Log::info($response->body());
        return $responseJson['access_token'];
    }
        private function updateMissingPerson($service, $incidentId)
    {
        try {
            $storagePath = '/public/incidents';
            $filePath = $storagePath . '/' . $incidentId . '.json';

            if (file_exists($filePath)) {
                unlink($filePath);
            }
            $query = new QueryByAttribute('incident');
            $query->AddAttributeValue('incidentid', $incidentId);

            $retrievedMissingPerson = $service->Retrieve('incident', $incidentId, new \AlexaCRM\Xrm\ColumnSet([
                'cct_height', 'cct_hair', 'cct_eyes', 'cct_fitness', 'cct_sex', 'cct_build', 'cct_doesnotspeakenglish', 'cct_experience',
                'cct_distinguishingmarks', 'cct_cooperation', 'cct_answersto', 'cct_age', 'cct_weight', 'cct_missingperson',
                'cct_medications', 'cct_medicathistory', 'cct_allergies', 'cct_equipment', 'cct_disabilities', 'cct_clothing', 'cct_foorwear',
                'cct_modeoftravel', 'cct_othernotes', 'cct_circumstancesofincident', 'cct_numberinparty'
            ]));
            $data = json_decode(json_encode($retrievedMissingPerson), true);
            $entities = $data;

            $newData = [
                [
                    'name' => 'Height',
                    'value' => $entities['Attributes']['cct_height'] ?? '',
                    'gui_element' => 'textbox',
                    'is_read_only' => false
                ],
                [
                    'name' => "Name",
                    'value' => is_array($entities['Attributes']['cct_missingperson']) ? $entities['Attributes']['cct_missingperson']['Name'] : $entities['Attributes']['cct_answersto'],
                    'gui_element' => 'textbox',
                    'is_read_only' => false
                ],
                [
                    'name' => "Answer To",
                    'value' => isset($entities['Attributes']['cct_answersto']) ? (is_array($entities['Attributes']['cct_answersto']) ? $entities['Attributes']['cct_answersto']['Name'] : $entities['Attributes']['cct_answersto']) : '',
                    'gui_element' => 'textbox',
                    'is_read_only' => false
                ],
                [
                    'name' => "Build",
                    'value' => $entities['Attributes']['cct_buid'] ?? "",
                    'gui_element' => 'textbox',
                    'is_read_only' => false
                ],
                [
                    'name' => 'Hair',
                    'value' => $entities['Attributes']['cct_hair'] ?? '',
                    'gui_element' => 'textbox',
                    'is_read_only' => false
                ],
                [
                    'name' => 'Distinguishing Marks',
                    'value' => $entities['Attributes']['cct_distinguishingmarks'] ?? '',
                    'gui_element' => 'textbox',
                    'is_read_only' => false
                ],
                [
                    'name' => 'Footware',
                    'value' => $entities['Attributes']['cct_footwear'] ?? '',
                    'gui_element' => 'textbox',
                    'is_read_only' => false
                ],
                [
                    'name' => 'Notes',
                    'value' => $entities['Attributes']['cct_othernotes'] ?? '',
                    'gui_element' => 'textbox',
                    'is_read_only' => false
                ],
                [
                    'name' => 'Circumstances of incident',
                    'value' => $entities['Attributes']['cct_circumstancesofincident'] ?? '',
                    'gui_element' => 'textbox',
                    'is_read_only' => false
                ],
                [
                    'name' => 'Number in party',
                    'value' => $entities['Attributes']['cct_numberinparty'] ?? '',
                    'gui_element' => 'textbox',
                    'is_read_only' => false
                ],
                [
                    'name' => 'Mode of travel',
                    'value' => $entities['Attributes']['cct_modeoftravel'] ?? '',
                    'gui_element' => 'textbox',
                    'is_read_only' => false
                ],
                [
                    'name' => 'Medications',
                    'value' => $entities['Attributes']['cct_medications'] ?? '',
                    'gui_element' => 'textbox',
                    'is_read_only' => false
                ],
                [
                    'name' => 'Cooperation',
                    'value' => $entities['Attributes']['cct_cooperation'] ?? '',
                    'gui_element' => 'textbox',
                    'is_read_only' => false
                ],
                [
                    'name' => 'Medical History',
                    'value' => $entities['Attributes']['cct_medicalhistory'] ?? '',
                    'gui_element' => 'textbox',
                    'is_read_only' => false
                ],
                [
                    'name' => 'Clothing',
                    'value' => $entities['Attributes']['cct_clothing'] ?? '',
                    'gui_element' => 'textbox',
                    'is_read_only' => false
                ],
                [
                    'name' => 'Fitness',
                    'value' => $entities['Attributes']['cct_fitness'] ?? '',
                    'gui_element' => 'textbox',
                    'is_read_only' => false
                ],

                [
                    'name' => 'Experience',
                    'value' => $entities['Attributes']['cct_experience'] ?? '',
                    'gui_element' => 'textbox',
                    'is_read_only' => false
                ],
                [
                    'name' => 'Disabilities',
                    'value' => $entities['Attributes']['cct_disabilities'] ?? '',
                    'gui_element' => 'textbox',
                    'is_read_only' => false
                ],
                [
                    'name' => 'Allergies',
                    'value' => $entities['Attributes']['cct_allergies'] ?? '',
                    'gui_element' => 'textbox',
                    'is_read_only' => false
                ],
                [
                    'name' => 'Equipment',
                    'value' => $entities['Attributes']['cct_equipment'] ?? '',
                    'gui_element' => 'textbox',
                    'is_read_only' => false
                ],
                [
                    'name' => 'Does not checkbox English',
                    'value' => $entities['Attributes']['cct_doesnotspeakenglish'] ?? '',
                    'gui_element' => 'checkbox',
                    'is_read_only' => false
                ],
                [
                    'name' => 'Age',
                    'value' => $entities['Attributes']['cct_age'] ?? '',
                    'gui_element' => 'textbox',
                    'is_read_only' => false
                ],
                [
                    'name' => 'Weight',
                    'value' => $entities['Attributes']['cct_weight'] ?? '',
                    'gui_element' => 'textbox',
                    'is_read_only' => false
                ],
                [
                    'name' => 'Sex',
                    'value' => $entities['Attributes']['cct_sex'] ?? '',
                    'gui_element' => 'textbox',
                    'is_read_only' => false
                ],
                [
                    'name' => 'Eyes',
                    'value' => $entities['Attributes']['cct_eyes'] ?? '',
                    'gui_element' => 'textbox',
                    'is_read_only' => false
                ],
                [
                    'name' => 'ANSWERS TO',
                    'gui_element' => 'textbox',
                    'is_read_only' => false
                ],
            ];
            $jsonData = json_encode($newData, JSON_PRETTY_PRINT);
            Storage::put($filePath, $jsonData);

            return $filePath;
        } catch (\Exception $ex) {
            // Handle exceptions
            Log::error($ex->getMessage());
            Log::error($ex->getTraceAsString());
            return response()->json(['error' => 'An error occurred.'], 500);
        }
    }
    private function uploadFileToTakServer($token, $file, $missionName)
    {
        $takurl = Credential::first()->tak_url;
        $response = Http::withoutVerifying()->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->post($takurl . '/Marti/api/missions/' . $missionName .'/contents', [
                'file' => $file,
            ]);
        return $response->body();
    }
    private function getAllIncidents($service)
    {
        try {
            $fetchXML = <<<FETCHXML
            <fetch mapping="logical"> 
                <entity name="incident">
                    <attribute name="incidentid" />
                    <attribute name="ticketnumber" />
                    <attribute name="cct_incidenttype" />
                    <attribute name="createdon" />
                </entity>
            </fetch>
            FETCHXML;
            $fetchExpression = new FetchExpression($fetchXML);
            $collection = $service->RetrieveMultiple($fetchExpression);
            $records = json_decode(json_encode($collection), true);
            $entities = $records['Entities'];
      
            $filteredData = collect($entities)->filter(function ($item) {
                Log::info('hello item');
                Log::info($item);
                $isValidType = in_array($item['FormattedValues']['cct_incidenttype'], ['Missing Person', 'Lost Person']);
            
                // Convert date to Carbon instance
                $createdAt = Carbon::parse($item['FormattedValues']['createdon']);
            
                // $isValidDate = $createdAt->diffInMinutes(now()) <= 10;
            
                // Return true if both conditions are met
                return $isValidType;
            })->values();
            Log::info('hello filtered data');
            Log::info($filteredData);
            return $filteredData;
        } catch (\Exception $ex) {
            // Handle exceptions
            Log::error($ex->getMessage());
            Log::error($ex->getTraceAsString());
            return response()->json(['error' => 'An error occurred.'], 500);
        }
    }
    private function getIncidentWithDownloadZipFeature($service)
    {
        try {
            $query = new QueryByAttribute('incident');
            $query->AddAttributeValue('cct_downloadzipfile', true);
            $query->ColumnSet = new \AlexaCRM\Xrm\ColumnSet(['incidentid', 'cct_downloadzipfile', 'title', 'ticketnumber']);
            $collection = $service->RetrieveMultiple($query);

            $data = json_decode(json_encode($collection), true);
            $entities = $data['Entities'];
            return $entities;
        } catch (\Exception $ex) {
            // Handle exceptions
            Log::error($ex->getMessage());
            Log::error($ex->getTraceAsString());
            return response()->json(['error' => 'An error occurred.'], 500);
        }
    }
    private function fetchZipFile($case, $token)
    {
        $takurl = Credential::first()->tak_url;

        $response = Http::withoutVerifying()->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get($takurl . '/Marti/api/missions/' . $case . '/archive');

        Log::info('hello fetch zip file');
        // Log::info($response);
        return $response->body();
    }

    private function createMission($missionName, $description ,$token)
    {
        $baseUrl = Credential::first()->tak_url . '/Marti/api/missions/' . $missionName;
        $response = Http::withoutVerifying()->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->put($baseUrl, [
                'description' => $description,
                'name' => $missionName,
            ]);
            Log::info('mission created');
            Log::info($response);
        return $response;
    }
    private function createVolunteer($service, $user, $contactid)
    {
        try {
            $volunteer = new Entity('cct_volunteer');
            $volunteer['emailaddress'] = $user['username'] . '@tak.com';
            $volunteer['cct_name'] = $user['username'];
            $volunteer['cct_volunteercontactinfo'] = $contactid;
            $service->Create($volunteer);
        } catch (\Exception $ex) {
            // Handle exceptions
            Log::error($ex->getMessage());
            Log::error($ex->getTraceAsString());
            return response()->json(['error' => 'An error occurred.'], 500);
        }
    }

    private function validateFolderName($folderMissionName)
    {
        $folderName = preg_replace('/[^A-Za-z0-9_]/', '_', $folderMissionName);

        return $folderName;
    }
    private function fetchKmlFile($missionName, $token)
    {
        $takurl = Credential::first()->tak_url;
        $response = Http::withoutVerifying()
            ->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get($takurl . '/Marti/api/missions/' . $missionName . '/kml');
        return $response->body();
    }
    private function uploadFileToSharePoint($file, $folderName, $fileType, $accessToken)
    {
        Log::info('hello upload');
        $siteUrl = Credential::first()->sharepoint_url;
        $fileName = $folderName . '.' . $fileType;
        $fileUtf8 = mb_convert_encoding($file, 'UTF-8');;
        
        try {
            // $response = Http::withHeaders([
            //     'Authorization' => "Bearer $accessToken",
            //     'Accept' => 'application/json;odata=verbose',
            //     'Content-Type' => 'application/json',
            // ])->post("$siteUrl/_api/web/GetFolderByServerRelativeUrl('/sites/GSARICS/Shared Documents')/files/add(url='$fileName',overwrite=true)", [
            //     'body' => $fileUtf8,
            // ]);
            $response = Http::withHeaders([
                'Authorization' => "Bearer $accessToken",
                'Accept' => 'application/json;odata=verbose',
                'Content-Type' => 'application/json',
            ])
                ->get("$siteUrl/_api/web/folders/GetFolderByServerUrl('Shared Documents')");
            Log::info('Response Status Code: ' . $response->status());
            Log::info('Response Headers: ' . json_encode($response->headers()));
            Log::info('Response Body: ' . $response->body());
        } catch (\Exception $ex) {
            // Handle exceptions
            Log::error($ex->getMessage());
            Log::error($ex->getTraceAsString());
            return response()->json(['error' => 'An error occurred.'], 500);
        }
    }
}
