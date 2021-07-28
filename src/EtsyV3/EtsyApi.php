<?php

namespace App\Library\EtsyApi\Src;

use Log;

class EtsyApi
{
    /** @var array $methods */
    private $methods = [];
    private $client;

    /**
     * EtsyApi constructor.
     * @param Etsy $server
     * @param TokenCredentials $tokenCredentials
     */
    public function __construct($client)
    {

        $methods_file = dirname(realpath(__FILE__)) . '/methods.json';
        $this->methods = json_decode(file_get_contents($methods_file), true);
        $this->client = $client;
    }
    // create miniMethod.json from Etsy method.json
    private function request($arguments)
    {
        $method = $this->methods[$arguments['method']];
        $args = $arguments['args'];
        $uri = preg_replace_callback('@{(.+?)}(\/|$)@', function ($matches) use ($args) {
            return $args["params"][$matches[1]] . $matches[2];
        }, $method['uri']);

        $query = $this->prepareQueries($method['params'], $method['query'], $args['params']);

        if (!empty($args['associations'])) {
            $query['includes'] = $this->prepareAssociations($args['associations']);
        }

        if (!empty($args['fields'])) {
            $query['fields'] = $this->prepareFields($args['fields']);
        }


        if (!empty($query)) {
            $uri .= "?" . http_build_query($query);
        }

        return $this->client->request($method['http_method'], $uri, $args['data'], $method['content-type']);
    }

    public function __call($method, $args)
    {
        if (isset($this->methods[$method])) {
            return call_user_func_array(array($this, 'request'), array(
                array(
                    'method' => $method,
                    'args' => array(
                        'data' => @$args[0]['data'],
                        'params' => @$args[0]['params'],
                        //'query' => @$args[0]['query'],
                        'associations' => @$args[0]['associations'],
                        'fields' => @$args[0]['fields']
                    )
                )
            ));
        } else {
            throw new \Exception('Method "' . $method . '" not exists');
        }
    }

    private function prepareQueries($mParams, $queries, $params)
    {
        $result = [];
        foreach ($params as $key => $param) {
            if (isset($queries[$key])) {
                $result[$key] = $param;
            } else if (!isset($mParams[$key])) {
                throw new \Exception($key . ' is invalid .', 400);
            }
        }
        return $result;
    }

    private function prepareAssociations($associations)
    {
        $includes = array();
        foreach ($associations as $key => $value) {
            if (is_array($value)) {
                $includes[] = $this->buildAssociation($key, $value);
            } else {
                $includes[] = $value;
            }
        }

        return implode(',', $includes);
    }

    private function prepareFields($fields)
    {

        return implode(',', $fields);
    }

    private function buildAssociation($assoc, $conf)
    {
        $association = $assoc;
        if (isset($conf['select'])) {
            $association .= "(" . implode(',', $conf['select']) . ")";
        }
        if (isset($conf['scope'])) {
            $association .= ':' . $conf['scope'];
        }
        if (isset($conf['limit'])) {
            $association .= ':' . $conf['limit'];
        }
        if (isset($conf['offset'])) {
            $association .= ':' . $conf['offset'];
        }
        if (isset($conf['associations'])) {
            $association .= '/' . $this->prepareAssociations($conf['associations']);
        }

        return $association;
    }
}
