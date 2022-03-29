<?php


namespace Picqer\BolRetailerV5\OpenApi;

class ClientGenerator
{
    protected $specs;

    protected static $overrideMethodNames = [
        'postShippingLabel' => 'createShippingLabel',
        'postInbound' => 'createInbound'
    ];

    protected static $paramTypeMapping = [
        'array' => 'array',
        'string' => 'string',
        'boolean' => 'bool',
        'integer' => 'int',
        'float' => 'float',
        'number' => 'float'
    ];

    public function __construct()
    {
        $this->specs = json_decode(file_get_contents(__DIR__ . '/apispec.json'), true);
    }

    public static function run()
    {
        $generator = new static;
        $generator->generateClient();
    }

    public function generateClient()
    {
        $code = [];
        $code[] = '<?php';
        $code[] = '';
        $code[] = sprintf('namespace %s;', $this->getClientNamespace());
        $code[] = '';
        $code[] = '// This class is auto generated by OpenApi\ClientGenerator';
        $code[] = 'class Client extends BaseClient';
        $code[] = '{';
//        $this->generateMethod('/retailer/orders', 'get', $code);
//        $this->generateMethod('/retailer/offers', 'post', $code);
//        $this->generateMethod('/retailer/commission', 'post', $code);
//        $this->generateMethod('/retailer/offers/{offer-id}/price', 'put', $code);
//        $this->generateMethod('/retailer/shipping-labels/{shipping-label-id}', 'get', $code);
//        $this->generateMethod('/retailer/offers/export', 'post', $code);

        foreach ($this->specs['paths'] as $path => $methodsDef) {
            foreach ($methodsDef as $method => $methodDef) {
                $this->generateMethod($path, $method, $code);
            }
        }
        $code[] = '}';
        $code[] = '';

        //echo implode("\n", $code);

        file_put_contents(__DIR__ . '/../Client.php', implode("\n", $code));
    }

    protected function generateMethod(string $path, string $httpMethod, array &$code): void
    {
        $methodDefinition = $this->specs['paths'][$path][$httpMethod];

        echo "{$methodDefinition['operationId']}...";

        $returnType = $this->getReturnType($methodDefinition['responses']);

        if ($returnType === null) {
            echo "unsupported returnType\n";
            return;
        }

        $methodName = $this->getMethodName($methodDefinition['operationId']);
        $arguments = $this->extractArguments($methodDefinition['parameters'] ?? []);

        $nullableReturnType = false;
        if (isset($methodDefinition['responses']['404'])) {
            $nullableReturnType = true;
            if (! isset($returnType['property'])) {
                $returnType['doc'] = $returnType['doc'] . '|null';
                $returnType['php'] = '?' . $returnType['php'];
            }
        }

        $argumentsList = $this->getArgumentsList($arguments);

        $code[] = '';
        $code[] = '    /**';
        $code[] = $this->wrapComment($methodDefinition['description'], '     * ');
        $this->addParamsPhpDoc($arguments, $code);
        $code[] = sprintf('     * @return %s', $returnType['doc']);
        $code[] = '     * @throws Exception\ConnectException when an error occurred in the HTTP connection.';
        $code[] = '     * @throws Exception\ResponseException when an unexpected response was received.';
        $code[] = '     * @throws Exception\UnauthorizedException when the request was unauthorized.';
        $code[] = '     * @throws Exception\RateLimitException when the throttling limit has been reached for the API user.';
        $code[] = '     * @throws Exception\Exception when something unexpected went wrong.';
        $code[] = '     */';
        $code[] = sprintf('    public function %s(%s): %s', $methodName, $argumentsList, $returnType['php']);
        $code[] = '    {';
        $code[] = sprintf('        $url = "%s";', $this->getUrl($path, $arguments));

        $options = '[]';
        $code[] = '        $options = [';
        $this->addQueryParams($arguments, $code);
        $this->addBodyParam($arguments, $code);
        $code[] = sprintf('            \'produces\' => \'%s\',', $methodDefinition['produces'][0]);
        $code[] = '        ];';
        $options = '$options';

        $this->addResponseTypes($methodDefinition['responses'], $code);

        $code[] = '';
        if (isset($returnType['property']) && $nullableReturnType) {
            $code[] = sprintf(
                '        $result = $this->request(\'%s\', $url, %s, $responseTypes);',
                strtoupper($httpMethod),
                $options
            );
            $code[] = sprintf(
                '        return $result === null ? [] : $result->%s;',
                $returnType['property']
            );
        } elseif (isset($returnType['property'])) {
            $code[] = sprintf(
                '        return $this->request(\'%s\', $url, %s, $responseTypes)->%s;',
                strtoupper($httpMethod),
                $options,
                $returnType['property']
            );
        } else {
            $code[] = sprintf(
                '        return $this->request(\'%s\', $url, %s, $responseTypes);',
                strtoupper($httpMethod),
                $options
            );
        }

        $code[] = '    }';

        echo "ok\n";
        //print_r($methodDefinition);
    }

