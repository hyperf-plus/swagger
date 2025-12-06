<?php

declare(strict_types=1);

namespace HPlus\Swagger;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Di\Annotation\AnnotationCollector;
use HPlus\Route\Annotation\ApiController;
use HPlus\Route\Annotation\ApiResponse;
use HPlus\Route\Annotation\ApiResponseExample;
use HPlus\Route\Annotation\RequestBody;
use HPlus\Route\Annotation\GetApi;
use HPlus\Route\Annotation\PostApi;
use HPlus\Route\Annotation\PutApi;
use HPlus\Route\Annotation\DeleteApi;
use HPlus\Route\Annotation\PatchApi;
use HPlus\Route\Annotation\Mapping;
use HPlus\Swagger\Annotation\ApiDefinition;
use HPlus\Swagger\Annotation\ApiCallback;
use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;

/**
 * OpenAPI 3.1.1 文档构建器
 * 
 * 特性：
 * - 支持 OpenAPI 3.1.1 完整规范
 * - 软依赖 validate 插件（可选）
 * - 支持 FormRequest 类验证器
 * - 内置缓存机制优化性能
 */
class SwaggerBuilder
{
    // 软依赖类名常量
    private const VALIDATE_REQUEST_VALIDATION = 'HPlus\\Validate\\Annotations\\RequestValidation';
    private const VALIDATE_RULE_PARSER = 'HPlus\\Validate\\RuleParser';
    private const HYPERF_FORM_REQUEST = 'Hyperf\\Validation\\Request\\FormRequest';

    // HTTP 方法常量
    private const HTTP_METHODS_NO_BODY = ['GET', 'DELETE', 'HEAD', 'OPTIONS'];

    /** @var array<string, array> 方法注解缓存 */
    private array $annotationCache = [];

    /** @var array<string, array> FormRequest 数据缓存 */
    private array $formRequestCache = [];

    /** @var bool|null validate 插件可用性缓存 */
    private ?bool $validateAvailable = null;

    /** @var bool|null RuleParser 可用性缓存 */
    private ?bool $ruleParserAvailable = null;

    /** @var array|null 构建结果缓存 */
    private ?array $buildCache = null;

    public function __construct(
        private readonly ConfigInterface $config
    ) {}

    /**
     * 构建完整的 OpenAPI 3.1.1 文档
     * 
     * 懒加载 + 缓存：首次访问时构建，后续直接返回缓存
     */
    public function build(): array
    {
        // 返回缓存（如果有）
        if ($this->buildCache !== null) {
            return $this->buildCache;
        }

        $swaggerConfig = $this->config->get('swagger', []);
        
        $openapi = [
            'openapi' => '3.1.1',
            'info' => $this->buildInfo($swaggerConfig),
            'servers' => $swaggerConfig['servers'] ?? $this->getDefaultServers(),
            'paths' => $this->buildPaths(),
            'components' => $this->buildComponents($swaggerConfig),
        ];

        // 添加可选字段
        $optionalFields = ['security', 'tags', 'externalDocs', 'webhooks', 'jsonSchemaDialect'];
        foreach ($optionalFields as $field) {
            if (!empty($swaggerConfig[$field])) {
                $openapi[$field] = $swaggerConfig[$field];
            }
        }

        // 添加扩展字段 (x-*)
        foreach ($swaggerConfig as $key => $value) {
            if (str_starts_with($key, 'x-') && $value !== null) {
                $openapi[$key] = $value;
            }
        }

        // paths 是 OpenAPI 规范的必需字段，即使为空也必须保留
        // 缓存构建结果
        $this->buildCache = array_filter($openapi, static fn($v, $k) => 
            $v !== null && ($v !== [] || $k === 'paths'),
            ARRAY_FILTER_USE_BOTH
        );

        return $this->buildCache;
    }

