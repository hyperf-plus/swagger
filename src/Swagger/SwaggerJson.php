<?php
declare(strict_types=1);

namespace HPlus\Swagger\Swagger;

use Doctrine\Common\Annotations\AnnotationReader;
use HPlus\Route\Annotation\ApiController;
use HPlus\Route\Annotation\AdminController;
use HPlus\Route\Annotation\Query;
use HPlus\Swagger\Annotation\ApiDefinition;
use HPlus\Swagger\Annotation\ApiDefinitions;
use HPlus\Route\Annotation\ApiResponse;
use HPlus\Swagger\Annotation\ApiServer;
use HPlus\Swagger\Annotation\ApiVersion;
use HPlus\Route\Annotation\Body;
use HPlus\Route\Annotation\FormData;
use HPlus\Route\Annotation\Param;
use HPlus\Swagger\ApiAnnotation;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Di\ReflectionManager;
use Hyperf\HttpServer\Annotation\Mapping;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Utils\Arr;
use Hyperf\Utils\Str;
use HPlus\Validate\Annotations\RequestValidation;

class SwaggerJson
{
    public $config;

    public $swagger;

    public $server;

    public function __construct($server)
    {
        $container = ApplicationContext::getContainer();
        $this->config = $container->get(ConfigInterface::class);
        $this->swagger = $this->config->get('swagger.swagger');
        $this->server = $server;
    }

    public function addPath($className, $methodName, $path)
    {
        $ignores = $this->config->get('annotations.scan.ignore_annotations', []);
        foreach ($ignores as $ignore) {
            AnnotationReader::addGlobalIgnoredName($ignore);
        }
        $classAnnotation = ApiAnnotation::classMetadata($className);
        $controlerAnno = $classAnnotation[ApiController::class] ?? $classAnnotation[AdminController::class] ?? null;
        $serverAnno = $classAnnotation[ApiServer::class] ?? null;
        $versionAnno = $classAnnotation[ApiVersion::class] ?? null;
        $definitionsAnno = $classAnnotation[ApiDefinitions::class] ?? null;
        $definitionAnno = $classAnnotation[ApiDefinition::class] ?? null;
        $bindServer = $serverAnno ? $serverAnno->name : $this->config->get('server.servers.0.name');
        $servers = $this->config->get('server.servers');
        $servers_name = array_column($servers, 'name');
        if (!in_array($bindServer, $servers_name)) {
            throw new \Exception(sprintf('The bind ApiServer name [%s] not found, defined in %s!', $bindServer, $className));
        }

        if ($bindServer !== $this->server) {
            return;
        }
        $methodAnnotations = ApiAnnotation::methodMetadata($className, $methodName);
        if (!$controlerAnno || !$methodAnnotations) {
            return;
        }
        $params = [];
        $responses = [];
        /** @var \HPlus\Route\Annotation\GetApi $mapping */
        $mapping = null;
        $consumes = null;
        $rules = [];
        $consumes = 'application/x-www-form-urlencoded';
        foreach ($methodAnnotations as $option) {
            if ($option instanceof RequestValidation) {
                $rules = array_merge($rules, $this->getValidateRule($option));
                if ($option->dateType == 'json') {
                    $param = new Body();
                    $param->rules = $this->getValidateRule($option);
                    $param->name = "body";
                    $param->key = "body";
                    $params[] = $param;
                    unset($param);
                }
                if ($option->dateType == 'form') {
                    foreach ($this->getValidateRule($option) as $key => $item) {
                        $param = new FormData();
                        list($key, $name) = explode('|', $key);
                        $param->key = $key;
                        $param->name = $name;
                        $param->rule = $item;
                        $param->required = in_array('required', explode('|', $item));
                        $params[] = $param;
                        unset($param);
                    }
                }
                $consumes = $this->getConsumes($option->dateType);
                continue;
            }
            if ($option instanceof Mapping) {
                $mapping = $option;
            }
            if ($option instanceof Param) {
                $params[] = $option;
            }
            if ($option instanceof ApiResponse) {
                $responses[] = $option;
            }
            if ($option instanceof FormData) {
                $consumes = 'application/x-www-form-urlencoded';
            }
            if ($option instanceof Body) {
                $consumes = 'application/json';
            }
        }

        $this->makeDefinition($definitionsAnno);
        $definitionAnno && $this->makeDefinition([$definitionAnno]);

        $tag = $controlerAnno->tag ?: $className;
        $this->swagger['tags'][$tag] = [
            'name' => $tag,
            'description' => $controlerAnno->description,
        ];
        if ($path[0] !== '/') {
            $path = '/' . $path;
        }
        if ($versionAnno && $versionAnno->version) {
            $path = '/' . $versionAnno->version . $path;
        }
        $path = str_replace("/_self_path", "", $path);
        $path = $this->getPath($path);
        $method = strtolower($mapping->methods[0]);
        $this->swagger['paths'][$path][$method] = [
            'tags' => [$tag],
            'summary' => $mapping->summary ?? '',
            'description' => $mapping->description ?? '',
            'operationId' => implode('', array_map('ucfirst', explode('/', $path))) . $mapping->methods[0],
            'parameters' => $this->makeParameters($params, $path, $method),
            'produces' => [
                $consumes
            ],
            'responses' => $this->makeResponses($responses, $path, $method),
        ];
        if ($consumes !== null) {
            $this->swagger['paths'][$path][$method]['consumes'] = [$consumes];
        }
        if (property_exists($mapping, 'security') && $mapping->security && isset($this->swagger['securityDefinitions'])) {
            foreach ($this->swagger['securityDefinitions'] as $key => $val) {
                $this->swagger['paths'][$path][$method]['security'][] = [$key => $val['petstore_auth'] ?? []];
            }
        }
    }

