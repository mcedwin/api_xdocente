<?php

namespace App\Services;

use Google\Auth\Credentials\ServiceAccountCredentials;
use GuzzleHttp\Client;

class FirebaseService
{
    private string $projectId;
    private array $credentials;

    public function __construct()
    {
        $this->projectId = config('services.firebase.project_id');
        $this->credentials = json_decode(
            file_get_contents(config('services.firebase.credentials')),
            true
        );
    }

    private function getAccessToken(): string
    {
        $scopes = ['https://www.googleapis.com/auth/firebase.messaging'];

        $credentials = new ServiceAccountCredentials(
            $scopes,
            $this->credentials
        );

        $token = $credentials->fetchAuthToken();
        return $token['access_token'];
    }

    public function sendNotification(
        string $fcmToken,
        string $title,
        string $body,
        array $data = []
    ): array {
        $client = new Client();
        $accessToken = $this->getAccessToken();

        $url = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";

        $payload = [
            'message' => [
                'token' => $fcmToken,
                // 'notification' => [
                //     'title' => $title,
                //     'body'  => $body,
                // ],
                'data' => array_map('strval', $data), // 👈 TODO STRING
                // 'android' => [
                //     'notification' => [
                //         'channel_id' => 'high_importance_channel',
                //     ],
                // ],
                 'android' => [
                    'priority' => 'HIGH',
                ],
            ],
        ];

        $response = $client->post($url, [
            'headers' => [
                'Authorization' => "Bearer {$accessToken}",
                'Content-Type'  => 'application/json',
            ],
            'json' => $payload,
        ]);

        return json_decode($response->getBody(), true);
    }
}