<?php
namespace App\Repositories;
use Illuminate\Http\Request;


interface TakRepositoryInterface
{
    public function connect(String $baseUrl, String $login, String $password) : String;
    
    public function getContacts(String $baseUrl, String $token) : array;

    public function uploadFile($takUrl ,$token, $file , $missionName);
    
    public function createMission($takUrl, $missionName, $description, $token);
        
    public function fetchZipFile($takUrl, $caseTicketNumber, $token);

    public function fetchKmlFile($takUrl, $caseTicketNumber, $token);

}