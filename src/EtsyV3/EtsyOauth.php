<?php

namespace App\Package\EtsyPhpV3\src\EtsyV3;

use Redis;
use Str;

define('ETSY_SCOPE', 'address_r address_w billing_r cart_r cart_w email_r favorites_r favorites_w feedback_r listings_d listings_r listings_w profile_r profile_w recommend_r recommend_w shops_r shops_w transactions_r transactions_w');
class EtsyOauth
{
    public $accountId;
    public $client;

    /**
     * Setup OAuth config.
     *
     * @param Account|array $auth
     * @param mixed         $accountId
     * @param null|mixed    $access_token
     * @param null|mixed    $refresh_token
     */
    public function __construct($accountId = null, $access_token = null, $refresh_token = null)
    {
        $this->accountId = $accountId;
        $this->client = new EtsyClient(env('ETSY_KEYSTRING'), ETSY_SCOPE);
    }

    public function getApi()
    {
        $this->setAutoResetToken();
        $response = new EtsyApi($this->client);
        if (true == $this->client->new_token_created) {
            $this->setRedisToken($this->accountId, $this->client->access_token, $this->client->refresh_token);
        }

        return $response;
    }

    // Step 1: Authorization Code
    public function getUrlRedirect($redirectUri)
    {
        $state = strtolower(Str::random(40));
        $codeVerifier = strtolower(Str::random(128));
        Redis::hset('state_oauth2', $state, 1);
        Redis::hset('code_verifier_oauth2', $state, $codeVerifier);
        return $this->client->getUrlRedirect($redirectUri, $codeVerifier, $state);
    }

    // Step 2: Grant Access#
    //Step 3: Request an Access Token
    public function getAccessToken($state, $code, $redirectUri)
    {
        if ((!Redis::hexists('state_oauth2', $state)) && (!Redis::hexists('code_verifier_oauth2', $state))) {
            throw new \Exception('State not match', 400);
        }
        $codeVerifier = Redis::hget('code_verifier_oauth2', $state);
        Redis::hdel('state_oauth2', $state);
        Redis::hdel('code_verifier_oauth2', $state);

        $token= $this->client->getAccessToken($redirectUri, $codeVerifier, $code);
        $this->setRedisToken($this->accountId, $this->client->access_token, $this->client->refresh_token);
        return $token;
    }

    public function getRateLimitRemaining()
    {
        return $this->client->xRateLimitRemaining;
    }

    private function setAutoResetToken()
    {
        if (!Redis::hexists('token_oauth2', $this->accountId)) {
            $this->client->refreshToken(); //access_token and refresh_token was set
            $this->setRedisToken($this->accountId, $this->client->access_token, $this->client->refresh_token);
        } else {
            $token = Redis::hget('token_oauth2', $this->accountId);
            if (now() - $token['updated_at_token'] > 3400) {
                $this->client->refreshToken(); //access_token and refresh_token was set
                $this->setRedisToken($this->accountId, $this->client->access_token, $this->client->refresh_token);
            } else {
                $this->client->access_token = $token->access_token;
                $this->client->refresh_token = $token->refresh_token;
            }
        }
    }

    private function setRedisToken($accountId, $accessToken, $refreshToken)
    {
        Redis::hmset('token_oauth2', $accountId, [
            'id' => $accountId,
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'updated_at_token' => now(),
        ]);
    }
}
/*
'access_token' => '340549007.xprxmzICsLSqq8IIb-lh1p1omVse4UX8mYJPXR2s-1Ly3O-R5a-4FPeDfgwP49gBwm_qmTyokdSLtpp5NdlcdLtEBAa',
            'refresh_token' => '340549007.2C72k6bgd35plIh8Ciqw58PsflCGngZSXF_lqcFsi6iBtp92MLBAdQWn-4pqfik2b_r0Tu4q2Snz7C5dHviv7Yepa3',
 */