    private function getConsumes($type = 'json')
    {
        switch ($type) {
            case 'json':
                return 'application/json';
            case 'xml':
                return 'application/xml';
            default:
                return 'application/x-www-form-urlencoded';
        }
    }

    private function getValidateRule(RequestValidation $validation)
    {
        if (class_exists($validation->validate)) {
            $rolesModel = ReflectionManager::reflectClass($validation->validate)->getDefaultProperties();
            $rules = $rolesModel['scene'][$validation->scene] ?? [];
            $fields = $rolesModel['field'] ?? [];
            $newRules = [];
            foreach ($rules as $key => $rule) {
                if (is_numeric($key)) {
                    $key = $rule;
                    $rule = $rolesModel['rule'][$rule] ?? '';
                }
                if (isset($fields[$key])) $key = $key . "|" . $fields[$key];
                $newRules[$key] = $rule;
            }
            return $newRules;
        }
        return $validation->rules;
    }

    private function initModel()
    {
        $arraySchema = [
            'type' => 'array',
            'required' => [],
            'items' => [
                'type' => 'string',
            ],
        ];
        $objectSchema = [
            'type' => 'object',
            'required' => [],
            'items' => [
                'type' => 'string',
            ],
        ];

        $this->swagger['definitions']['ModelArray'] = $arraySchema;
        $this->swagger['definitions']['ModelObject'] = $objectSchema;
    }

    private function rules2schema($name, $rules)
    {
        $schema = [
            'type' => 'object',
            'properties' => [],
        ];
        foreach ($rules as $field => $rule) {
            $type = null;
            $property = [];

            $fieldNameLabel = explode('|', $field);
            $fieldName = $fieldNameLabel[0];
            if (is_array($rule)) {
                $deepModelName = $name . ucfirst($fieldName);
                if (Arr::isAssoc($rule)) {
                    $this->rules2schema($deepModelName, $rule);
                    $property['$ref'] = '#/definitions/' . $deepModelName;
                } else {
                    $type = 'array';
                    $this->rules2schema($deepModelName, $rule[0]);
                    $property['items']['$ref'] = '#/definitions/' . $deepModelName;
                }
            } else {
                $type = $this->getTypeByRule($rule);
                if ($type === 'string') {
                    in_array('required', explode('|', $rule)) && $schema['required'][] = $fieldName;
                }
                if ($type == 'array') {
                    $property['$ref'] = '#/definitions/ModelArray';
                }
                if ($type == 'object') {
                    $property['$ref'] = '#/definitions/ModelObject';
                }
            }
            if ($type !== null) {
                $property['type'] = $type;
            }
            $property['description'] = $fieldNameLabel[1] ?? '';

            $schema['properties'][$fieldName] = $property;
        }

        $this->swagger['definitions'][$name] = $schema;
    }