    protected function getType(string $ref): string
    {
        //strip #/definitions/
        $type = substr($ref, strrpos($ref, '/') + 1);

        // There are some weird types like 'delivery windows for inbound shipments.', uppercase and concat
        $type = str_replace(['.', ','], '', $type);
        $words = explode(' ', $type);
        $words = array_map(function ($word) {
            return ucfirst($word);
        }, $words);
        $type = implode('', $words);

        // Classname 'Return' is not allowed in php <= 7
        if ($type == 'Return') {
            $type = 'ReturnObject';
        }

        return $type;
    }

    protected function getClientNamespace(): string
    {
        return substr(__NAMESPACE__, 0, strrpos(__NAMESPACE__, '\\'));
    }

    protected function getMethodName(string $operationId): string
    {
        $methodName = $this->kebabCaseToCamelCase($operationId);
        if (isset(static::$overrideMethodNames[$methodName])) {
            return static::$overrideMethodNames[$methodName];
        }
        return $methodName;
    }

    protected function addParamsPhpDoc(array $arguments, array &$code): void
    {
        // TODO break at 120 chars
        foreach ($arguments as $argument) {
            if (empty($argument['description'])) {
                $code[] = sprintf(
                    '     * @param %s $%s',
                    $argument['doc'],
                    $argument['name']
                );
            } else {
                $code[] = $this->wrapComment(sprintf(
                    '@param %s $%s %s',
                    $argument['doc'],
                    $argument['name'],
                    $argument['description']
                ), '     * ');
            }
        }
    }

    protected function kebabCaseToCamelCase(string $name): string
    {
        // Fix for bug in specs where name contains spaces (e.g. 'get packing list')
        $name = str_replace(' ', '-', $name);

        $nameElems = explode('-', $name);
        for ($i=1; $i<count($nameElems); $i++) {
            $nameElems[$i] = ucfirst($nameElems[$i]);
        }
        return implode('', $nameElems);
    }

    protected function getUrl(string $path, array $arguments): string
    {
        $url = substr($path, strlen('/retailer/'));

        foreach ($arguments as $argument) {
            if ($argument['in'] != 'path') {
                continue;
            }

            $url = str_replace(
                '{' . $argument['paramName'] . '}',
                '${' . $argument['name'] . '}',
                $url
            );
        }

        return $url;
    }

    protected function extractArguments(array $parameters): array
    {
        $argsWithoutDefault = [];
        $argsWithDefault = [];

        foreach ($parameters as $parameter) {
            $argument = [
                'default' => null,
                'description' => $parameter['description'] ?? null,
                'in' => $parameter['in'],
                'paramName' => null,
                'required' => $parameter['required']
            ];

            if ($parameter['in'] == 'body') {
                //strip #/definitions/
                $ref = $parameter['schema']['$ref'];
                $type = $this->getType($ref);
                $apiType = substr($ref, strrpos($ref, '/') + 1);

                // extract property if it's a model that wraps an array
                $refDefinition = $this->specs['definitions'][$apiType];
                if (count($refDefinition['properties']) == 1) {
                    $property = array_keys($refDefinition['properties'])[0];
                    $propDefinition = $refDefinition['properties'][$property];

                    if (isset($propDefinition['type']) && $propDefinition['type'] == 'array') {
                        $itemsType = $this->getType($propDefinition['items']['$ref']);
                        $argument['doc'] = 'Model\\' . $itemsType . '[]';
                        $argument['php'] = 'array';
                    } elseif (isset($propDefinition['type'])) {
                        $wrappingType = static::$paramTypeMapping[$propDefinition['type']];
                        $argument['doc'] = $wrappingType;
                        $argument['php'] = $wrappingType;
                    } else {
                        $wrappingType = $this->getType($propDefinition['$ref']);
                        $argument['doc'] = 'Model\\' . $wrappingType;
                        $argument['php'] = 'Model\\' . $wrappingType;
                    }
                    $argument['property'] = $property;
                    $argument['name'] = $property;
                    $argument['wrapperPhp'] = 'Model\\' . $type;
                }

                if (! isset($argument['property'])) {
                    $argument['php'] = 'Model\\' . $type;
                    $argument['doc'] = $argument['php'];
                    $argument['name'] = lcfirst($type);
                }
            } else {
                $argument['php'] = static::$paramTypeMapping[$parameter['type']];
                $argument['doc'] = $argument['php'];
                $argument['name'] = $this->kebabCaseToCamelCase($parameter['name']);
                $argument['paramName'] = $parameter['name'];
                if (isset($parameter['default'])) {
                    if ($parameter['type'] == 'string') {
                        $defaultValue = str_replace(['\''], ['\\\''], $parameter['default']);
                        $argument['default'] = sprintf('\'%s\'', $defaultValue);
                    } else {
                        $argument['default'] = $parameter['default'];
                    }
                }
            }


            // body arguments are always required, even though specs claim not
            if (! $argument['required'] && $argument['in'] != 'body') {
                if ($argument['php'] == 'array') {
                    $argument['default'] = '[]';
                } else {
                    $argument['php'] = '?' . $argument['php'];
                    $argument['doc'] = $argument['doc'] . '|null';
                    if ($argument['default'] === null) {
                        $argument['default'] = 'null';
                    }
                }
            }

            if ($argument['default'] !== null) {
                $argsWithDefault[] = $argument;
            } else {
                $argsWithoutDefault[] = $argument;
            }
        }

        return array_merge($argsWithoutDefault, $argsWithDefault);
    }

