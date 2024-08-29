<?php
declare(strict_types=1);

namespace HPlus\Swagger\Swagger;

use Exception;
use HPlus\Route\Annotation\ApiController;
use HPlus\Route\Annotation\AdminController;
use HPlus\Route\Annotation\Mapping;
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
use Hyperf\Collection\Arr;
use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Di\ReflectionManager;
use HPlus\Validate\Annotations\RequestValidation;
use Hyperf\Stringable\Str;

class SwaggerJson
{
    private $config;
    private $swagger;
    private $server;

    public function __construct(string $server)
    {
        $container = ApplicationContext::getContainer();
        $this->config = $container->get(ConfigInterface::class);
        $this->swagger = $this->config->get('swagger.swagger', []);
        $this->server = $server;
    }

    public function addPath(string $className, string $methodName, string $path): void
    {
        $classAnnotation = ApiAnnotation::classMetadata($className);
        $controllerAnnotation = $classAnnotation[ApiController::class] ?? $classAnnotation[AdminController::class] ?? null;

        if (!$controllerAnnotation) {
            return;
        }

        $serverAnnotation = $classAnnotation[ApiServer::class] ?? null;
        $versionAnnotation = $classAnnotation[ApiVersion::class] ?? null;
        $definitionsAnnotation = $classAnnotation[ApiDefinitions::class] ?? null;
        $definitionAnnotation = $classAnnotation[ApiDefinition::class] ?? null;

        $bindServer = $serverAnnotation->name ?? $this->config->get('server.servers.0.name');
        $serverNames = array_column($this->config->get('server.servers', []), 'name');

        if (!in_array($bindServer, $serverNames, true)) {
            throw new Exception(sprintf('The bind ApiServer name [%s] not found, defined in %s!', $bindServer, $className));
        }

        if ($bindServer !== $this->server) {
            return;
        }

        $methodAnnotations = ApiAnnotation::methodMetadata($className, $methodName);
        if (!$methodAnnotations) {
            return;
        }

        $params = [];
        $responses = [];
        $mappings = [];
        $rules = [];
        $consumes = 'application/x-www-form-urlencoded';

        foreach ($methodAnnotations as $annotation) {
            if ($annotation instanceof RequestValidation) {
                $rules = array_merge($rules, $this->getValidationRules($annotation));
                $consumes = $this->getConsumes($annotation->dateType);

                if ($annotation->dateType === 'json') {
                    $params[] = $this->createBodyParam($annotation, 'body');
                } elseif ($annotation->dateType === 'form') {
                    $params = array_merge($params, $this->createFormParams($annotation));
                }

                continue;
            }

            if ($annotation instanceof Mapping) {
                $mappings[] = $annotation;
            }

            if ($annotation instanceof Param || $annotation instanceof Query) {
                $params[] = $annotation;
            }

            if ($annotation instanceof ApiResponse) {
                $responses[] = $annotation;
            }

            if ($annotation instanceof FormData) {
                $consumes = 'application/x-www-form-urlencoded';
            }

            if ($annotation instanceof Body) {
                $consumes = 'application/json';
            }
        }

        $this->processDefinitions($definitionsAnnotation);
        $this->processDefinitions([$definitionAnnotation]);

        $tag = $controllerAnnotation->tag ?: $className;
        $this->swagger['tags'][$tag] = [
            'name' => $tag,
            'description' => $controllerAnnotation->description,
        ];

        $path = $this->normalizePath($path, $versionAnnotation);
        foreach ($mappings as $mapping) {
            $method = strtolower($mapping->methods[0] ?? '');
            $this->swagger['paths'][$path][$method] = $this->createPathItem($mapping, $params, $responses, $tag, $path, $method, $consumes);
        }
    }

    private function getConsumes(string $type): string
    {
        return match ($type) {
            'json' => 'application/json',
            'xml' => 'application/xml',
            default => 'application/x-www-form-urlencoded',
        };
    }

