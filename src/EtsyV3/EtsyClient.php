<?php

namespace App\Library\EtsyApi\Src;

use GuzzleHttp\Client;
use Illuminate\Http\Request;
use GuzzleHttp\Psr7;
use Log;
use Redis;
use Str;

define('APP_REDIRECT_AUTH_URL', 'http://localhost:4000/oauth/verifier');


define('ETSY_BASE_URL', 'https://api.etsy.com');
define('ETSY_TOKEN_URL', 'https://api.etsy.com/v3/public/oauth/token');
define('ETSY_CONNECT_URL', 'https://www.etsy.com/oauth/connect');

define('ETSY_SCOPE', 'address_r address_w billing_r cart_r cart_w email_r favorites_r favorites_w feedback_r listings_d listings_r listings_w profile_r profile_w recommend_r recommend_w shops_r shops_w transactions_r transactions_w');

class EtsyClient
{
    public $client;
    public $clientId;
    public $token;
    public $scope;
    public $xRateLimitHeaders;

    public function __construct($clientId, $token = [], $scope = null)
    {
        $this->clientId =  $clientId;
        $this->setToken($token);
        $this->client = new Client([
            'base_uri' => ETSY_BASE_URL,
        ]);
    }
    // Step 1: Authorization Code
    public function getUrlRedirect($redirectUri)
    {
        // $data = $this->request('GET', '/v3/application/users/340549007/shops');
        // dd($data);

        $state = strtolower(Str::random(40));
        $code_verifier = strtolower(Str::random(128));
        Redis::hset('state_oauth2', $state, 1);
        Redis::hset('code_verifier_oauth2', $state, $code_verifier);

        Log::info('aa', [
            'state' => $state,
            'code_verifier_oauth2' => $code_verifier
        ]);
        $codeChallenge = strtr(rtrim(
            base64_encode(hash('sha256', $code_verifier, true)),
            '='
        ), '+/', '-_');

        $query = http_build_query([
            'response_type' => 'code',
            'redirect_uri' => $redirectUri, //APP_REDIRECT_AUTH_URL,
            'scope' => $this->scope, //ETSY_SCOPE,
            'client_id' => $this->clientId,
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ]);

        return ETSY_CONNECT_URL . '?' . $query;
    }
    // Step 2: Grant Access#
    public function getAccessToken($state, $code, $redirectUri)
    {
        if ((!Redis::hexists('state_oauth2', $state)) && (!Redis::hexists('code_verifier_oauth2', $state))) {
            throw new \Exception('State not match', 1);
        }
        $codeVerifier = Redis::hget('code_verifier_oauth2', $state);
        Redis::hdel('state_oauth2', $state);
        Redis::hdel('code_verifier_oauth2', $state);
        // Step 3: Get Access Token
        $response = $this->client->post('v3/public/oauth/token', [
            'form_params' => [
                'grant_type' => 'authorization_code',
                'client_id' => $this->clientId,
                'redirect_uri' => $redirectUri, //APP_REDIRECT_AUTH_URL,
                'code_verifier' => $codeVerifier,
                'code' => $code,
            ],
        ]);
        $token = json_decode((string) $response->getBody(), true);
        $this->setToken($token, true);
        return $token;
    }

    public function refreshToken()
    {
        $response = $this->client->post('v3/public/oauth/token', [
            'form_params' => [
                'grant_type' => 'refresh_token',
                'client_id' => $this->clientId,
                'refresh_token' => $this->token['refresh_token'],
            ],
        ]);
        $token = json_decode((string) $response->getBody());
        $this->setToken($token, true);
        return $token;
    }

    public function setToken($token, $newToken = false)
    {
        $this->token['access_token'] = (array)$token['access_token'];
        $this->token['refresh_token'] = (array)$token['refresh_token'];
        $this->token['new_token'] = $newToken;
        //auto renew token if invalid_token
        //update date token if new_token=true
    }

    public function getToken()
    {
        return $this->token;
    }

    public function request($methods, $uri, $data = [], $contentType = null)
    {
        $this->token['new_token'] = false;
        $body = [];
        if (strtolower($methods) == 'post') {
            $body = $this->getBody($contentType, $data);
        }
        for ($i = 0; $i < 2; $i++) {
            try {
                $body['headers'] =  [
                    'x-api-key' => $this->clientId,
                    'authorization' => 'Bearer ' . $this->token['access_token'],
                    //'Content-Type' => 'application/json; charset = utf-8',
                    //'Content-Type' => 'application/x-www-form-urlencoded'
                ];
                $response = $this->client->request($methods, $uri, $body);

                $this->xRateLimitHeaders = [
                    'X-RateLimit-Limit' => $response->getHeader('X-RateLimit-Limit') ?? null,
                    'X-RateLimit-Remaining' => $response->getHeader('X-RateLimit-Remaining') ?? null,
                ];

                break;
            } catch (\GuzzleHttp\Exception\ClientException $e) {
                $jsonError = $e->getResponse()->getBody()->getContents();
                $error = json_decode((string) $jsonError);
                if (isset($error->error) && $error->error == 'invalid_token') {
                    $this->refreshToken();
                    continue;
                }
                break;
                throw new \Exception($e->getMessage(), 400);
            }
        }
        return json_decode((string) $response->getBody(), true);
    }

    protected function getBody($contentType, $data = [])
    {
        switch ($contentType) {
            case "application/x-www-form-urlencoded":
                return ['form_params' => $data];
                break;
            case "multipart/form-data":
                $multipart = [];
                foreach ($data as $key => $item) {
                    //check item is url
                    if (($key == 'file' || $key == 'image') && (bool) filter_var($item, FILTER_VALIDATE_URL)) {
                        $resource = Psr7\Utils::tryFopen($item, 'r');
                        $steam = Psr7\Utils::streamFor($resource);
                        $content = $steam;
                    } else $content = $item;
                    $multipart[] = [
                        'name' => $key,
                        'contents' =>  $content,
                    ];
                }
                return ['multipart' => $multipart];
                break;
            case "application/json":
                return ['json' => $data];
                break;
            default:
                throw new \Exception('Content type is empty', 400);
                break;
        }
    }
}
