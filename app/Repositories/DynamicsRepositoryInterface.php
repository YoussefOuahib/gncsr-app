<?php
namespace App\Repositories;
use Illuminate\Http\Request;


interface DynamicsRepositoryInterface
{
    public function connect();
    
    public function getContactByEmail(String $email);

    public function createNewContact(String $username);

    public function getContactId(String $email);
    
    public function createVolunteer($user, String $contactId);

    public function updateMissingPerson(String $incidentId);

    public function getAllIncidents();

    public function getIncidentWithDownloadZipFeature();

    public function updateIncident(String $incidentId) : void;
}