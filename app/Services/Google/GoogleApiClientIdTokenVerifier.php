<?php

namespace App\Services\Google;

use Google\Client as GoogleClient;

class GoogleApiClientIdTokenVerifier implements GoogleIdTokenVerifier
{
    /**
     * @return array<string, mixed>|null
     */
    public function verify(string $idToken): ?array
    {
        $clientId = trim((string) config('services.google.client_id'));

        if ($clientId === '') {
            throw new GoogleIdTokenConfigurationException('Google Client ID is not configured.');
        }

        $client = new GoogleClient([
            'client_id' => $clientId,
        ]);

        $payload = $client->verifyIdToken($idToken);

        return is_array($payload) ? $payload : null;
    }
}
