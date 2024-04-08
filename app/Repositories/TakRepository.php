<?php

namespace App\Repositories;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TakRepository implements TakRepositoryInterface
{
    public function connect(String $baseUrl, String $login, String $password): String
    {
        try {
            $url = $baseUrl . '/oauth/token';

            $response = Http::withoutVerifying()->get($url, [
                'grant_type' => 'password',
                'username' => $login,
                'password' => $password,
            ]);

            // Check if the response was successful
            if ($response->successful()) {
                $responseJson = json_decode($response->getBody(), true);
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

    public function getContacts(String $baseUrl, String $token): array
    {
        try {
            $url = $baseUrl . '/user-management/api/list-users';
            $response = Http::withoutVerifying()->withHeaders(['Authorization' => 'Bearer ' . $token])->get($url);
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

    
    public function uploadFile($takUrl, $token, $file, $missionName)
    {
        $response = Http::withoutVerifying()->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->post($takUrl . '/Marti/api/missions/' . $missionName .'/contents', [
                'file' => $file,
            ]);
        return $response->body();
    }
    public function createMission($takUrl, $missionName, $description, $token)
    {
        $baseUrl = $takUrl. '/Marti/api/missions/' . $missionName;
        $response = Http::withoutVerifying()->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->put($baseUrl, [
                'description' => $description,
                'name' => $missionName,
            ]);
        return $response;
    }
    public function fetchZipFile($takUrl, $case, $token)
    {

        $response = Http::withoutVerifying()->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get($takUrl . '/Marti/api/missions/' . $case . '/archive');
        return $response->body();
    }
    public function fetchKmlFile($takUrl, $case, $token)
    {
        $response = Http::withoutVerifying()
        ->withHeaders(['Authorization' => 'Bearer ' . $token])
        ->get($takUrl . '/Marti/api/missions/' . $case . '/kml');
    return $response->body();
    }

}
