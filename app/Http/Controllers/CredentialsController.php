<?php

namespace App\Http\Controllers;

use AlexaCRM\WebAPI\ClientFactory;
use AlexaCRM\WebAPI\OData\OnlineSettings;
use AlexaCRM\Xrm\ColumnSet;
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
use Carbon\Carbon;
use Exception;
use Illuminate\Log\Logger;
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
    public function execute()
    {
        // Replace these values with your Dynamics 365 details
        $organizationUri = Credential::first()->dynamics_url;
        $applicationId = Credential::first()->dynamics_client_id;
        $applicationSecret = Credential::first()->dynamics_client_secret;

        // Connect to Dynamics
        $service = $this->connect($organizationUri, $applicationId, $applicationSecret);
        Log::info('connection has been set');
        //get bearer token
        $token = $this->getBearerToken();
        $users = $this->getContactsFromTak($token);
        //compare contacts from Dynamics with contacts from TAK then create new contacts in Dynamics
        $contacts = $this->handleContacts($service, $users);

        //get all cases with download feature
        $accessToken = $this->getAccessToken();
        $cases = $this->getCases($service);
        
        foreach ($cases as $case) {

            if ($token) {
                $missionFolderName = $case['Attributes']['title'] . '' .  strtoupper(str_replace('-', '', $case['Attributes']['incidentid']));
                //validate folder name
                $folderName = $this->validateFolderName($missionFolderName);
                //create a tak mission //
                $this->createMission($case['Attributes']['ticketnumber'], $token);
                // fetch zip file
                $zip = $this->fetchZipFile(Str::lower($case['Attributes']['ticketnumber']),  $token);

                // fetch kml file
                $kml = $this->fetchKmlFile(Str::lower($case['Attributes']['ticketnumber']),  $token);
                // $accessToken = $this->getAccessToken();
                // if ($zip) {
                //     $this->uploadFileToSharePoint($zip, $folderName, 'zip', $accessToken);
                // }
                // if ($kml) {
                //     $this->uploadFileToSharePoint($kml, $folderName, 'kml', $accessToken);
                // }
            }
        }
    }
    public function getContactsFromTak($token)
    {
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
    public function handleContacts($service, $users)
    {
        try {
            //retrieve contacts from Dynamics
            $query = new QueryByAttribute('contact');
            // $query->ColumnSet = new \AlexaCRM\Xrm\ColumnSet(['emailaddress1']);
            $collection = $service->RetrieveMultiple($query);
            $data = json_decode(json_encode($collection), true);
            $entities = $data;
            Log::info($entities);

            //compare contacts from Dynamics with contacts from TAK
            foreach ($users as $user) {
                $contact = $this->getContactByEmail($service, $user['username']);
                if ($contact != $user['username'] . '@tak.com') {
                    $this->createContact($service, $user);
                    $this->createVolunteer($service, $user);
                }
            }

            return $entities;
        } catch (\Exception $ex) {
            // Handle exceptions
            Log::error($ex->getMessage());
            Log::error($ex->getTraceAsString());
            return response()->json(['error' => 'An error occurred.'], 500);
        }
    }
    private function getContactByEmail($service, $email)
    {
        try {
            $query = new QueryByAttribute('contact');
            $query->ColumnSet = new \AlexaCRM\Xrm\ColumnSet(['emailaddress1']);
            $query->AddAttributeValue('emailaddress1', $email);
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
    private function createContact($service, $user)
    {
        try {
            $contact = new \AlexaCRM\Xrm\Entity('contact');
            $contact['emailaddress1'] = $user['username'] . '@tak.com';
            $service->Create($contact);
            Log::info('hello create contact');
        } catch (\Exception $ex) {
            // Handle exceptions
            Log::error($ex->getMessage());
            Log::error($ex->getTraceAsString());
            return response()->json(['error' => 'An error occurred.'], 500);
        }
    }
    private function createVolunteer($service, $user)
    {
        try {
            $volunteer = new \AlexaCRM\Xrm\Entity('cct_volunteer');
            $volunteer['emailaddress'] = $user['username'] . '@tak.com';
            $volunteer['cct_name'] = $user['username'];
            $service->Create($volunteer);
        } catch (\Exception $ex) {
            // Handle exceptions
            Log::error($ex->getMessage());
            Log::error($ex->getTraceAsString());
            return response()->json(['error' => 'An error occurred.'], 500);
        }
    }
    private function getCases($service)
    {
        try {
            Log::info('before getting data');
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

    private function getBearerToken()
    {
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
    private function createMission($missionName, $token)
    {
        $baseUrl = Credential::first()->tak_url . '/Marti/api/missions';
        $response = Http::withoutVerifying()->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->put($baseUrl, [
                'name' => $missionName,
            ]);

        return $response;
    }

    private function validateFolderName($folderMissionName)
    {
        $folderName = preg_replace('/[^A-Za-z0-9_]/', '_', $folderMissionName);

        Log::info('hello folder:' . $folderName);
        return $folderName;
    }

    private function fetchZipFile($case, $token)
    {
        $takurl = Credential::first()->tak_url;

        $response = Http::withoutVerifying()->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get($takurl . '/Marti/api/missions/' . $case . '/archive');

        Log::info('hello zip: ' . $response);
        return $response->body();
    }

    private function fetchKmlFile($missionName, $token)
    {
        $takurl = Credential::first()->tak_url;
        $response = Http::withoutVerifying()
            ->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get($takurl . '/Marti/api/missions/' . $missionName . '/kml');
        Log::info('hello kml: ' . $response->body());
        return $response->body();
    }

    private function getAccessToken()
    {
        $clientId = '4d208f07-d9f9-4e22-8a6c-bbb6f0b39431';
        $clientSecret = 'LVGp6308v34K03eqLq/myMNSpE5qJsuHtMGa3AUCrHE=';
        $tenantId = '66b36574-7ebb-4383-8c9c-3af14215027a';
        $url = "https://accounts.accesscontrol.windows.net/$tenantId/oauth2/token";
        $response = Http::asForm()->post($url, [
            'grant_type' => 'client_credentials',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ]);
        $responseJson = json_decode($response->getBody(), true);
        return $responseJson['access_token'];
    }
    private function uploadFileToSharePoint($file, $folderName, $fileType, $accessToken)
    {
        $siteUrl = Credential::first()->sharepoint_url;
        $username = Credential::first()->sharepoint_login;
        $password = Credential::first()->sharepoint_password;
        $fileName = Carbon::now()->format('Y_m_d_H_i_s') . '.' . $fileType;
        
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer $accessToken",
                'Accept' => 'application/json;odata=verbose',
                'Content-Type' => 'application/json',
            ])
            ->post("$siteUrl/_api/web/folders", [
                '__metadata' => [
                    'type' => 'SP.Folder',
                ],
                'ServerRelativeUrl' => $siteUrl . "/sites/gsarics/$folderName", 
            ]);
            Log::info($response->status());
            Log::info($response->body());
            
          
            // $client = Http::withBasicAuth($username, $password);
            // $response =  $client->post($uploadUrl, [
            //     'headers' => [
            //         'Accept' => 'application/json;odata=verbose',
            //         'Content-Type' => 'application/json;odata=verbose',
            //     ],
            //     'body' => mb_convert_encoding($file, 'UTF-8')
            // ]);

            // Log::info($response->status());
        } catch (\Exception $ex) {
            // Handle exceptions
            Log::error($ex->getMessage());
            Log::error($ex->getTraceAsString());
            return response()->json(['error' => 'An error occurred.'], 500);
        }
    }
}
