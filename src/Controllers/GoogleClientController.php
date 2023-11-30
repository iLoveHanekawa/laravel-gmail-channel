<?php

namespace App\Http\Controllers;

use App\Models\GoogleToken;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;

class GoogleClientController extends Controller
{
    private string $codeUrl;
    private string $scopes;
    private string $redirectUrl;
    private string $clientId;
    private string $clientSecret;
    private string $tokenUrl;
    public function __construct() {
        $this->codeUrl = 'https://accounts.google.com/o/oauth2/v2/auth';
        $this->scopes = 'https://mail.google.com/';
        $this->redirectUrl = 'http://localhost/29ksubscriptions/public/api/google/redirect';
        $this->clientId = env('GOOGLE_CLIENT_ID');
        $this->clientSecret = env('GOOGLE_CLIENT_SECRET');
        $this->tokenUrl = 'https://oauth2.googleapis.com/token';
    }
    public function show() {
        $url = $this->codeUrl . '?' . http_build_query([
            'scope'=> $this->scopes,
            'access_type' => 'offline',
            'include_granted_scopes' => 'true',
            'response_type' => 'code',
            'state' => 'state_parameter_passthrough_value',
            'redirect_uri' => $this->redirectUrl,
            'client_id' => $this->clientId,
            'prompt' => 'consent'
        ]);
        return Redirect::to($url);
    }
    public function index(Request $request) {
        $code = $request->query('code');
        $body = json_encode([
            'code' => $code, 
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => $this->redirectUrl,
            'grant_type' => 'authorization_code'
        ]);
        $headers = [
            'Content-Type' => 'application/json',
        ];
        $res = Http::withHeaders($headers)->withBody($body)->post($this->tokenUrl);
        $res = $res->json();
        GoogleToken::updateOrCreate([
            'key' => 'access_token'
        ], [
            'value' => $res['access_token']
        ]);
        GoogleToken::updateOrCreate([
            'key' => 'refresh_token'
        ], [
            'value' => $res['refresh_token']
        ]);
        GoogleToken::updateOrCreate([
            'key' => 'id_token'
        ], [
            'value' => $res['id_token']
        ]);
        return 'finished';
    }
    public function getAccessToken(): string {
        $idToken = GoogleToken::where(['key' => 'id_token'])->first();
        if($this->accessTokenExpired($idToken['value'])) {
            return $this->exchangeRefreshToken();
        }
        else {
            $oldAccessToken = GoogleToken::where(['key' => 'access_token'])->first();
            return $oldAccessToken['value'];
        }
    }
    private function accessTokenExpired(string $idToken) {
        list($headerB64, $payloadB64, $signature) = explode('.', $idToken);
        $payload = base64_decode(strtr($payloadB64, '-_', '+/'));
        $payloadData = json_decode($payload, true);
        $currentTimestamp = time();
        if (isset($payloadData['exp']) && $payloadData['exp'] >= $currentTimestamp) {
            return false;
        } else {
            return true;
        }
    }
    private function exchangeRefreshToken() {
        $refreshToken = GoogleToken::where(['key' => 'refresh_token'])->first();
        $body = json_encode([
            'refresh_token' => $refreshToken['value'], 
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => $this->redirectUrl,
            'grant_type' => 'refresh_token'
        ]);
        $headers = [
            'Content-Type' => 'application/json',
        ];
        $res = Http::withHeaders($headers)->withBody($body)->post($this->tokenUrl);
        $res = $res->json();
        GoogleToken::where(['key' => 'access_token'])->update(['value' => $res['access_token']]);
        GoogleToken::where(['key' => 'id_token'])->update(['value' => $res['id_token']]);
        return $res['access_token'];
    }
}
