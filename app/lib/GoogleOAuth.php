<?php
/**
 * Simple Google OAuth 2.0 Client – No Composer required
 * Uses only native PHP functions (cURL, JSON).
 */
class GoogleOAuth {
    private $clientId;
    private $clientSecret;
    private $redirectUri;

    public function __construct($clientId, $clientSecret, $redirectUri) {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->redirectUri = $redirectUri;
    }

    /**
     * Generate the Google OAuth URL
     */
    public function getAuthUrl() {
        $params = [
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => 'email profile',
            'access_type' => 'online',
        ];
        return 'https://accounts.google.com/o/oauth2/auth?' . http_build_query($params);
    }

    /**
     * Exchange authorization code for access token
     */
    public function getAccessToken($code) {
        $url = 'https://oauth2.googleapis.com/token';
        $postData = [
            'code' => $code,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => $this->redirectUri,
            'grant_type' => 'authorization_code',
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Only for local testing – remove on production
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception('Failed to get access token: ' . $response);
        }

        $data = json_decode($response, true);
        if (!isset($data['access_token'])) {
            throw new Exception('Invalid token response: ' . $response);
        }
        return $data;
    }

    /**
     * Get user info using the access token
     */
    public function getUserInfo($accessToken) {
        $url = 'https://www.googleapis.com/oauth2/v2/userinfo';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Only for local testing – remove on production
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception('Failed to get user info: ' . $response);
        }

        $user = json_decode($response, true);
        if (empty($user['id']) || empty($user['email'])) {
            throw new Exception('Invalid user info response');
        }
        return $user;
    }
}