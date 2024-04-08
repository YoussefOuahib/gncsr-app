<?php
namespace App\Repositories;
use Illuminate\Http\Request;


interface DynamicsRepositoryInterface
{
    public function connect($url, $applicationId, $applicationSecret);
    
    public function getContactByEmail($service, $email);

    public function createNewContact($service, $username);

    public function getContactId($service, $email);
    
    public function createVolunteer($service, $user, $contactId);

    public function updateMissingPerson($service, $incidentId);

    public function getAllIncidents($service);

    public function getIncidentWithDownloadZipFeature($service);


}