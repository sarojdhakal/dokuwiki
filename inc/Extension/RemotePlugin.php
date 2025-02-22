<?php

namespace dokuwiki\Extension;

use dokuwiki\Remote\Api;
use ReflectionException;
use ReflectionMethod;

/**
 * Remote Plugin prototype
 *
 * Add functionality to the remote API in a plugin
 */
abstract class RemotePlugin extends Plugin
{
    private Api $api;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->api = new Api();
    }

    /**
     * Get all available methods with remote access.
     *
     * By default it exports all public methods of a remote plugin. Methods beginning
     * with an underscore are skipped.
     *
     * @return array Information about all provided methods. {@see dokuwiki\Remote\RemoteAPI}.
     * @throws ReflectionException
     */
    public function _getMethods()
    {
        $result = [];

        $reflection = new \ReflectionClass($this);
        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            // skip parent methods, only methods further down are exported
            $declaredin = $method->getDeclaringClass()->name;
            if ($declaredin === 'dokuwiki\Extension\Plugin' || $declaredin === 'dokuwiki\Extension\RemotePlugin') {
                continue;
            }
            $method_name = $method->name;
            if (strpos($method_name, '_') === 0) {
                continue;
            }

            // strip asterisks
            $doc = $method->getDocComment();
            $doc = preg_replace(
                ['/^[ \t]*\/\*+[ \t]*/m', '/[ \t]*\*+[ \t]*/m', '/\*+\/\s*$/m', '/\s*\/\s*$/m'],
                ['', '', '', ''],
                $doc
            );

            // prepare data
            $data = [];
            $data['name'] = $method_name;
            $data['public'] = 0;
            $data['doc'] = $doc;
            $data['args'] = [];

            // get parameter type from doc block type hint
            foreach ($method->getParameters() as $parameter) {
                $name = $parameter->name;
                $type = 'string'; // we default to string
                if (preg_match('/^@param[ \t]+([\w|\[\]]+)[ \t]\$' . $name . '/m', $doc, $m)) {
                    $type = $this->cleanTypeHint($m[1]);
                }
                $data['args'][] = $type;
            }

            // get return type from doc block type hint
            if (preg_match('/^@return[ \t]+([\w|\[\]]+)/m', $doc, $m)) {
                $data['return'] = $this->cleanTypeHint($m[1]);
            } else {
                $data['return'] = 'string';
            }

            // add to result
            $result[$method_name] = $data;
        }

        return $result;
    }

    /**
     * Matches the given type hint against the valid options for the remote API
     *
     * @param string $hint
     * @return string
     */
    protected function cleanTypeHint($hint)
    {
        $types = explode('|', $hint);
        foreach ($types as $t) {
            if (str_ends_with($t, '[]')) {
                return 'array';
            }
            if ($t === 'boolean') {
                return 'bool';
            }
            if (in_array($t, ['array', 'string', 'int', 'double', 'bool', 'null', 'date', 'file'])) {
                return $t;
            }
        }
        return 'string';
    }

    /**
     * @return Api
     */
    protected function getApi()
    {
        return $this->api;
    }
}