    protected function getArgumentsList(array $arguments): string
    {
        $argumentsList = [];

        foreach ($arguments as $argument) {
            if ($argument['default'] !== null) {
                $argumentsList[] = sprintf('%s $%s = %s', $argument['php'], $argument['name'], $this->argumentValueToString($argument['default']));
            } else {
                $argumentsList[] = sprintf('%s $%s', $argument['php'], $argument['name']);
            }
        }

        return implode(', ', $argumentsList);
    }

    protected function argumentValueToString($argument): string
    {
        if ($argument === true) {
            return 'true';
        } elseif ($argument === false) {
            return 'false';
        }

        return $argument;
    }

    protected function addQueryParams(array $arguments, array &$code): void
    {
        $amount = array_reduce($arguments, function ($amount, $argument) {
            return $argument['in'] == 'query' ? $amount+1 : $amount;
        });

        if ($amount == 0) {
            return;
        }

        $code[] = '            \'query\' => [';

        foreach ($arguments as $argument) {
            if ($argument['in'] != 'query') {
                continue;
            }
            $code[] = sprintf('                \'%s\' => $%s,', $argument['paramName'], $argument['name']);
        }
        $code[] = '            ],';
    }

    protected function addBodyParam(array $arguments, array &$code): void
    {
        foreach ($arguments as $argument) {
            if ($argument['in'] != 'body') {
                continue;
            }

            if (isset($argument['wrapperPhp'])) {
                $code[] = sprintf(
                    '            \'body\' => %s::constructFromArray([\'%s\' => $%s]),',
                    $argument['wrapperPhp'],
                    $argument['property'],
                    $argument['name']
                );
            } else {
                $code[] = sprintf('            \'body\' => $%s,', $argument['name']);
            }


            return;
        }
    }

    protected function addResponseTypes(array $responses, array &$code): void
    {
        $code[] = '        $responseTypes = [';
        foreach ($responses as $httpStatus => $response) {
            $type = null;
            if (in_array($httpStatus, ['200', '202'])) {
                if (! isset($response['schema'])) {
                    // There are 2 methods that return a csv, but have no response type defined
                    $type = '\'string\'';
                } elseif (isset($response['schema']['$ref'])) {
                    $type = 'Model\\' . $this->getType($response['schema']['$ref']) . '::class';
                } else {
                    $type = '\'string\'';
                }
            } elseif ($httpStatus == '404') {
                $type = '\'null\'';
            }
            if ($type !== null) {
                $code[] = sprintf('            \'%s\' => %s,', $httpStatus, $type);
            }
        }
        $code[] = '        ];';
    }

    protected function getReturnType(array $responses): array
    {
        $response = $responses['200'] ?? $responses['202'] ?? null;
        if ($response === null) {
            throw new \Exception('Could not fit responseType');
        }

        if (! isset($response['schema'])) {
            // There are 2 methods that return a csv, but have no response type defined
            return ['doc' => 'string', 'php' => 'string'];
        } elseif (isset($response['schema']['$ref'])) {
            //strip #/definitions/
            $ref = $response['schema']['$ref'];
            $apiType = substr($ref, strrpos($ref, '/') + 1);

            // extract property if it's a model that wraps an array
            $refDefinition = $this->specs['definitions'][$apiType];
            if (count($refDefinition['properties']) == 1) {
                $property = array_keys($refDefinition['properties'])[0];
                if (isset($refDefinition['properties'][$property]['type'], $refDefinition['properties'][$property]['items']['$ref']) && $refDefinition['properties'][$property]['type'] == 'array') {
                    return [
                        'doc' => 'Model\\' . $this->getType(
                            $refDefinition['properties'][$property]['items']['$ref']
                        ) . '[]',
                        'php' => 'array',
                        'property' => $property
                    ];
                }
            }

            $type = 'Model\\' . $this->getType($ref);
            return ['doc' => $type, 'php' => $type];
        } else {
            // currently only array is support

            if ($response['schema']['type'] != 'array' || $response['schema']['items']['format'] != 'byte') {
                throw new \Exception("Only Models and raw bytes are supported as response type");
            }
            return ['doc' => 'string', 'php' => 'string', ''];
        }
    }

    protected function wrapComment(string $comment, string $linePrefix, int $maxLength = 120): string
    {
        $wordWrapped = wordwrap(strip_tags($comment), $maxLength - strlen($linePrefix));
        return $linePrefix . trim(str_replace("\n", "\n{$linePrefix}", $wordWrapped));
    }
}