    private function getValidationRules(RequestValidation $validation): array
    {
        if ($validation->validate && class_exists($validation->validate)) {
            $validationClass = ReflectionManager::reflectClass($validation->validate)->getDefaultProperties();
            $rules = $validationClass['scene'][$validation->scene] ?? [];
            $fields = $validationClass['field'] ?? [];

            $newRules = [];
            foreach ($rules as $key => $rule) {
                if (is_numeric($key)) {
                    $key = $rule;
                    $rule = $validationClass['rule'][$rule] ?? '';
                }
                $newRules[$key] = $rule;
            }

            return $newRules;
        }

        return $validation->rules;
    }


    private function processDefinitions(?array $definitions): void
    {
        if (!$definitions) {
            return;
        }

        if ($definitions instanceof ApiDefinitions) {
            $definitions = $definitions->definitions;
        }
        foreach ($definitions as $definition) {
            if (!$definition) continue;
            $this->swagger['definitions'][$definition->name] = $this->formatDefinitionProperties($definition->properties);
        }
    }

    private function formatDefinitionProperties(array $properties): array
    {
        $formattedProps = [];

        foreach ($properties as $propKey => $prop) {
            $propKeyArr = explode('|', $propKey);
            $propName = $propKeyArr[0];
            $formattedProps[$propName] = $this->formatProperty($prop, $propKeyArr[1] ?? '');
        }

        return ['properties' => $formattedProps];
    }

    private function formatProperty($prop, string $description): array
    {
        $property = is_array($prop) ? $prop : ['default' => $prop];
        $property['description'] = $description;

        if (isset($property['default'])) {
            $property['type'] = is_numeric($property['default']) ? 'integer' : 'string';
        }

        if (isset($property['$ref'])) {
            $property['$ref'] = '#/definitions/' . $property['$ref'];
        }

        return $property;
    }

    private function normalizePath(string $path, ?ApiVersion $versionAnnotation): string
    {
        $path = '/' . ltrim($path, '/');
        if ($versionAnnotation && $versionAnnotation->version) {
            $path = '/' . $versionAnnotation->version . $path;
        }
        return str_replace("/_self_path", "", $path);
    }

    private function createPathItem($mapping, array $params, array $responses, string $tag, string $path, string $method, string $consumes): array
    {
        return [
            'tags' => [$tag],
            'summary' => $mapping->summary ?? '',
            'description' => $mapping->description ?? '',
            'operationId' => $this->generateOperationId($path, $mapping->methods[0] ?? ''),
            'parameters' => $this->makeParameters($params, $path, $method),
            'produces' => [$consumes],
            'responses' => $this->makeResponses($responses, $path, $method),
            'consumes' => $consumes !== 'application/x-www-form-urlencoded' ? [$consumes] : [],
            'security' => $this->generateSecurity($mapping),
        ];
    }

    private function generateOperationId(string $path, string $method): string
    {
        return implode('', array_map('ucfirst', explode('/', $path))) . $method;
    }

    private function generateSecurity($mapping): array
    {
        $security = [];

        if ($mapping && property_exists($mapping, 'security') && $mapping->security && isset($this->swagger['securityDefinitions'])) {
            foreach ($this->swagger['securityDefinitions'] as $key => $val) {
                $security[] = [$key => $val['petstore_auth'] ?? []];
            }
        }

        return $security;
    }

    private function createBodyParam(RequestValidation $annotation, string $name): Body
    {
        $param = new Body($name);
        $param->rules = $this->getValidationRules($annotation);
        $param->key = $name;
        return $param;
    }

    private function createFormParams(RequestValidation $annotation): array
    {
        $params = [];
        foreach ($this->getValidationRules($annotation) as $key => $rule) {
            $param = new FormData("");
            [$key, $name] = explode('|', $key);
            $param->key = $key;
            $param->name = $name;
            $param->rule = $rule;
            $param->required = str_contains($rule, 'required');
            $params[] = $param;
        }
        return $params;
    }

