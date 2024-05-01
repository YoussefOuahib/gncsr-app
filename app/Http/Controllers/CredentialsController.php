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
use App\Http\Resources\CredentialsResource;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Log\Logger;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;


class CredentialsController extends Controller
{

    /**
     * Handle the incoming request.
     */

    public function store(Request $request)
    {
        Credential::updateOrCreate(
            [
                'user_id' => auth()->id(),
            ],
            [
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
            ]
        );
        return response()->json(200);
    }

    public function update(Request $request, $userId)
    {
        Credential::updateOrCreate(
            ['user_id' => $userId],
            [
                'user_id' => $userId,
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
            ]
        );
        return response()->json(200);
    }
    public function index()
    {

        $data = Credential::where('user_id', auth()->user()->id)->first();

        return new CredentialsResource($data);
    }
    public function show($userId)
    {
        $credential = Credential::where('user_id', $userId)->first();
        return response()->json([
            'credential' => $credential
        ], 200);
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
        $missions = $this->getMissionsFromTak($token);

        // //compare contacts from Dynamics with contacts from TAK then create new contacts in Dynamics
        $this->compareContacts($service, $users);
        event(new SendResponseEvent('Retrieving Incidents'));
        $incidents = $this->getAllIncidents($service);
        foreach ($incidents as $incident) {
        event(new SendResponseEvent('Creating mission in tak server'));
        foreach ($missions as $mission) {
            Log::info('mission name: ' . $mission['name']);
            Log::info('case name: ' . $incident['Attributes']['ticketnumber']);

            if($mission['name'] != $incident['Attributes']['ticketnumber']) {
                Log::info('creating mission !');
                $this->createMission($incident['Attributes']['ticketnumber'], $incident['Attributes']['title'], $token);
            }else {
                Log::info('mission already exists');
            }
        }
            event(new SendResponseEvent('Updating missing person informations'));

            if ($incident['Attributes']['cct_missingpersoninformationupdated']) {
                $filePath = $this->updateMissingPerson($service, $incident['Attributes']['incidentid']);
                event(new SendResponseEvent('Uploading file to tak server'));
                $hash = $this->uploadFileToTakServer($token, $filePath);
                $this->associateFileToMission($token, $hash, $incident['Attributes']['ticketnumber']);
                $this->markMissingInfoUpdatedFeatureAsFalse($service, $incident['Attributes']['incidentid']);
            }
        }
        // //get all cases with download feature
        event(new SendResponseEvent('Retrieving incidents with Download Zip Feature'));

        $cases = $this->getIncidentWithDownloadZipFeature($service);
        foreach ($cases as $case) {
            if ($token) {
                //                 //validate folder name
                event(new SendResponseEvent('Generating Folder Name'));
                $folderName = $this->validateFolderName($case['Attributes']['title'], $case['Attributes']['incidentid']);
                //      create a tak mission //
                //      fetch zip file
                $zip = $this->fetchZipFile($case, $missions, $token);
                //         //          fetch kml file
                $kml = $this->fetchKmlFile($case, $missions, $token);
                $accessToken = $this->getAccessToken();
                if ($zip) {
                    $this->createFolderInSharePoint($accessToken, $folderName);
                    $this->uploadFileToSharePoint($zip, $folderName, 'zip', $case, $accessToken);
                }
                if ($kml) {
                    $this->uploadFileToSharePoint($kml, $folderName, 'kml', $case, $accessToken);
                }
                $this->updateIncident($service, $case['Attributes']['incidentid']);

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
    private function getMissionsFromTak($token)
    {
        try {
            $takurl = Credential::first()->tak_url;
            $baseUrl = $takurl . '/Marti/api/missions';
            $response = Http::withoutVerifying()->withHeaders(['Authorization' => 'Bearer ' . $token])->get($baseUrl);
            if ($response->successful()) {
                $missions = json_decode($response->getBody(), true)['data'];
                return $missions;
            } else {
                return $response->json()['error_description'] ?? 'Unknown Error';
            }
        } catch (\Exception $e) {
            return $e->getMessage();
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
        $tenantId = Credential::first()->sharepoint_tenant_id;
        $clientId = Credential::first()->sharepoint_client_id . '@' . $tenantId;
        $clientSecret = Credential::first()->sharepoint_client_secret;
        $parts = parse_url(Credential::first()->sharepoint_url);
        $host = $parts['host'];
        $url = "https://accounts.accesscontrol.windows.net/$tenantId/tokens/OAuth/2";
        $response = Http::asForm()->post($url, [
            'grant_type' => 'client_credentials',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'resource' => '00000003-0000-0ff1-ce00-000000000000/' . $host . '@' . $tenantId,
        ]);
        $responseJson = json_decode($response->getBody(), true);

        return $responseJson['access_token'];
    }
    private function updateMissingPerson($service, $incidentId)
    {
        try {

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
            $storagePath = '/public/incidents';
            $filePath = $storagePath . '/' . $incidentId . '.json';
            $path = 'app' . $storagePath . '/' . $incidentId . '.json';
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            $jsonData = json_encode($newData, JSON_PRETTY_PRINT);
            Storage::put($filePath, $jsonData);

            return $path;
        } catch (\Exception $ex) {
            // Handle exceptions
            Log::error($ex->getMessage());
            Log::error($ex->getTraceAsString());
            return response()->json(['error' => 'An error occurred.'], 500);
        }
    }
    private function uploadFileToTakServer($token, $file)
    {


        $takurl = Credential::first()->tak_url;
        try {
            // Check if the file exists
            // Read the file content
            $file_content = storage_path($file);
            Log::info('hello content: ');
            $contents = file_get_contents($file_content);
            $jsonContent = json_decode($contents, true);
            $bodyContent = json_encode($jsonContent);
            $contentType = "application/json";
            $originalFileName = basename($file);

            // Make the POST request
            $response = Http::withoutVerifying()->withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ])->attach('file', $bodyContent, $originalFileName)->post($takurl . '/Marti/sync/upload', [
                'originalFileName' => $originalFileName,
            ]);
            $data = json_decode($response->getBody(), true);
            $hashValue = $data['Hash'];
            return $hashValue;
        } catch (Exception $e) {
            Log::info($e->getMessage());
        }
    }
    private function associateFileToMission($token, $hash, $missionName)
    {
        try {
            $takurl = Credential::first()->tak_url;
            $response = Http::withoutVerifying()->withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ])->put($takurl . '/Marti/api/missions/' . $missionName . '/contents', [
                'hashes' => [$hash],
            ]);

            return $response;
        } catch (Exception $e) {
            Log::info($e->getMessage());
        }
    }
    private function markMissingInfoUpdatedFeatureAsFalse($service, $incidentId)
    {
        $record = new \AlexaCRM\Xrm\Entity('incident', $incidentId);
        $record['cct_missingpersoninformationupdated'] = false;
        $service->Update($record);
    }
    private function getAllIncidents($service)
    {
        try {
            $fetchXML = <<<FETCHXML
            <fetch mapping="logical"> 
                <entity name="incident">
                    <attribute name="incidentid" />
                    <attribute name="ticketnumber" />
                    <attribute name="title" />
                    <attribute name="cct_incidenttype" />
                    <attribute name="createdon" />
                    <attribute name="cct_missingpersoninformationupdated" />
                </entity>
            </fetch>
            FETCHXML;
            $fetchExpression = new FetchExpression($fetchXML);
            $collection = $service->RetrieveMultiple($fetchExpression);
            $records = json_decode(json_encode($collection), true);
            $entities = $records['Entities'];

            $filteredData = collect($entities)->filter(function ($item) {

                $isValidType = in_array($item['FormattedValues']['cct_incidenttype'], ['Missing Person', 'Lost Person']);

                // Convert date to Carbon instance
                $createdAt = Carbon::parse($item['FormattedValues']['createdon']);

                // $isValidDate = $createdAt->diffInMinutes(now()) <= 10;

                // Return true if both conditions are met
                return $isValidType;
            })->values();

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
            $query->ColumnSet = new \AlexaCRM\Xrm\ColumnSet(['incidentid', 'cct_downloadzipfile', 'createdon', 'title', 'ticketnumber']);
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
    private function fetchZipFile($case, $missions, $token)
    {
        $takurl = Credential::first()->tak_url;
            foreach ($missions as $mission) {
            if ($mission['name'] == strtoupper($case['Attributes']['ticketnumber'])) {
                $contents = $mission['contents'];
                foreach ($contents as $content) {
                    if ($content['data']['mimeType'] === 'application/x-zip-compressed' || $content['data']['mimeType'] == 'application/zip') {
                        Log::info("here we go zip");
                        $response = Http::timeout(60)->withoutVerifying()->withHeaders(['Authorization' => 'Bearer ' . $token])
                            ->get($takurl . '/Marti/api/missions/' . strtolower($case['Attributes']['ticketnumber']) . '/archive');
                        if ($response->successful()) {
                            $fileName = $case['Attributes']['title'] . '_' . Carbon::parse($case['Attributes']['createdon'])->format('Y-m-d') . '.' . 'zip';
                            $path = 'app/public/downloads/' . $fileName . '.zip';
                            if (file_exists($path)) {
                                unlink($path);
                            }
                            Storage::put('public/downloads/' . $fileName, $response->getBody());
                            return $response->getBody();
                        }
                    }
                }
            }
        }
        return null;
    }

    private function createMission($missionName, $description, $token)
    {
        $baseUrl = Credential::first()->tak_url . '/Marti/api/missions/' . $missionName;
        $response = Http::withoutVerifying()->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->put($baseUrl, [
                'description' => $description,
                'name' => $missionName,
            ]);

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

    private function validateFolderName($title, $guid)
    {
        $missionFolderName =   preg_replace('/[^a-zA-Z0-9\s]/', '-', strtoupper($title)) . '_' .  str_replace("-", "", strtoupper($guid));
        return $missionFolderName;
    }
    private function fetchKmlFile($case, $missions, $token)
    {
        Log::info('hello fetch kml');

        $takurl = Credential::first()->tak_url;
        foreach ($missions as $mission) {
            if ($mission['name'] == strtoupper($case['Attributes']['ticketnumber'])) {
                $contents = $mission['contents'];
                foreach ($contents as $content) {
                    if ($content['data']['mimeType'] === 'application/octet-stream') {
                        Log::info('kml file');
                        $response = Http::timeout(60)->withoutVerifying()->withHeaders(['Authorization' => 'Bearer ' . $token])
                            ->get($takurl . '/Marti/api/missions/' . strtolower($case['Attributes']['ticketnumber']) . '/kml');

                        if ($response->successful()) {
                            $fileName = $case['Attributes']['title'] . '_' . Carbon::parse($case['Attributes']['createdon'])->format('Y-m-d') . '.' . 'kml';
                            Storage::put('public/downloads/' . $fileName, $response->getBody());
                            return $response->getBody();
                        }
                    }
                }
            }
        }
        return null;
    }
    private function uploadFileToSharePoint($file, $folderName, $fileType, $case, $accessToken)
    {
        try {
            $siteUrl = Credential::first()->sharepoint_url;
            $fileName = $case['Attributes']['title'] . '_' . Carbon::parse($case['Attributes']['createdon'])->format('Y-m-d') . '.' . $fileType;
            $path = public_path('downloads/' . $fileName);
            Http::withHeaders([
                'Authorization' => "Bearer $accessToken",
            ])->attach('file', $path, $fileName)->post("$siteUrl/_api/web/GetFolderByServerRelativeUrl('/sites/gsarics/incident/$folderName')/files/add(url='$fileName',overwrite=true)");
        } catch (\Exception $ex) {
            // Handle exceptions
            Log::error($ex->getMessage());
            Log::error($ex->getTraceAsString());
            return response()->json(['error' => 'An error occurred.'], 500);
        }
    }
    private function createFolderInSharePoint($token, $folderName)
    {
        try {
            $siteUrl = Credential::first()->sharepoint_url;

            $isFolderExists = Http::withoutVerifying()->withHeaders(['Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json; odata=verbose'])
                ->get($siteUrl . "/_api/web/GetFolderByServerRelativeUrl('/sites/gsarics/incident/" . $folderName . "')");
            if ($isFolderExists->status() == 404) {
                $response = Http::withoutVerifying()->withHeaders(['Authorization' => 'Bearer ' . $token])
                    ->post($siteUrl . "/_api/web/folders", [
                        "ServerRelativeUrl" => "/sites/gsarics/incident/" . $folderName,
                    ]);
            }
        } catch (Exception $e) {
            Log::error($e->getMessage());
            dd($e->getMessage());
        }
    }
    public function updateIncident($service, $incidentId)
    {
        $record = new \AlexaCRM\Xrm\Entity('incident', $incidentId);
        $record['cct_downloadzipfile'] = false;
        $service->Update($record);
    }
}