    public function getTypeByRule($rule)
    {
        $default = explode('|', preg_replace('/\[.*\]/', '', $rule));
        if (array_intersect($default, ['int', 'lt', 'gt', 'ge'])) {
            return 'integer';
        }
        if (array_intersect($default, ['numeric'])) {
            return 'number';
        }
        if (array_intersect($default, ['array'])) {
            return 'array';
        }
        if (array_intersect($default, ['object'])) {
            return 'object';
        }
        if (array_intersect($default, ['file'])) {
            return 'file';
        }
        return 'string';
    }

    private function getPath($path)
    {
        $urls = explode(':', $path);
        $path = $urls[0];
        if (count($urls) > 1) {
            $path .= '}';
        }
        return $path;
    }

    public function makeParameters($params, $path, $method)
    {
        $this->initModel();
        $method = ucfirst($method);
        $path = $this->getPath($path);
        $parameters = [];
        /** @var Query $item */
        foreach ($params as $item) {
            if ($item->rule !== null && in_array('array', explode('|', $item->rule))) {
                $item->name .= '[]';
            }
            $parameters[$item->key] = [
                'in' => $item->in,
                'name' => $item->key,
                'description' => empty($item->description) ? $item->name : $item->description,
                'required' => $item->required,
            ];
            if ($item instanceof Body) {
                $modelName = $method . implode('', array_map('ucfirst', explode('/', $path)));
                $this->rules2schema($modelName, $item->rules);
                $parameters[$item->key]['schema']['$ref'] = '#/definitions/' . $modelName;
            } else {
                $type = $this->getTypeByRule($item->rule);
                $parameters[$item->key]['type'] = $type;
                $parameters[$item->key]['default'] = $item->default;
            }
        }
        return array_values($parameters);
    }

    public function makeResponses($responses, $path, $method)
    {
        $path = $this->getPath($path);
        $resp = [];
        /** @var ApiResponse $item */
        foreach ($responses as $item) {
            $resp[$item->code] = [
                'description' => $item->description ?? '',
            ];
            if ($item->schema) {
                if (isset($item->schema['$ref'])) {
                    $resp[$item->code]['schema']['$ref'] = '#/definitions/' . $item->schema['$ref'];
                    continue;
                }
                // 处理直接返回列表的情况 List<Integer> List<String>
                if (isset($item->schema[0]) && !is_array($item->schema[0])) {
                    $resp[$item->code]['schema']['type'] = 'array';
                    if (is_int($item->schema[0])) {
                        $resp[$item->code]['schema']['items'] = [
                            "type" => 'integer',
                        ];
                    } elseif (is_string($item->schema[0])) {
                        $resp[$item->code]['schema']['items'] = [
                            "type" => 'string',
                        ];
                    }
                    continue;
                }

                $modelName = implode('', array_map('ucfirst', explode('/', $path))) . ucfirst($method) . 'Response' . $item->code;
                $ret = $this->responseSchemaToDefinition($item->schema, $modelName);
                if ($ret) {
                    // 处理List<String, Object>
                    if (isset($item->schema[0]) && is_array($item->schema[0])) {
                        $resp[$item->code]['schema']['type'] = 'array';
                        $resp[$item->code]['schema']['items']['$ref'] = '#/definitions/' . $modelName;
                    } else {
                        $resp[$item->code]['schema']['$ref'] = '#/definitions/' . $modelName;
                    }
                }
            }
        }

        return $resp;
    }

