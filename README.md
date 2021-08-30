# etsy-php-v3

#PHP-Laravel-Redis-Guzzel

#Setup EtsyOauth.php Example, Get Token.

##Config Etsy client_id ,etsy_scope
```
public function __construct($accountId = null, $access_token = null, $refresh_token = null)
    {
        $this->accountId = $accountId;
        $this->client = new EtsyClient(env('ETSY_KEYSTRING'), ETSY_SCOPE);
    }
```

##Requesting an OAuth Token
###Step 1: Request an Authorization Code
Get URL to redirect to Etsy Store
```
$getUrlRedirect= (new EtsyOauth())->getUrlRedirect(route('callback.verifier'));

```    
###Step 2: Grant Access
###Step 3: Request an Access Token
```
$token = (new EtsyOauth())->getAccessToken($request->state, $request->code, route('callback.verifier'));
```
#EtsyApi.php
- Map methods.json to etsy method function.
#EtsyClient.php
- Guzzle client.
#How to use etsy-php-v3
