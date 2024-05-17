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
    protected $userId;
    protected $takurl;
    protected $token;
    protected $service;
    protected $sharePointUrl;
    protected $accessToken;
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
    public function execute($userId)
    {
        $this->userId = $userId;
        $user = User::find($this->userId);
        if ($user->is_admin || !$user->credentials()->exists()) {
            abort(401, "you don't have connection system");
        }


        $this->takurl = Credential::where('user_id', $this->userId)->first()->tak_url;
        $this->sharePointUrl = Credential::where('user_id', $this->userId)->first()->sharepoint_url;

        // Connect to Dynamics
        $this->service = $this->connect();
        event(new SendResponseEvent('Connecting to Microsoft Dynamics'));
        //get bearer token
        event(new SendResponseEvent('Connecting to Tak Server'));
        $this->token = $this->getBearerToken();

        event(new SendResponseEvent('Retrieving Contacts from Tak'));
        $users = $this->getContactsFromTak();
        $missions = $this->getMissionsFromTak();

        // compare contacts from Dynamics with contacts from TAK then create new contacts in Dynamics
        $this->compareContacts($users);
        event(new SendResponseEvent('Retrieving Incidents'));
        $isMissionExisted = 0;
        $incidents = $this->getAllIncidents($this->service);
        foreach ($incidents as $incident) {
            event(new SendResponseEvent('Creating mission in tak server'));
            foreach ($missions as $mission) {

                if ($mission['name'] == $incident['Attributes']['ticketnumber']) {
                    $isMissionExisted = 1;
                }
            }
            if ($isMissionExisted == 0) {

                $this->createMission($incident['Attributes']['ticketnumber'], $incident['Attributes']['title'], $this->token);
            }
            event(new SendResponseEvent('Updating missing person informations'));

            if ($incident['Attributes']['cct_missingpersoninformationupdated']) {


                $filePath = $this->updateMissingPerson($this->service, $incident['Attributes']['incidentid']);
                event(new SendResponseEvent('Uploading file to tak server'));
                $hash = $this->uploadFileToTakServer($this->token, $filePath);
                $this->associateFileToMission($this->token, $hash, $incident['Attributes']['ticketnumber']);
                $this->markMissingInfoUpdatedFeatureAsFalse($this->service, $incident['Attributes']['incidentid']);
            }
        }

        // //get all cases with download feature
        event(new SendResponseEvent('Retrieving incidents with Download Zip Feature'));

        $cases = $this->getIncidentWithDownloadZipFeature($this->service);
        foreach ($cases as $case) {
            if ($this->token) {
                //                 //validate folder name
                event(new SendResponseEvent('Generating Folder Name'));
                $folderName = $this->validateFolderName($case['Attributes']['title'], $case['Attributes']['incidentid']);
                //      create a tak mission //
                //      fetch zip file
                $zip = $this->fetchZipFile($case, $missions);
                //         //          fetch kml file
                $kml = $this->fetchKmlFile($case, $missions);
                $this->accessToken = $this->getAccessToken();
                if (!$this->accessToken) {
                    event(new SendResponseEvent('Failed to obtain access token'));
                };
                if ($zip || $kml) {
                    $this->createFolderInSharePoint($folderName);
                }
                if ($zip) {
                    $this->uploadFileToSharePoint($zip, $folderName, 'zip', $case);
                }
                if ($kml) {
                    $this->uploadFileToSharePoint($kml, $folderName, 'kml', $case);
                }
                //$this->updateIncident($this->service, $case['Attributes']['incidentid']);

            }
        }
    }
    private function connect()
    {
        try {
            $organizationUri = Credential::where('user_id', $this->userId)->first()->dynamics_url;
            $applicationId = Credential::where('user_id', $this->userId)->first()->dynamics_client_id;
            $applicationSecret = Credential::where('user_id', $this->userId)->first()->dynamics_client_secret;
            $settings = new OnlineSettings();
            $settings->instanceURI = $organizationUri;
            $settings->applicationID = $applicationId;
            $settings->applicationSecret = $applicationSecret;

            return ClientFactory::createOnlineClient(
                $organizationUri,
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
    private function getContactsFromTak()
    {

        $baseUrl = $this->takurl . '/user-management/api/list-users';
        $response = Http::withoutVerifying()->withHeaders(['Authorization' => 'Bearer ' . $this->token])->get($baseUrl);
        if ($response->successful()) {
            $users = json_decode($response->getBody(), true);
            return $users;
        } else {
            // Handle unsuccessful response
            return $response->json()['error_description'] ?? 'Unknown Error';
        }
    }
    private function getMissionsFromTak()
    {
        try {
            $baseUrl = $this->takurl . '/Marti/api/missions';
            $response = Http::withoutVerifying()->withHeaders(['Authorization' => 'Bearer ' . $this->token])->get($baseUrl);
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

        try {
            $baseUrl = $this->takurl . '/oauth/token';

            $response = Http::withoutVerifying()->timeout(60)->get($baseUrl, [
                'grant_type' => 'password',
                'username' => Credential::where('user_id', $this->userId)->first()->tak_login,
                'password' => Credential::where('user_id', $this->userId)->first()->tak_password,
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


    private function compareContacts($users)
    {
        try {
            foreach ($users as $user) {
                $contactEmail = $this->getContactByEmail($user['username'] . '@tak.com');
                if ($contactEmail != $user['username'] . '@tak.com' || $contactEmail == 'No email') {
                    $this->createNewContact($user['username']);
                    $contactid = $this->getContactId($user['username'] . '@tak.com');


                    $this->createVolunteer($user, $contactid);
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
    private function getContactByEmail($takEmail)
    {
        try {


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
            $collection = $this->service->RetrieveMultiple($fetchExpression);
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
    private function createNewContact($username)
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
            $this->service->Create($contact);

            return $contact;
        } catch (\Exception $ex) {
            // Handle exceptions
            Log::error($ex->getMessage());
            Log::error($ex->getTraceAsString());
            return response()->json(['error' => 'An error occurred.'], 500);
        }
    }
    private function getContactId($email)
    {
        $fetchXML = <<<FETCHXML
            <fetch mapping="logical"> 
                <entity name="contact">
                    <attribute name="contactid" />
                    <filter type="and">
                        <condition attribute="emailaddress1" operator="eq" value="{$email}" />
                </filter>
                </entity>
            </fetch>
            FETCHXML;
        $fetchExpression = new \AlexaCRM\Xrm\Query\FetchExpression($fetchXML);
        $collection = $this->service->RetrieveMultiple($fetchExpression);
        $records = json_decode(json_encode($collection), true);
        $entities = $records['Entities'];
        $contactid = $entities[0]["Attributes"]["contactid"];


        return $contactid;
    }
    private function getAccessToken()
    {
        $tenantId = Credential::where('user_id', $this->userId)->first()->sharepoint_tenant_id;
        $clientId = Credential::where('user_id', $this->userId)->first()->sharepoint_client_id . '@' . $tenantId;
        $clientSecret = Credential::where('user_id', $this->userId)->first()->sharepoint_client_secret;
        $parts = parse_url(Credential::where('user_id', $this->userId)->first()->sharepoint_url);
        $host = $parts['host'];
        $url = "https://accounts.accesscontrol.windows.net/$tenantId/tokens/OAuth/2";
        $response = Http::asForm()->post($url, [
            'grant_type' => 'client_credentials',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'resource' => '00000003-0000-0ff1-ce00-000000000000/' . $host . '@' . $tenantId,
        ]);
        if (!$response->successful()) {
            // Return the status code from the response
            return response()->json([
                'error' => 'Failed to obtain access token',
            ], $response->status());
        }
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
        try {
            // Check if the file exists
            // Read the file content
            $file_content = storage_path($file);
            $contents = file_get_contents($file_content);
            $jsonContent = json_decode($contents, true);
            $bodyContent = json_encode($jsonContent);
            $originalFileName = basename($file);

            // Make the POST request
            $response = Http::withoutVerifying()->withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ])->attach('file', $bodyContent, $originalFileName)->post($this->takurl . '/Marti/sync/upload', [
                'originalFileName' => $originalFileName,
            ]);
            $data = json_decode($response->getBody(), true);
            $hashValue = $data['Hash'];
            return $hashValue;
        } catch (Exception $e) {
        }
    }
    private function associateFileToMission($token, $hash, $missionName)
    {
        try {
            $response = Http::withoutVerifying()->withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ])->put($this->takurl . '/Marti/api/missions/' . $missionName . '/contents', [
                'hashes' => [$hash],
            ]);

            return $response;
        } catch (Exception $e) {
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
                    <attribute name="modifiedon" />
                    <attribute name="cct_missingpersoninformationupdated" />
                </entity>
            </fetch>
            FETCHXML;
            $fetchExpression = new FetchExpression($fetchXML);
            $collection = $service->RetrieveMultiple($fetchExpression);
            $records = json_decode(json_encode($collection), true);
            $entities = $records['Entities'];
            $filteredData = collect($entities)->filter(function ($item) {
                // Check if the incident type is valid
                $isValidType = in_array($item['FormattedValues']['cct_incidenttype'], ['Missing Person', 'Lost Person']);

                if (!$isValidType) {
                    return false; // Skip this item if the incident type is not valid
                }

                // Convert date to Carbon instance and check if it's within the last 10 minutes
                $createdAt = Carbon::parse($item['FormattedValues']['modifiedon']);
                $isValidDate = $createdAt->diffInMinutes(now()) <= 10;

                return $isValidDate; // Return true if the date is valid
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

            $fetchXML = <<<FETCHXML
            <fetch mapping="logical"> 
                <entity name="incident">
                    <attribute name="incidentid" />
                    <attribute name="ticketnumber" />
                    <attribute name="cct_downloadzipfile" />
                    <attribute name="title" />
                    <attribute name="modifiedon" />
                    <filter type="and">
                        <condition attribute="cct_downloadzipfile" operator="eq" value="true" />
                    </filter>
                </entity>
            </fetch>
            FETCHXML;
            $fetchExpression = new FetchExpression($fetchXML);
            $collection = $service->RetrieveMultiple($fetchExpression);
            $records = json_decode(json_encode($collection), true);
            $entities = $records['Entities'];
            $filteredData = collect($entities)->filter(function ($item) {
                $createdAt = Carbon::parse($item['FormattedValues']['modifiedon']);
                $isValidDate = $createdAt->diffInMinutes(now()) <= 10;


                return $isValidDate;
            })->values();
            return $filteredData;
        } catch (\Exception $ex) {
            // Handle exceptions
            Log::error($ex->getMessage());
            Log::error($ex->getTraceAsString());
            return response()->json(['error' => 'An error occurred.'], 500);
        }
    }
    private function fetchZipFile($case, $missions)
    {
        foreach ($missions as $mission) {
            if ($mission['name'] == strtoupper($case['Attributes']['ticketnumber'])) {
                $contents = $mission['contents'];
                foreach ($contents as $content) {
                    if ($content['data']['mimeType'] === 'application/x-zip-compressed' || $content['data']['mimeType'] == 'application/zip') {
                        $response = Http::timeout(60)->withoutVerifying()->withHeaders(['Authorization' => 'Bearer ' . $this->token])
                            ->get($this->takurl . '/Marti/api/missions/' . strtolower($case['Attributes']['ticketnumber']) . '/archive');
                        if ($response->successful()) {

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
        $baseUrl = $this->takurl . '/Marti/api/missions/' . $missionName;
        $response = Http::withoutVerifying()->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->put($baseUrl, [
                'description' => $description,
                'name' => $missionName,
            ]);

        return $response;
    }
    private function createVolunteer($user, $contactid)
    {
        try {
            $volunteer = new Entity('cct_volunteer');
            $volunteer['emailaddress'] = $user['username'] . '@tak.com';
            $volunteer['cct_name'] = $user['username'];
            $volunteer['cct_volunteerstaff'] = "803200000";
            $volunteer['cct_volunteercontactinfo'] = new \AlexaCRM\Xrm\EntityReference('contact', $contactid);
            $this->service->Create($volunteer);
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
    private function fetchKmlFile($case, $missions)
    {


        foreach ($missions as $mission) {
            if ($mission['name'] == strtoupper($case['Attributes']['ticketnumber'])) {
                $contents = $mission['contents'];
                foreach ($contents as $content) {
                    if ($content['data']['mimeType'] === 'application/octet-stream') {
                        $response = Http::timeout(60)->withoutVerifying()->withHeaders(['Authorization' => 'Bearer ' . $this->token])
                            ->get($this->takurl . '/Marti/api/missions/' . strtolower($case['Attributes']['ticketnumber']) . '/kml');

                        return $response->getBody();
                    }
                }
            }
        }
        return null;
    }
    private function uploadFileToSharePoint($file, $folderName, $fileType, $case)
    {
        try {


            $fileName = $case['Attributes']['title'] . '_' . Carbon::parse($case['Attributes']['modifiedon'])->format('Y-m-d') . '.' . $fileType;
            Http::withHeaders([
                'Authorization' => "Bearer $this->accessToken",
            ])->timeout(100)->attach('file', $file, $fileName)->post("$this->sharePointUrl/_api/web/GetFolderByServerRelativeUrl('/sites/gsarics/incident/$folderName')/files/add(url='$fileName',overwrite=true)");
        } catch (\Exception $ex) {
            // Handle exceptions
            Log::error($ex->getMessage());
            Log::error($ex->getTraceAsString());
            return response()->json(['error' => 'An error occurred.'], 500);
        }
    }
    private function createFolderInSharePoint($folderName)
    {

        try {
            $isFolderExists = Http::withoutVerifying()->withHeaders(['Authorization' => 'Bearer ' . $this->accessToken, 'Accept' => 'application/json; odata=verbose'])
                ->get($this->sharePointUrl . "/_api/web/GetFolderByServerRelativeUrl('/sites/gsarics/incident/" . $folderName . "')");
            if ($isFolderExists->status() == 404) {
                $response = Http::withoutVerifying()->withHeaders(['Authorization' => 'Bearer ' . $this->accessToken])
                    ->post($this->sharePointUrl . "/_api/web/folders", [
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
