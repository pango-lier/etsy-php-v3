<?php

namespace App\Package\EtsyPhpV3\src\EtsyV3;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7;

//define('APP_REDIRECT_AUTH_URL', 'http://localhost:4000/oauth/verifier');

define('ETSY_CONNECT_URL', 'https://www.etsy.com/oauth/connect');

//define('ETSY_SCOPE', 'address_r address_w billing_r cart_r cart_w email_r favorites_r favorites_w feedback_r listings_d listings_r listings_w profile_r profile_w recommend_r recommend_w shops_r shops_w transactions_r transactions_w');

class EtsyClient
{
    //client ID
    public $clientId;
    //scope
    // address_r address_w billing_r cart_r cart_w email_r favorites_r favorites_w feedback_r listings_d listings_r listings_w profile_r profile_w recommend_r recommend_w shops_r shops_w transactions_r transactions_w
    public $scope;
    //access token, refresh token
    public $access_token;
    public $refresh_token;

    public $xRateLimitRemaining;
    // client guzzle : put, path, delete , post ,get,
    public $guzzleClient;

    // --enable auto refresh access token , if request first is error token invalid ->
    // --refresh token and save token ,new_token_created =true ->get new access_token -> request again-
    public $enable_reset_token_invalid = true;
    public $new_token_created;

    public function __construct($clientId, $scope = null, $access_token = null, $refresh_token = null)
    {
        $this->clientId = $clientId;
        $this->scope = $scope;
        $this->guzzleClient = new Client([
            'base_uri' => 'https://api.etsy.com',
        ]);
    }

    // Step 1: Authorization Code
    public function getUrlRedirect($redirectUri,$codeVerifier,$state)
    {
        $codeChallenge = strtr(rtrim(
            base64_encode(hash('sha256', $codeVerifier, true)),
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

        return 'https://www.etsy.com/oauth/connect?' . $query;
    }

    // Step 2: Grant Access#
    public function getAccessToken($redirectUri,$codeVerifier, $code)
    {
        // Step 3: Get Access Token
        try {
            $response = $this->guzzleClient->post('v3/public/oauth/token', [
                'form_params' => [
                    'grant_type' => 'authorization_code',
                    'client_id' => $this->clientId,
                    'redirect_uri' => $redirectUri, //APP_REDIRECT_AUTH_URL,
                    'code_verifier' => $codeVerifier,
                    'code' => $code,
                ],
            ]);

            $token = json_decode((string) $response->getBody(), true);
            $this->setToken((array) $token, true);

            return $token;
        } catch (ClientException  $e) {
            throw new EtsyRequestException($e);
        }
    }

    public function refreshToken()
    {
        try {
            $response = $this->guzzleClient->post('v3/public/oauth/token', [
                'form_params' => [
                    'grant_type' => 'refresh_token',
                    'client_id' => $this->clientId,
                    'refresh_token' => $this->refresh_token,
                ],
            ]);
            $token = json_decode((string) $response->getBody());
            $this->setToken((array) $token);

            return $token;
        } catch (ClientException  $e) {
            throw new EtsyRequestException($e);
        }
    }

    public function setToken($token)
    {
        $this->access_token = $token['access_token'] ?? null;
        $this->refresh_token = $token['refresh_token'] ?? null;
        $this->new_token_created = true;
    }

    public function request($methods, $uri, $data = [], $contentType = null)
    {
        $this->new_token_created = false;
        $body = [];

        // refresh token if fail
        for ($i = 0; $i < 1 + $this->enable_reset_token_invalid; ++$i) {
            if (1 == $i) {
                $this->refreshToken();
            }

            try {
                if ('post' == strtolower($methods)) {
                    $body = $this->getBody($contentType, $data);
                }
                $body['headers'] = [
                    'x-api-key' => $this->clientId,
                    'authorization' => 'Bearer ' . $this->access_token,
                ];
                $response = $this->guzzleClient->request($methods, $uri, $body);
                $this->xRateLimitRemaining = $response->getHeader('X-RateLimit-Remaining') ?? null;

                break;
            } catch (ClientException $e) {
                $jsonError = $e->getResponse()->getBody()->getContents();
                $error = json_decode((string) $jsonError);
                if (0 == $i && isset($error->error) && 'invalid_token' == $error->error) {
                    continue;
                }
                $errorMessage = ($error->error ?? '') . ' :' . ($error->error_description ?? '');

                throw new \Exception($errorMessage, 400);
            }
        }

        return json_decode((string) $response->getBody(), true);
    }

    public function getBody($contentType, $data = [])
    {
        switch ($contentType) {
            case 'application/x-www-form-urlencoded':
                return ['form_params' => $data];

                break;

            case 'multipart/form-data':
                $multipart = [];
                foreach ($data as $key => $item) {
                    //check item is url
                    if (('file' == $key || 'image' == $key) && (bool) filter_var($item, FILTER_VALIDATE_URL)) {
                        $resource = Psr7\Utils::tryFopen($item, 'r');
                        $steam = Psr7\Utils::streamFor($resource);
                        $content = $steam;
                    } else {
                        $content = $item;
                    }
                    $multipart[] = [
                        'name' => $key,
                        'contents' => $content,
                    ];
                }

                return ['multipart' => $multipart];

                break;

            case 'application/json':
                return ['json' => $data];

                break;

            default:
                throw new \Exception('Content type is empty', 400);

                break;
        }
    }
}

class EtsyRequestException extends \Exception
{
    public $jsonError;

    public function __construct($e)
    {
        $this->jsonError = $e->getResponse()->getBody()->getContents();
        $error = json_decode((string) $this->jsonError);
        $errorMessage = ($error->error ?? '') . ' :' . ($error->error_description ?? '');
        parent::__construct($errorMessage, 1, $e);
    }
}