    /**
     * 清除构建缓存
     * 
     * 如果需要刷新文档，可调用此方法
     */
    public function clearCache(): self
    {
        $this->buildCache = null;
        $this->annotationCache = [];
        $this->formRequestCache = [];
        return $this;
    }

    /**
     * 构建信息对象
     */
    private function buildInfo(array $config): array
    {
        $info = [
            'title' => $config['title'] ?? 'API Documentation',
            'version' => $config['version'] ?? '1.0.0',
        ];

        $optionalFields = ['summary', 'description', 'termsOfService', 'contact', 'license'];
        foreach ($optionalFields as $field) {
            if (!empty($config[$field])) {
                $info[$field] = $config[$field];
            }
        }

        return $info;
    }

    /**
     * 构建路径信息
     */
    private function buildPaths(): array
    {
        if (!class_exists(\HPlus\Route\RouteCollector::class)) {
            return [];
        }

        try {
            $routeCollector = \HPlus\Route\RouteCollector::getInstance();
            $routes = $routeCollector->collectRoutes();
            
            $paths = [];
            foreach ($routes as $route) {
                $operation = $this->buildOperationFromRoute($route);
                
                foreach ($route['methods'] as $httpMethod) {
                    $paths[$route['path']][strtolower($httpMethod)] = $operation;
                }
            }
            
            return $paths;
        } catch (\Throwable $e) {
            error_log("SwaggerBuilder: RouteCollector error - " . $e->getMessage());
            return [];
        }
    }

    /**
     * 从路由信息构建操作
     */
    private function buildOperationFromRoute(array $route): array
    {
        $operation = [
            'summary' => $route['summary'] ?: 'API Operation',
            'description' => $route['description'] ?: '',
            'operationId' => $route['name'],
            'tags' => $route['tags'],
            'deprecated' => $route['deprecated'] ?? false,
        ];

        // 通过反射获取参数和请求体
        if (isset($route['_method']) && $route['_method'] instanceof ReflectionMethod) {
            $method = $route['_method'];
            $httpMethod = $route['methods'][0] ?? 'GET';
            $annotations = $this->getMethodAnnotations($method);
            
            // 构建参数（传入路由路径用于解析路径参数）
            $parameters = $this->buildParameters($method, $httpMethod, $annotations, $route['path'] ?? '');
            if (!empty($parameters)) {
                $operation['parameters'] = $parameters;
            }

            // 构建请求体
            $requestBody = $this->buildRequestBody($method, $httpMethod, $annotations);
            if ($requestBody) {
                $operation['requestBody'] = $requestBody;
            }
            
            // 构建响应
            $operation['responses'] = $this->buildResponses($annotations);
        } else {
            $operation['responses'] = $this->getDefaultResponse();
        }

        // 添加安全要求
        if ($route['security']) {
            $operation['security'] = $this->config->get('swagger.security', []);
        }

        return array_filter($operation, static fn($v) => $v !== null && $v !== []);
    }

    /**
     * 获取方法注解（带缓存）
     */
    private function getMethodAnnotations(ReflectionMethod $method): array
    {
        $cacheKey = $method->getDeclaringClass()->getName() . '::' . $method->getName();
        
        return $this->annotationCache[$cacheKey] ??= AnnotationCollector::getClassMethodAnnotation(
            $method->getDeclaringClass()->getName(),
            $method->getName()
        );
    }

    /**
     * 构建参数信息
     */
    private function buildParameters(ReflectionMethod $method, string $httpMethod, array $annotations, string $path = ''): array
    {
        $parameters = [];
        
        // 1. 路径参数（从路由路径解析 + 方法参数类型）
        $parameters = array_merge($parameters, $this->buildPathParameters($method, $path));

        // 2. 从 validate 注解获取查询参数（软依赖）
        if ($this->isValidateAvailable()) {
            $validation = $annotations[self::VALIDATE_REQUEST_VALIDATION] ?? null;
            
            if ($validation) {
                $parameters = array_merge(
                    $parameters, 
                    $this->buildValidationParameters($validation, $httpMethod)
                );
            }
        }

        return $parameters;
    }

