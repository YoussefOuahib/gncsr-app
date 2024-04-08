<?php
namespace App\Repositories;
use Illuminate\Http\Request;


interface SharePointRepositoryInterface
{
    public function connect(String $tenantId, String $clientId, String $clientSecret): String;
    
    public function uploadFileToSharePoint(String $sharePointUrl ,$file, String $folderName, String $fileType, String $accessToken);


}