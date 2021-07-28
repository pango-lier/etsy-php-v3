<?php
// generate methods.json from EtsyMethods.json
function mapEtsyJson()
{
    $methods_file = dirname(realpath(__FILE__)) . '/EtsyMethods.json';
    $methods = json_decode(file_get_contents($methods_file), true);
    $mapMethods = [];
    foreach ($methods['paths'] as $uri => $path) {
        foreach ($path as $httpMethod => $item) {

            $query = [];
            $pathParams = [];
            if (isset($item['parameters'])) {
                foreach ($item['parameters'] as $parameter) {
                    $params = [];
                    if (isset($parameter['schema']['minimum'])) {
                        $params['minimum'] = $parameter['schema']['minimum'];
                    }
                    if (isset($parameter['schema']['maximum'])) {
                        $params['maximum'] = $parameter['schema']['maximum'];
                    }
                    if (isset($parameter['schema']['enum'])) {
                        $params['enum'] = $parameter['schema']['enum'];
                    }
                    if (isset($parameter['schema']['nullable'])) {
                        $params['nullable'] = $parameter['schema']['nullable'];
                    }
                    if (isset($parameter['schema']['default'])) {
                        $params['default'] = $parameter['schema']['default'];
                    }
                    $params['required'] = $parameter['required'];
                    $params['type'] = $parameter['schema']['type'];

                    if ($parameter['in'] == 'path') {
                        $pathParams[$parameter['name']] = $params;
                    } else {
                        $query[$parameter['name']] = $params;
                    }
                }
            };
            $data = [];
            $contentType = null;
            if (isset($item['requestBody']['content'])) {
                foreach ($item['requestBody']['content'] as $method => $body) {

                    foreach ($body['schema']['properties'] as $key => $property) {
                        if (isset($property['minimum'])) {
                            $data[$key]['minimum'] = $property['minimum'];
                        }
                        if (isset($property['maximum'])) {
                            $data[$key]['maximum'] = $property['maximum'];
                        }
                        if (isset($property['enum'])) {
                            $data[$key]['enum'] = $property['enum'];
                        }
                        if (isset($property['nullable'])) {
                            $data[$key]['nullable'] = $property['nullable'];
                        }
                        if (isset($property['default'])) {
                            $data[$key]['default'] = $property['default'];
                        }
                        $required = [];
                        if (isset($body['schema']['required'])) {
                            $required = $body['schema']['required'];
                        }
                        $data[$key] = [
                            'type' => ($property['format'] ?? $property['type']) == 'float' ? 'float' :  $property['type'],
                            'required' => in_array($key, $required) ?? false,
                        ];
                    }
                    $contentType = $method;
                };
            }
            $mapMethods[$item['operationId']] = [
                'http_method' => $httpMethod,
                'content-type' => $contentType,
                'uri' => $uri,
                'params' => $pathParams,
                'query' => $query,
                'data' => $data
            ];
        }
    }
    print(json_encode($mapMethods));
}

function insertIsset(&$array, $data, $key)
{
    if (isset($data[$key])) {
        $array[$key] = $data[$key];
    }
}
