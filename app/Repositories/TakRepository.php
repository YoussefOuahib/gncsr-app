<?php

namespace App\Repositories;

use App\Models\Credential;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TakRepository implements TakRepositoryInterface
{
    protected $takUrl;
    protected $token;
    protected $userId;
    

    public function connect($userId): String
    {
        try {
            $this->userId = $userId;
            $this->takUrl = Credential::where('user_id', $this->userId)->first()->tak_url;
            $login = Credential::where('user_id', $this->userId)->first()->tak_login;
            $password = Credential::where('user_id', $this->userId)->first()->password;
            $url = $this->takUrl . '/oauth/token';

            $response = Http::withoutVerifying()->get($url, [
                'grant_type' => 'password',
                'username' => $login,
                'password' => $password,
            ]);

            // Check if the response was successful
            if ($response->successful()) {
                $responseJson = json_decode($response->getBody(), true);
                $this->token = $response['access_token'];
                return $responseJson['access_token'];
            } else {
                // Handle unsuccessful response
                $statusCode = $response->status();
                $errorMessage = $response->json()['error_description'] ?? 'Unknown Error';
                throw new Exception("Request failed with status $statusCode: $errorMessage");
            }
        } catch (Exception $e) {
            // Handle any exceptions
            Log::info($e->getMessage());
            dd($e->getMessage());
        }
    }

    public function getContacts(): array
    {
        try {
            $url = $this->takUrl . '/user-management/api/list-users';
            $response = Http::withoutVerifying()->withHeaders(['Authorization' => 'Bearer ' . $this->token])->get($url);
            if ($response->successful()) {
                $users = json_decode($response->getBody(), true);
                return $users;
            } else {
                // Handle unsuccessful response
                return $response->json()['error_description'] ?? 'Unknown Error';
            }
        } catch (Exception $e) {
            Log::info($e->getMessage());
        }
    }


    public function uploadFile($file, String $missionName)
    {
        try {
            $file_content = storage_path($file);
            Log::info('hello content: ');
            $contents = file_get_contents($file_content);
            $jsonContent = json_decode($contents, true);
            $bodyContent = json_encode($jsonContent);
            $contentType = "application/json";
            $originalFileName = basename($file);

            // Make the POST request
            $response = Http::withoutVerifying()->withHeaders([
                'Authorization' => 'Bearer ' . $this->token,
                'Content-Type' => 'application/json',
            ])->attach('file', $bodyContent, $originalFileName)->post($this->takUrl . '/Marti/sync/upload', [
                'originalFileName' => $originalFileName,
            ]);
            $data = json_decode($response->getBody(), true);
            $hashValue = $data['Hash'];
            return $hashValue;
        } catch (Exception $e) {
            Log::info($e->getMessage());
        }
    }
    public function createMission($missionName, $description)
    {
        $baseUrl = $this->takUrl . '/Marti/api/missions/' . $missionName;
        $response = Http::withoutVerifying()->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->put($baseUrl, [
                'description' => $description,
                'name' => $missionName,
            ]);
        return $response;
    }
    public function associateFileToMission($hash, $missionName)
    {
        try {

            $response = Http::withoutVerifying()->withHeaders([
                'Authorization' => 'Bearer ' . $this->token,
                'Content-Type' => 'application/json',
            ])->put($this->takUrl . '/Marti/api/missions/' . $missionName . '/contents', [
                'hashes' => [$hash],
            ]);

            return $response;
        } catch (Exception $e) {
            Log::info($e->getMessage());
        }
    }
    public function fetchZipFile($case)
    {

        $response = Http::withoutVerifying()->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->get($this->takUrl . '/Marti/api/missions/' . $case . '/archive');
        return $response->body();
    }
    public function fetchKmlFile($case)
    {
        $response = Http::withoutVerifying()
            ->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->get($this->takUrl . '/Marti/api/missions/' . $case . '/kml');
        return $response->body();
    }
}