    /**
     * 构建路径参数
     * 
     * 从路由路径中解析 {param} 格式的参数，并从方法参数获取类型信息
     */
    private function buildPathParameters(ReflectionMethod $method, string $path): array
    {
        $parameters = [];
        
        // 从路径中提取参数名：/users/{id}/posts/{postId} -> ['id', 'postId']
        preg_match_all('/\{(\w+)\}/', $path, $matches);
        $pathParamNames = $matches[1] ?? [];
        
        // 如果路径中没有参数，返回空
        if (empty($pathParamNames)) {
            return [];
        }
        
        // 构建方法参数映射，用于获取类型信息
        $methodParams = [];
        foreach ($method->getParameters() as $param) {
            $methodParams[$param->getName()] = $param;
        }
        
        // 为每个路径参数构建信息
        foreach ($pathParamNames as $paramName) {
            $param = $methodParams[$paramName] ?? null;
            
            $parameters[] = [
                'name' => $paramName,
                'in' => 'path',
                'required' => true, // 路径参数始终必需
                'schema' => $param ? $this->getParameterSchema($param) : ['type' => 'string'],
                'description' => "路径参数: {$paramName}"
            ];
        }

        return $parameters;
    }

    /**
     * 从验证注解构建参数
     */
    private function buildValidationParameters(object $validation, string $httpMethod): array
    {
        $parameters = [];
        
        // 处理 queryRules（始终作为查询参数）
        if (!empty($validation->queryRules)) {
            foreach ($validation->queryRules as $field => $rule) {
                $parameters[] = $this->buildQueryParameter($field, $rule, $validation);
            }
        }
        
        // 处理 rules（根据 HTTP 方法和 mode 判断）
        if ($this->shouldAddAsQueryParams($httpMethod, $validation)) {
            $rules = $this->getValidationRules($validation);
            foreach ($rules as $field => $rule) {
                $parameters[] = $this->buildQueryParameter($field, $rule, $validation);
            }
        }

        return $parameters;
    }

    /**
     * 构建单个查询参数
     */
    private function buildQueryParameter(string $field, mixed $rule, object $validation): array
    {
        [$fieldName, $description] = $this->parseFieldName($field);
        $ruleStr = is_array($rule) ? implode('|', $rule) : (string)$rule;
        
        // 优先使用 FormRequest 的 attributes 描述
        $attrDescription = $this->getFieldDescription($validation, $fieldName);
        
        return [
            'name' => $fieldName,
            'in' => 'query',
            'required' => str_contains($ruleStr, 'required'),
            'schema' => $this->ruleToSchema($ruleStr),
            'description' => $attrDescription ?: $description ?: "查询参数: {$fieldName}"
        ];
    }

    /**
     * 构建请求体信息
     */
    private function buildRequestBody(ReflectionMethod $method, string $httpMethod, array $annotations): ?array
    {
        // 1. 检查 RequestBody 注解（优先级最高）
        $requestBodyAnnotation = $annotations[RequestBody::class] ?? null;
        if ($requestBodyAnnotation) {
            return [
                'description' => $requestBodyAnnotation->description,
                'required' => $requestBodyAnnotation->required,
                'content' => $requestBodyAnnotation->content ?: [
                    'application/json' => ['schema' => ['type' => 'object']]
                ]
            ];
        }

        // 2. 从 validate 注解构建（软依赖）
        if ($this->isValidateAvailable()) {
            $validation = $annotations[self::VALIDATE_REQUEST_VALIDATION] ?? null;
            
            if ($validation && $this->shouldAddAsRequestBody($httpMethod, $validation)) {
                return $this->buildValidationRequestBody($validation);
            }
        }

        return null;
    }