    private function initModel(): void
    {
        $arraySchema = [
            'type' => 'array',
            'required' => [],
            'items' => ['type' => 'string'],
        ];

        $objectSchema = [
            'type' => 'object',
            'required' => [],
            'items' => ['type' => 'string'],
        ];

        $this->swagger['definitions']['ModelArray'] = $arraySchema;
        $this->swagger['definitions']['ModelObject'] = $objectSchema;
    }

    public function makeParameters(array $params, string $path, string $method): array
    {
        $this->initModel();
        $method = ucfirst($method);
        $path = $this->getPath($path);
        $parameters = [];

        foreach ($params as $item) {
            if ($item instanceof Body) {
                $parameters[$item->key] = $this->createBodyParameter($item, $method, $path);
            } elseif ($item instanceof Query) {
                $parameters = array_merge($parameters, $this->createQueryParameters($item));
            } else {
                $parameters[$item->key] = $this->createDefaultParameter($item);
            }
        }

        return array_values($parameters);
    }

    private function createBodyParameter(Body $item, string $method, string $path): array
    {
        $parameter = $this->getDefaultParameter($item->key, $item->in, $item->key, $item->required);
        $modelName = $method . implode('', array_map('ucfirst', explode('/', $path)));
        $this->rules2schema($modelName, $item->rules);
        $parameter['schema']['$ref'] = '#/definitions/' . $modelName;
        return $parameter;
    }


    private function createQueryParameters(Query $item): array
    {
        $parameters = [];
        foreach ($item->rules as $keyNameLabel => $rule) {
            $fieldNameLabel = explode('|', is_numeric($keyNameLabel) ? $rule : $keyNameLabel);
            $type = $this->getTypeByRule($rule);
            $keyName = $fieldNameLabel[0];
            $keyLabel = $fieldNameLabel[1] ?? $keyName;
            $parameters[$keyName] = $this->getDefaultParameter($keyName, $item->in, $keyLabel, str_contains($rule, 'required'));
            $parameters[$keyName]['type'] = $type;
            $parameters[$keyName]['default'] = $item->default[$keyName] ?? '';
        }
        return $parameters;
    }

    private function createDefaultParameter($item): array
    {
        $parameter = $this->getDefaultParameter($item->key, $item->in, $item->key, $item->required);
        $parameter['type'] = $this->getTypeByRule($item->rule);
        $parameter['default'] = $item->default ?? '';
        return $parameter;
    }

    public function makeResponses(array $responses, string $path, string $method): array
    {
        $resp = [];

        foreach ($responses as $item) {
            $resp[$item->code] = [
                'description' => $item->description ?? '',
                'schema' => $this->createResponseSchema($item, $path, $method),
            ];
        }

        return $resp;
    }

    private function createResponseSchema(ApiResponse $item, string $path, string $method): array
    {
        if ($item->schema) {
            if (isset($item->schema['$ref'])) {
                return ['$ref' => '#/definitions/' . $item->schema['$ref']];
            }

            if (isset($item->schema[0]) && !is_array($item->schema[0])) {
                return [
                    'type' => 'array',
                    'items' => ['type' => is_int($item->schema[0]) ? 'integer' : 'string'],
                ];
            }

            $modelName = $this->generateModelName($path, $method, $item->code);
            return $this->createSchemaDefinition($item->schema, $modelName);
        }

        return [];
    }

    private function generateModelName(string $path, string $method, string $code): string
    {
        return implode('', array_map('ucfirst', explode('/', $path))) . ucfirst($method) . 'Response' . $code;
    }

    private function createSchemaDefinition($schema, string $modelName): array
    {
        $definition = [];
        $schemaContent = isset($schema[0]) && is_array($schema[0]) ? $schema[0] : $schema;

        foreach ($schemaContent as $keyString => $val) {
            $keyArray = explode('|', $keyString);
            $key = $keyArray[0];
            $_key = str_replace('_', '', $key);
            $property = ['type' => gettype($val), 'description' => $keyArray[1] ?? ''];

            if (is_array($val)) {
                $definitionName = $modelName . ucfirst($_key);
                $property = $this->handleArrayProperty($val, $definitionName, $property);
            } else {
                $property['default'] = $val;
            }

            $definition['properties'][$key] = $property;
        }

        $this->swagger['definitions'][$modelName] = $definition;
        return ['$ref' => '#/definitions/' . $modelName];
    }

