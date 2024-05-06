<?php

namespace App\Repositories;

use App\Models\Credential;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SharePointRepository implements SharePointRepositoryInterface
{
    protected $sharePointUrl;
    protected $sitePath;
    protected $accessToken;
    public function __construct()
    {
        $this->sharePointUrl = Credential::where('user_id', auth()->user()->id)->first()->sharepoint_url;
        $this->sitePath = parse_url($this->sharePointUrl);
        $this->accessToken = $this->connect();
    }
    public function connect() : String
    {
        $tenantId = Credential::where('user_id', auth()->user()->id)->first()->sharepoint_tenant_id;
        $clientId = Credential::where('user_id', auth()->user()->id)->first()->sharepoint_client_id . '@' . $tenantId;
        $clientSecret = Credential::where('user_id', auth()->user()->id)->first()->sharepoint_client_secret;
        $parts = parse_url($this->sharePointUrl);
        $host = $parts['host'];
        $url = "https://accounts.accesscontrol.windows.net/$tenantId/tokens/OAuth/2";
        $response = Http::asForm()->post($url, [
            'grant_type' => 'client_credentials',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'resource' => '00000003-0000-0ff1-ce00-000000000000/' . $host . '@' . $tenantId,
        ]);
        $responseJson = json_decode($response->getBody(), true);
        Log::info('hello access token');
        Log::info($response->body());
        return $responseJson['access_token']; 
    }
    public function uploadFileToSharePoint($file, String $folderName, String $fileType)
    {
        $fileName = $folderName . '.' . $fileType;

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer $this->accessToken",
            ])->attach('file', $file ,$fileName)->post($this->sharePointUrl . "/_api/web/GetFolderByServerRelativeUrl('$this->sitePath/incident/$folderName')/files/add(url='$fileName',overwrite=true)");
            Log::info($response->status());
            Log::info($response->getBody());
            
        } catch (\Exception $ex) {
            // Handle exceptions
            Log::error($ex->getMessage());
            Log::error($ex->getTraceAsString());
            return response()->json(['error' => 'An error occurred.'], 500);
        }
        
    }
    public function createFolderInSharePoint(String $folderName) {
        try {
            
            $isFolderExists = Http::withoutVerifying()->withHeaders(['Authorization' => 'Bearer ' . $this->accessToken, 'Accept' => 'application/json; odata=verbose'])
            ->get($this->sharePointUrl . "/_api/web/GetFolderByServerRelativeUrl('$this->sitePath/incident/" . $folderName . "')");
            if($isFolderExists->status() == 404) {
                $response = Http::withoutVerifying()->withHeaders(['Authorization' => 'Bearer ' . $this->accessToken])
                ->post($this->sharePointUrl . "/_api/web/folders", [
                    "ServerRelativeUrl" => "$this->sitePath/incident/" . $folderName,
                ]);
                Log::info('creating file');
                Log::info($response->getBody());
                Log::info($response->status());
            }
            
        } catch (Exception $e) {
            Log::error($e->getMessage());
            dd($e->getMessage());
        }
    }
}