<?php

namespace App\Repositories;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SharePointRepository implements SharePointRepositoryInterface
{
    public function connect(String $tenantId, String $clientId,  String $clientSecret) : String
    {
        $url = "https://accounts.accesscontrol.windows.net/$tenantId/oauth2/token";
        $response = Http::asForm()->post($url, [
            'grant_type' => 'client_credentials',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ]);
        $responseJson = json_decode($response->getBody(), true);
        Log::info('hello access token');
        Log::info($response->body());
        return $responseJson['access_token']; 
    }
    public function uploadFileToSharePoint(String $sharePointUrl ,$file, String $folderName, String $fileType, String $accessToken)
    {
        $fileName = $folderName . '.' . $fileType;
        $fileUtf8 = mb_convert_encoding($file, 'UTF-8');;
        
        try {
            // $response = Http::withHeaders([
            //     'Authorization' => "Bearer $accessToken",
            //     'Accept' => 'application/json;odata=verbose',
            //     'Content-Type' => 'application/json',
            // ])->post("$sharepointUrl/_api/web/GetFolderByServerRelativeUrl('/sites/GSARICS/Shared Documents')/files/add(url='$fileName',overwrite=true)", [
            //     'body' => $fileUtf8,
            // ]);
            $response = Http::withHeaders([
                'Authorization' => "Bearer $accessToken",
                'Accept' => 'application/json;odata=verbose',
                'Content-Type' => 'application/json',
            ])
                ->get("$sharePointUrl/_api/web/folders/GetFolderByServerUrl('Shared Documents')");
            Log::info('Response Status Code: ' . $response->status());
            Log::info('Response Body: ' . $response->body());
        } catch (\Exception $ex) {
            // Handle exceptions
            Log::error($ex->getMessage());
            Log::error($ex->getTraceAsString());
            return response()->json(['error' => 'An error occurred.'], 500);
        }
        
    }
}