    /**
     * 从验证注解构建请求体
     */
    private function buildValidationRequestBody(object $validation): ?array
    {
        $formRequestData = $this->getFormRequestData($validation);
        $rules = !empty($validation->rules) ? $validation->rules : ($formRequestData['rules'] ?? []);
        
        if (empty($rules)) {
            return null;
        }
        
        $attributes = $formRequestData['attributes'] ?? [];
        $schema = $this->rulesToJsonSchema($rules, $attributes);
        
        return [
            'description' => '请求数据',
            'required' => true,
            'content' => [
                'application/json' => ['schema' => $schema]
            ]
        ];
    }

    /**
     * 构建响应信息
     */
    private function buildResponses(array $annotations): array
    {
        $responses = [];
        
        foreach ($annotations as $annotation) {
            if ($annotation instanceof ApiResponse) {
                $responses[(string)$annotation->code] = [
                    'description' => $annotation->description ?? 'Success',
                    'content' => [
                        'application/json' => [
                            'schema' => $annotation->schema ?: ['type' => 'object']
                        ]
                    ]
                ];
            } elseif ($annotation instanceof ApiResponseExample) {
                $responses[(string)$annotation->code] = $this->buildResponseExample($annotation);
            }
        }

        return $responses ?: $this->getDefaultResponse();
    }

    /**
     * 构建响应示例
     */
    private function buildResponseExample(ApiResponseExample $annotation): array
    {
        $response = ['description' => $annotation->description ?? 'Success'];
        
        if ($annotation->schema || $annotation->schemaRef) {
            $content = [
                $annotation->mediaType => [
                    'schema' => $annotation->schemaRef 
                        ? ['$ref' => $annotation->schemaRef] 
                        : $annotation->schema
                ]
            ];
            
            if ($annotation->example) {
                $content[$annotation->mediaType]['example'] = $annotation->example;
            }
            
            if ($annotation->examples) {
                $content[$annotation->mediaType]['examples'] = $annotation->examples;
            }
            
            $response['content'] = $content;
        }
        
        if ($annotation->headers) {
            $response['headers'] = $annotation->headers;
        }
        
        if ($annotation->links) {
            $response['links'] = $annotation->links;
        }
        
        return $response;
    }

    /**
     * 获取默认响应
     */
    private function getDefaultResponse(): array
    {
        return [
            '200' => [
                'description' => 'Success',
                'content' => [
                    'application/json' => ['schema' => ['type' => 'object']]
                ]
            ]
        ];
    }

    /**
     * 构建组件信息
     */
    private function buildComponents(array $config): array
    {
        $components = [];

        $schemas = $this->buildSchemas();
        if (!empty($schemas)) {
            $components['schemas'] = $schemas;
        }

        if (!empty($config['security_schemes'])) {
            $components['securitySchemes'] = $config['security_schemes'];
        }

        return $components;
    }

    /**
     * 构建 Schema 定义
     */
    private function buildSchemas(): array
    {
        $schemas = [];
        $definitions = AnnotationCollector::getClassesByAnnotation(ApiDefinition::class);
        
        foreach ($definitions as $className => $annotation) {
            if ($annotation->name) {
                $schemas[$annotation->name] = $this->buildSchemaFromDefinition($annotation);
            }
        }

        return $schemas;
    }

    /**
     * 从 ApiDefinition 构建 Schema
     */
    private function buildSchemaFromDefinition(ApiDefinition $definition): array
    {
        $schema = ['type' => $definition->type ?? 'object'];

        $fields = [
            'title', 'description', 'default', 'example', 'examples',
            'const', 'enum', 'properties', 'required', 'additionalProperties',
            'minProperties', 'maxProperties'
        ];

        foreach ($fields as $field) {
            if ($definition->$field !== null) {
                $schema[$field] = $definition->$field;
            }
        }

        return $schema;
    }

    // ==================== 辅助方法 ====================

    /**
     * 检查 validate 插件是否可用
     */
    private function isValidateAvailable(): bool
    {
        return $this->validateAvailable ??= class_exists(self::VALIDATE_REQUEST_VALIDATION);
    }