    private function handleArrayProperty(array $val, string $definitionName, array $property): array
    {
        if ($property['type'] === 'array' && isset($val[0])) {
            if (is_array($val[0])) {
                $property['items']['$ref'] = $this->createSchemaDefinition($val[0], $definitionName);
            } else {
                $property['items']['type'] = gettype($val[0]);
            }
        } else {
            unset($property['type']);
            $property['$ref'] = $this->createSchemaDefinition($val, $definitionName);
        }

        return $property;
    }

    private function rules2schema(string $name, array $rules): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [],
        ];

        foreach ($rules as $field => $rule) {
            $fieldNameLabel = explode('|', $field);
            $fieldName = $fieldNameLabel[0];
            $description = $fieldNameLabel[1] ?? '';

            // 解析多维数组路径
            $fieldPath = explode('.', $fieldName);
            $currentSchema = &$schema['properties'];

            foreach ($fieldPath as $key) {
                if (!isset($currentSchema[$key])) {
                    // 判断是否是数组字段
                    $currentSchema[$key] = [
                        'type' => count($fieldPath) > 1 ? 'object' : $this->getTypeByRule($rule),
                        'description' => count($fieldPath) === 1 ? $description : null,
                    ];

                    // 如果是最后一个路径节点且类型是 array，则设置 items
                    if ($currentSchema[$key]['type'] === 'array') {
                        $currentSchema[$key]['items'] = ['type' => 'string']; // 默认数组元素类型为字符串
                    }
                }

                // 针对最后一个路径节点的处理
                if ($key === end($fieldPath)) {
                    $currentSchema[$key]['type'] = $this->getTypeByRule($rule);
                    $currentSchema[$key]['description'] = $description;

                    // 如果是数组，设置 items 类型
                    if ($currentSchema[$key]['type'] === 'array') {
                        $currentSchema[$key]['items'] = ['type' => 'string']; // 默认数组元素类型为字符串
                    }
                } else {
                    // 仅在非叶子节点情况下，设置 properties 层级
                    if (!isset($currentSchema[$key]['properties'])) {
                        $currentSchema[$key]['properties'] = [];
                    }
                    $currentSchema = &$currentSchema[$key]['properties'];
                }
            }
        }

        $this->swagger['definitions'][$name] = $schema;
    }

    private function getTypeByRule(string $rule): string
    {
        $default = explode('|', preg_replace('/\[.*\]/', '', $rule));

        if (in_array('int', $default) || in_array('integer', $default)) {
            return 'integer';
        }

        if (in_array('numeric', $default) || in_array('float', $default)) {
            return 'number';
        }

        if (in_array('boolean', $default) || in_array('bool', $default)) {
            return 'boolean';
        }

        if (in_array('array', $default)) {
            return 'array';
        }

        if (in_array('object', $default)) {
            return 'object';
        }

        return 'string';
    }




    private function getPath(string $path): string
    {
        return preg_replace('/\{([^{}]+):[^{}]+\}/', '{$1}', $path);
    }

    private function getDefaultParameter(string $key, string $in, string $name, bool $required = false, ?string $description = null): array
    {
        return [
            'in' => $in,
            'name' => $key,
            'description' => $description ?: $name,
            'required' => $required,
        ];
    }

    public function save(): void
    {
        $this->swagger['tags'] = array_values($this->swagger['tags'] ?? []);
        $outputFile = $this->config->get('swagger.output_file');

        if (!$outputFile) {
            throw new Exception('/config/autoload/swagger.php need set output_file');
        }

        $outputFile = str_replace('{server}', $this->server, $outputFile);
        file_put_contents($outputFile, json_encode($this->swagger, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        print_r('Generate swagger.json success!' . PHP_EOL);
    }
}
