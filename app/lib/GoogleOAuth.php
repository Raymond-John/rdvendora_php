<?php
/**
 * Google OAuth 2.0 client using cURL.
 */
class GoogleOAuth {
    private $clientId;
    private $clientSecret;
    private $redirectUri;

    public function __construct($clientId, $clientSecret, $redirectUri) {
        if (stripos($redirectUri, 'http://') === 0 && !preg_match('#^http://(localhost|127\\.0\\.0\\.1)(:\\d+)?/#i', $redirectUri)) {
            $redirectUri = 'https://rdvendora.com/oauth2callback.php';
        }
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->redirectUri = $redirectUri;
    }

    public static function createPkcePair() {
        $verifier = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        return [$verifier, $challenge];
    }

    public function getAuthUrl($state, $codeChallenge = '') {
        if ($codeChallenge === '') {
            throw new InvalidArgumentException('PKCE code_challenge is required.');
        }
        $params = [
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ];
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    public function getAccessToken($code, $codeVerifier = '') {
        $post = [
            'code' => $code,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => $this->redirectUri,
            'grant_type' => 'authorization_code',
        ];
        if ($codeVerifier === '') {
            throw new Exception('Missing PKCE code_verifier.');
        }
        $post['code_verifier'] = $codeVerifier;
        $data = $this->curlJson('https://oauth2.googleapis.com/token', $post, true);

        if (empty($data['access_token'])) {
            $err = $data['error_description'] ?? $data['error'] ?? 'Invalid token response';
            throw new Exception($err);
        }
        return $data;
    }

    public function getUserInfo($accessToken) {
        $user = $this->curlJson('https://www.googleapis.com/oauth2/v2/userinfo', [], false, [
            'Authorization: Bearer ' . $accessToken,
        ]);
        if (empty($user['id']) || empty($user['email'])) {
            throw new Exception('Google did not return an email address for this account.');
        }
        return $user;
    }

    private function curlJson($url, array $post = [], $asPost = false, array $headers = []) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        if ($headers) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        if ($asPost) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post, '', '&', PHP_QUERY_RFC3986));
        }
        $response = curl_exec($ch);
        $curlErr = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            throw new Exception('Could not reach Google (' . $curlErr . ').');
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            throw new Exception('Unexpected response from Google.');
        }
        if ($httpCode < 200 || $httpCode >= 300) {
            $err = $data['error_description'] ?? $data['error'] ?? ('HTTP ' . $httpCode);
            throw new Exception(is_string($err) ? $err : 'Google OAuth request failed.');
        }
        return $data;
    }
}