    /**
     * 检查 RuleParser 是否可用
     */
    private function isRuleParserAvailable(): bool
    {
        return $this->ruleParserAvailable ??= class_exists(self::VALIDATE_RULE_PARSER);
    }

    /**
     * 从验证注解获取规则（支持内联规则和 FormRequest）
     */
    private function getValidationRules(object $validation): array
    {
        if (!empty($validation->rules)) {
            return $validation->rules;
        }
        
        $formRequestData = $this->getFormRequestData($validation);
        return $formRequestData['rules'] ?? [];
    }

    /**
     * 获取 FormRequest 类的完整数据
     */
    private function getFormRequestData(object $validation): array
    {
        if (empty($validation->validate) || !class_exists($validation->validate)) {
            return [];
        }
        
        $validatorClass = $validation->validate;
        $scene = $validation->scene ?? '';
        $cacheKey = $validatorClass . ':' . $scene;
        
        if (isset($this->formRequestCache[$cacheKey])) {
            return $this->formRequestCache[$cacheKey];
        }
        
        try {
            // 检查是否是 FormRequest 子类
            if (!class_exists(self::HYPERF_FORM_REQUEST) || !is_subclass_of($validatorClass, self::HYPERF_FORM_REQUEST)) {
                return $this->formRequestCache[$cacheKey] = [];
            }
            
            $reflection = new ReflectionClass($validatorClass);
            $instance = $reflection->newInstanceWithoutConstructor();
            
            // 获取 rules
            $allRules = $this->invokeMethodIfExists($reflection, $instance, 'rules', []);
            
            // 获取 attributes（字段描述）
            $attributes = $this->invokeMethodIfExists($reflection, $instance, 'attributes', []);
            
            // 处理场景过滤
            if (!empty($scene) && $reflection->hasProperty('scenes')) {
                $scenesProperty = $reflection->getProperty('scenes');
                $scenesProperty->setAccessible(true);
                $scenes = $scenesProperty->getValue($instance);
                
                if (isset($scenes[$scene])) {
                    $sceneFields = array_flip($scenes[$scene]);
                    $allRules = array_intersect_key($allRules, $sceneFields);
                    $attributes = array_intersect_key($attributes, $sceneFields);
                }
            }
            
            return $this->formRequestCache[$cacheKey] = [
                'rules' => $allRules,
                'attributes' => $attributes,
            ];
            
        } catch (\Throwable) {
            return $this->formRequestCache[$cacheKey] = [];
        }
    }

    /**
     * 安全调用反射方法
     */
    private function invokeMethodIfExists(ReflectionClass $reflection, object $instance, string $methodName, mixed $default): mixed
    {
        if (!$reflection->hasMethod($methodName)) {
            return $default;
        }
        
        $method = $reflection->getMethod($methodName);
        return $method->isStatic() 
            ? $reflection->getName()::$methodName() 
            : $method->invoke($instance);
    }

    /**
     * 获取字段描述
     */
    private function getFieldDescription(object $validation, string $fieldName): string
    {
        $formRequestData = $this->getFormRequestData($validation);
        return $formRequestData['attributes'][$fieldName] ?? '';
    }

    /**
     * 解析字段名和描述
     */
    private function parseFieldName(string $field): array
    {
        if ($this->isRuleParserAvailable()) {
            return (self::VALIDATE_RULE_PARSER)::parseFieldName($field);
        }
        
        // 兜底实现
        if (str_contains($field, '|')) {
            [$fieldName, $description] = explode('|', $field, 2);
            return [trim($fieldName), trim($description)];
        }
        
        return [$field, ''];
    }

