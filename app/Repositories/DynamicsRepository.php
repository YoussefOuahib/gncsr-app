<?php
namespace App\Repositories;

use AlexaCRM\WebAPI\ClientFactory;
use AlexaCRM\WebAPI\OData\OnlineSettings;
use AlexaCRM\Xrm\Entity;
use AlexaCRM\Xrm\Query\FetchExpression;
use AlexaCRM\Xrm\Query\QueryByAttribute;
use App\Models\Booking;
use App\Models\Credential;
use App\Repositories\DynamicsRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;


class DynamicsRepository implements DynamicsRepositoryInterface {
    protected $service;
    protected $userId;
    public function connect(int $userId) {
        try {
            $this->userId = $userId;
            $url = Credential::where('user_id', $this->userId)->first()->dynamics_url;
            $applicationId = Credential::where('user_id', $this->userId)->first()->dynamics_client_id;
            $applicationSecret = Credential::where('user_id', $this->userId)->first()->dynamics_client_secret;

            $settings = new OnlineSettings();
            $settings->instanceURI = $url;
            $settings->applicationID = $applicationId;
            $settings->applicationSecret = $applicationSecret;
            $this->service = ClientFactory::createOnlineClient(
                $url,
                $applicationId,
                $applicationSecret,
            ); 
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
    public function getContactByEmail( $takEmail) {
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

    public function createNewContact($username)
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

    public function getContactId($email)
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
        $collection = $this->service->RetrieveMultiple($fetchExpression);
        $records = json_decode(json_encode($collection), true);
        $entities = $records['Entities'];
        $contactid = $entities[0]["Attributes"]["contactid"];
        return $contactid;
    }

    public function createVolunteer($user, $contactId)
    {
        try {
            $volunteer = new Entity('cct_volunteer');
            $volunteer['emailaddress'] = $user['username'] . '@tak.com';
            $volunteer['cct_name'] = $user['username'];
            $volunteer['cct_volunteercontactinfo'] = $contactId;
            $this->service->Create($volunteer);
        } catch (\Exception $ex) {
            // Handle exceptions
            Log::error($ex->getMessage());
            Log::error($ex->getTraceAsString());
            return response()->json(['error' => 'An error occurred.'], 500);
        }
    }

    public function getAllIncidents()
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
            $collection = $this->service->RetrieveMultiple($fetchExpression);
            $records = json_decode(json_encode($collection), true);
            $entities = $records['Entities'];
      
            $filteredData = collect($entities)->filter(function ($item) {

                $isValidType = in_array($item['FormattedValues']['cct_incidenttype'], ['Missing Person', 'Lost Person']);
                // Convert date to Carbon instance
                $createdAt = Carbon::parse($item['FormattedValues']['createdon']);
                // $isValidDate = $createdAt->diffInMinutes(now()) <= 10;
                // Return true if both conditions are met
                //return $isValidType && $isValidDate;
                return $isValidType ;
            })->values();
            return $filteredData;
        }catch (\Exception $ex) {
            // Handle exceptions
            Log::error($ex->getMessage());
            Log::error($ex->getTraceAsString());
            return response()->json(['error' => 'An error occurred.'], 500);
        }
    }
    public function updateMissingPerson($incidentId)
    {
        try {
            $storagePath = '/public/incidents';
            $filePath = $storagePath . '/' . $incidentId . '.json';

            if (file_exists($filePath)) {
                unlink($filePath);
            }
            $query = new QueryByAttribute('incident');
            $query->AddAttributeValue('incidentid', $incidentId);

            $retrievedMissingPerson = $this->service->Retrieve('incident', $incidentId, new \AlexaCRM\Xrm\ColumnSet([
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
    public function getIncidentWithDownloadZipFeature() {
        try {
            $query = new QueryByAttribute('incident');
            $query->AddAttributeValue('cct_downloadzipfile', true);
            $query->ColumnSet = new \AlexaCRM\Xrm\ColumnSet(['incidentid', 'cct_downloadzipfile', 'title', 'ticketnumber']);
            $collection = $this->service->RetrieveMultiple($query);

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
    
    public function updateIncident(string $incidentId): void
    {
        $record = new \AlexaCRM\Xrm\Entity('incident', $incidentId );
        $record['cct_downloadzipfile'] = false;
        $this->service->Update( $record );
    }

}