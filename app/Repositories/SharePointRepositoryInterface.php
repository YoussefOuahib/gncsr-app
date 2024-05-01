<?php
namespace App\Repositories;
use Illuminate\Http\Request;


interface SharePointRepositoryInterface
{
    public function connect(): String;
    public function createFolderInSharePoint(String $folderName);
    public function uploadFileToSharePoint($file, String $folderName, String $fileType);


}