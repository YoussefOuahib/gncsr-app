<?php
namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class DynamicsConnectionService
{
    public function connect(string $url, string $username, string $password)
    {
        try {
            $client = new Client();

            $response = $client->post($url, [
                'form_params' => [
                    'Username' => $username,
                    'Password' => $password,
                    'AuthType' => 'Office365',
                ],
            ]);

            $data = json_decode($response->getBody(), true);

            if (isset($data['OrganizationWebProxyClient'])) {
                Log::info("The connection successful for $url");
                return $data['OrganizationWebProxyClient'];
            } else {
                Log::error("The connection failed for $url. Client is null");
                return null;
            }
        } catch (\Exception $ex) {
            Log::error($ex->getMessage());
            Log::error($ex->getTraceAsString());
            return null;
        }
    }
}