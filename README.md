# etsy-php-v3

# PHP-Laravel-Redis-Guzzel

# Setup EtsyOauth.php Example, Get Token.

## Config Etsy client_id ,etsy_scope

```
public function __construct($accountId = null, $access_token = null, $refresh_token = null)
    {
        $this->accountId = $accountId;
        $this->client = new EtsyClient(env('ETSY_KEYSTRING'), ETSY_SCOPE);
    }
```

## Requesting an OAuth Token

### Step 1: Request an Authorization Code

Get URL to redirect to Etsy Store

```
$getUrlRedirect= (new EtsyOauth())->getUrlRedirect(route('callback.verifier'));

```

### Step 2: Grant Access

### Step 3: Request an Access Token

```
$token = (new EtsyOauth())->getAccessToken($request->state, $request->code, route('callback.verifier'));
```

# EtsyApi.php

- Map methods.json to etsy method function.

# EtsyClient.php

- Guzzle client.

# How to use etsy-php-v3


## Step 1: Get auth account.
```
$etsyOauth = new EtsyOauth($accountId);
$api = $etsyOauth->getApi();

```

## Call api

## Step 2: check file methods.json .

```
  "createDraftListing": {
    "http_method": "post",
    "content-type": "application/x-www-form-urlencoded",
    "uri": "/v3/application/shops/{shop_id}/listings",
    "params": { "shop_id": { "minimum": 1, "required": true, "type": "integer" } },
    "query": [],
    "data": {
      "quantity": { "type": "integer", "required": true },
      "title": { "type": "string", "required": true },
      "description": { "type": "string", "required": true },
      "price": { "type": "float", "required": true },
      "who_made": { "type": "string", "required": true },
      "when_made": { "type": "string", "required": true },
      "taxonomy_id": { "type": "integer", "required": true },
      "shipping_profile_id": { "type": "integer", "required": true },
      "materials": { "type": "array", "required": false },
      "shop_section_id": { "type": "integer", "required": false },
      "processing_min": { "type": "integer", "required": false },
      "processing_max": { "type": "integer", "required": false },
      "tags": { "type": "array", "required": false },
      "recipient": { "type": "string", "required": false },
      "occasion": { "type": "string", "required": false },
      "styles": { "type": "array", "required": false },
      "item_weight": { "type": "float", "required": false },
      "item_length": { "type": "float", "required": false },
      "item_width": { "type": "float", "required": false },
      "item_height": { "type": "float", "required": false },
      "item_weight_unit": { "type": "string", "required": false },
      "item_dimensions_unit": { "type": "string", "required": false },
      "is_personalizable": { "type": "boolean", "required": false },
      "image_ids": { "type": "array", "required": false },
      "is_supply": { "type": "boolean", "required": false },
      "is_customizable": { "type": "boolean", "required": false },
      "is_taxable": { "type": "boolean", "required": false }
    }
  },
```
### Example run Api
## Step 2: Example data .

```
$api->createDraftListing([
    'params':
        [
            'shop_id':xxxxx   // required =true
        ],
    'data':
        [
      "quantity": 1,
      "title": "example title",   // required =true
      "description": "example",   // required =true
      "price": 11.11,   // required =true
      "who_made": { "type": "string", "required": true },// required =true
      "when_made": { "type": "string", "required": true },// required =true
      "taxonomy_id": { "type": "integer", "required": true },// required =true
      "shipping_profile_id": { "type": "integer", "required": true },// required =true
      "is_customizable": false,
      "is_taxable": false
        ]
])
```