    /**
     * 规则转 JSON Schema
     */
    private function ruleToSchema(string $rule): array
    {
        if ($this->isRuleParserAvailable()) {
            return (self::VALIDATE_RULE_PARSER)::ruleToJsonSchema($rule);
        }
        
        // 兜底实现
        $schema = ['type' => 'string'];
        
        $typeMap = [
            'integer' => 'integer', 'int' => 'integer',
            'numeric' => 'number', 'float' => 'number',
            'boolean' => 'boolean', 'bool' => 'boolean',
            'array' => 'array',
        ];
        
        foreach ($typeMap as $keyword => $type) {
            if (str_contains($rule, $keyword)) {
                $schema['type'] = $type;
                break;
            }
        }
        
        // 格式检测
        if (str_contains($rule, 'email')) {
            $schema['format'] = 'email';
        } elseif (str_contains($rule, 'url')) {
            $schema['format'] = 'uri';
        }
        
        return $schema;
    }

    /**
     * 规则数组转 JSON Schema
     */
    private function rulesToJsonSchema(array $rules, array $attributes = []): array
    {
        // 预处理：将数组格式规则转换为字符串格式
        $normalizedRules = [];
        foreach ($rules as $field => $rule) {
            $normalizedRules[$field] = is_array($rule) ? implode('|', $rule) : (string)$rule;
        }
        
        // 使用 RuleParser（如果可用）
        if ($this->isRuleParserAvailable()) {
            $schema = (self::VALIDATE_RULE_PARSER)::rulesToJsonSchema($normalizedRules);
            
            // 补充 attributes 描述
            if (!empty($attributes) && isset($schema['properties'])) {
                foreach ($attributes as $field => $description) {
                    if (isset($schema['properties'][$field])) {
                        $schema['properties'][$field]['description'] = $description;
                    }
                }
            }
            
            return $schema;
        }
        
        // 兜底实现
        $properties = [];
        $required = [];
        
        foreach ($normalizedRules as $field => $ruleStr) {
            [$fieldName, $description] = $this->parseFieldName($field);
            
            $properties[$fieldName] = $this->ruleToSchema($ruleStr);
            
            // 优先使用 attributes 描述
            $finalDescription = $attributes[$fieldName] ?? $description;
            if ($finalDescription) {
                $properties[$fieldName]['description'] = $finalDescription;
            }
            
            if (str_contains($ruleStr, 'required')) {
                $required[] = $fieldName;
            }
        }
        
        $schema = ['type' => 'object', 'properties' => $properties];
        
        if (!empty($required)) {
            $schema['required'] = $required;
        }
        
        return $schema;
    }

    /**
     * 获取参数 Schema
     */
    private function getParameterSchema(ReflectionParameter $param): array
    {
        $type = $param->getType();
        
        if (!$type instanceof \ReflectionNamedType) {
            return ['type' => 'string'];
        }

        return match ($type->getName()) {
            'int' => ['type' => 'integer'],
            'float' => ['type' => 'number'],
            'bool' => ['type' => 'boolean'],
            'array' => ['type' => 'array'],
            default => ['type' => 'string']
        };
    }

    /**
     * 判断是否应该添加为查询参数
     */
    private function shouldAddAsQueryParams(string $httpMethod, object $validation): bool
    {
        // GET/DELETE/HEAD/OPTIONS 的参数总是作为查询参数
        if (in_array($httpMethod, self::HTTP_METHODS_NO_BODY, true)) {
            return true;
        }
        
        // 非 json 模式也作为查询参数
        return ($validation->mode ?? 'json') !== 'json';
    }

    /**
     * 判断是否应该添加为请求体
     */
    private function shouldAddAsRequestBody(string $httpMethod, object $validation): bool
    {
        // 无请求体的 HTTP 方法不应该有请求体
        if (in_array($httpMethod, self::HTTP_METHODS_NO_BODY, true)) {
            return false;
        }
        
        // 只有 json 模式才作为请求体
        return ($validation->mode ?? 'json') === 'json';
    }

    /**
     * 获取默认服务器配置
     */
    private function getDefaultServers(): array
    {
        return [
            [
                'url' => $this->config->get('app_url', 'http://localhost:9501'),
                'description' => 'Development Server'
            ]
        ];
    }
}
