<?php
namespace App\Repositories;
use Illuminate\Http\Request;


interface TakRepositoryInterface
{
    public function connect(int $userId) : String;
    
    public function getContacts() : array;

    public function uploadFile(String $file , String $missionName);
    
    public function createMission(String $missionName, String $description);
        
    public function fetchZipFile(String $caseTicketNumber);

    public function fetchKmlFile(String $caseTicketNumber);

}