    public function makeDefinition($definitions)
    {
        if (!$definitions) {
            return;
        }
        if ($definitions instanceof ApiDefinitions) {
            $definitions = $definitions->definitions;
        }
        foreach ($definitions as $definition) {
            /** @var $definition ApiDefinition */
            $defName = $definition->name;
            $defProps = $definition->properties;

            $formattedProps = [];

            foreach ($defProps as $propKey => $prop) {
                $propKeyArr = explode('|', $propKey);
                $propName = $propKeyArr[0];
                $propVal = [];
                isset($propKeyArr[1]) && $propVal['description'] = $propKeyArr[1];
                if (is_array($prop)) {
                    if (isset($prop['description']) && is_string($prop['description'])) {
                        $propVal['description'] = $prop['description'];
                    }

                    if (isset($prop['type']) && is_string($prop['type'])) {
                        $propVal['type'] = $prop['type'];
                    }

                    if (isset($prop['default'])) {
                        $propVal['default'] = $prop['default'];
                        !isset($propVal['type']) && $propVal['type'] = is_numeric($propVal['default']) ? 'integer' : 'string';
                    }
                    if (isset($prop['$ref'])) {
                        $propVal['$ref'] = '#/definitions/' . $prop['$ref'];
                    }
                } else {
                    $propVal['default'] = $prop;
                    $propVal['type'] = is_numeric($prop) ? 'integer' : 'string';
                }
                $formattedProps[$propName] = $propVal;
            }
            $this->swagger['definitions'][$defName]['properties'] = $formattedProps;
        }
    }

    public function responseSchemaToDefinition($schema, $modelName, $level = 0)
    {
        if (!$schema) {
            return false;
        }
        $definition = [];

        // 处理 Map<String, String> Map<String, Object> Map<String, List>
        $schemaContent = $schema;
        // 处理 List<Map<String, Object>>
        if (isset($schema[0]) && is_array($schema[0])) {
            $schemaContent = $schema[0];
        }
        foreach ($schemaContent as $keyString => $val) {
            $property = [];
            $property['type'] = gettype($val);
            $keyArray = explode('|', $keyString);
            $key = $keyArray[0];
            $_key = str_replace('_', '', $key);
            $property['description'] = $keyArray[1] ?? '';
            if (is_array($val)) {
                $definitionName = $modelName . ucfirst($_key);
                if ($property['type'] === 'array' && isset($val[0])) {
                    if (is_array($val[0])) {
                        $property['type'] = 'array';
                        $ret = $this->responseSchemaToDefinition($val[0], $definitionName, 1);
                        $property['items']['$ref'] = '#/definitions/' . $definitionName;
                    } else {
                        $property['type'] = 'array';
                        $property['items']['type'] = gettype($val[0]);
                    }
                } else {
                    // definition引用不能有type
                    unset($property['type']);
                    $ret = $this->responseSchemaToDefinition($val, $definitionName, 1);
                    $property['$ref'] = '#/definitions/' . $definitionName;
                }
                if (isset($ret)) {
                    $this->swagger['definitions'][$definitionName] = $ret;
                }
            } else {
                $property['default'] = $val;
            }

            $definition['properties'][$key] = $property;
        }

        if ($level === 0) {
            $this->swagger['definitions'][$modelName] = $definition;
        }

        return $definition;
    }

    public function save()
    {
        $this->swagger['tags'] = array_values($this->swagger['tags'] ?? []);
        $outputFile = $this->config->get('swagger.output_file');
        if (!$outputFile) {
            throw new \Exception('/config/autoload/swagger.php need set output_file');
        }
        $outputFile = str_replace('{server}', $this->server, $outputFile);
        file_put_contents($outputFile, json_encode($this->swagger, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        print_r('Generate swagger.json success!' . PHP_EOL);
    }

    protected function getPrefix(string $className, string $prefix): string
    {
        if (!$prefix) {
            $handledNamespace = Str::replaceFirst('Controller', '', Str::after($className, '\\Controller\\'));
            $handledNamespace = str_replace('\\', '/', $handledNamespace);
            $prefix = Str::snake($handledNamespace);
            $prefix = str_replace('/_', '/', $prefix);
        }
        if ($prefix[0] !== '/') {
            $prefix = '/' . $prefix;
        }
        return $prefix;
    }
}
