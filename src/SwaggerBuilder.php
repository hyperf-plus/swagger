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
use HPlus\Validate\Annotations\RequestValidation;
use HPlus\Swagger\Annotation\ApiDefinition;
use HPlus\Swagger\Annotation\ApiServer;
use HPlus\Swagger\Annotation\ApiCallback;
use HPlus\Swagger\Annotation\ApiLink;
use HPlus\Validate\RuleParser;
use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;

/**
 * 简洁的 Swagger 文档构建器
 * 支持 OpenAPI 3.1.1 完整规范
 * 通过反射独立收集信息，不依赖其他包的具体实现
 */
class SwaggerBuilder
{
    public function __construct(
        private ConfigInterface $config
    ) {}

    /**
     * 构建完整的 OpenAPI 3.1.1 文档
     */
    public function build(): array
    {
        $swaggerConfig = $this->config->get('swagger', []);
        
        $openapi = [
            'openapi' => '3.1.1',
            'info' => $this->buildInfo($swaggerConfig),
            'servers' => $swaggerConfig['servers'] ?? $this->getDefaultServers(),
            'paths' => $this->buildPaths(),
            'components' => $this->buildComponents($swaggerConfig),
        ];

        // 添加可选字段
        if (!empty($swaggerConfig['security'])) {
            $openapi['security'] = $swaggerConfig['security'];
        }

        if (!empty($swaggerConfig['tags'])) {
            $openapi['tags'] = $swaggerConfig['tags'];
        }

        if (!empty($swaggerConfig['externalDocs'])) {
            $openapi['externalDocs'] = $swaggerConfig['externalDocs'];
        }

        // OpenAPI 3.1+ 新特性
        if (!empty($swaggerConfig['webhooks'])) {
            $openapi['webhooks'] = $swaggerConfig['webhooks'];
        }

        if (!empty($swaggerConfig['jsonSchemaDialect'])) {
            $openapi['jsonSchemaDialect'] = $swaggerConfig['jsonSchemaDialect'];
        }

        // 添加扩展字段
        foreach ($swaggerConfig as $key => $value) {
            if (str_starts_with($key, 'x-') && $value !== null) {
                $openapi[$key] = $value;
            }
        }

        return array_filter($openapi, fn($value) => $value !== null);
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

        $optionalFields = [
            'summary', 'description', 'termsOfService', 'contact', 'license'
        ];

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
        // 完全依赖 RouteCollector - 统一的路由数据源
        if (class_exists(\HPlus\Route\RouteCollector::class)) {
            try {
                $routeCollector = \HPlus\Route\RouteCollector::getInstance();
                $routes = $routeCollector->collectRoutes();
                
                $paths = [];
                foreach ($routes as $route) {
                    foreach ($route['methods'] as $httpMethod) {
                        $operation = $this->buildOperationFromRoute($route);

                        if (!isset($paths[$route['path']])) {
                            $paths[$route['path']] = [];
                        }

                        $paths[$route['path']][strtolower($httpMethod)] = $operation;
                    }
                }
                
                return $paths;
                
            } catch (\Throwable $e) {
                error_log("RouteCollector failed in SwaggerBuilder: " . $e->getMessage());
            }
        }
        
        // 如果 RouteCollector 不可用，返回空数组
        return [];
    }

    /**
     * 从RouteCollector的路由信息构建操作（简化版本）
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

        // 性能优化：使用懒加载获取参数和请求体
        if (class_exists(\HPlus\Route\RouteCollector::class)) {
            $routeCollector = \HPlus\Route\RouteCollector::getInstance();
            
            // 懒加载参数
            $parameters = $routeCollector->getRouteParameters($route);
            if (!empty($parameters)) {
                $operation['parameters'] = $this->convertRouteParameters($parameters);
            }

            // 懒加载请求体
            $requestBody = $routeCollector->getRouteRequestBody($route);
            if ($requestBody) {
                $operation['requestBody'] = $this->convertRouteRequestBody($requestBody);
            }
        } else {
            // 兜底方案：使用已有的参数和请求体
            if (!empty($route['parameters'])) {
                $operation['parameters'] = $this->convertRouteParameters($route['parameters']);
            }

            if (!empty($route['requestBody'])) {
                $operation['requestBody'] = $this->convertRouteRequestBody($route['requestBody']);
            }
        }

        // 构建响应
        $operation['responses'] = [
            '200' => [
                'description' => 'Success',
                'content' => [
                    'application/json' => ['schema' => ['type' => 'object']]
                ]
            ]
        ];

        // 添加安全要求
        if ($route['security']) {
            $operation['security'] = $this->config->get('swagger.security', []);
        }

        return array_filter($operation, fn($value) => $value !== null && $value !== []);
    }

    /**
     * 构建单个控制器的路径
     */
    private function buildControllerPaths(ReflectionClass $controller, ApiController $controllerAnnotation): array
    {
        $paths = [];
        $controllerPrefix = $controllerAnnotation->prefix ?? '';
        
        foreach ($controller->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $routeAnnotation = $this->getRouteAnnotation($method);
            
            if ($routeAnnotation) {
                $path = $routeAnnotation->path ?? '';
                
                // 确保路径参数之前有斜杠
                if ($path !== '' && !str_starts_with($path, '/')) {
                    $path = '/' . $path;
                }
                
                // 处理控制器前缀和路径的拼接
                if ($controllerPrefix && $path) {
                    // 确保前缀以 / 结尾，路径以 / 开头
                    $controllerPrefix = rtrim($controllerPrefix, '/');
                    $fullPath = $controllerPrefix . $path;
                } else {
                    $fullPath = $controllerPrefix . $path;
                }
                $fullPath = $this->normalizePath($fullPath);
                
                foreach ($routeAnnotation->methods as $httpMethod) {
                    $operation = $this->buildOperation($method, $routeAnnotation, $controllerAnnotation);
                    
                    if (!isset($paths[$fullPath])) {
                        $paths[$fullPath] = [];
                    }
                    
                    $paths[$fullPath][strtolower($httpMethod)] = $operation;
                }
            }
        }

        return $paths;
    }

    /**
     * 构建操作信息
     */
    private function buildOperation(ReflectionMethod $method, Mapping $routeAnnotation, ApiController $controllerAnnotation): array
    {
        $operation = [
            'summary' => $routeAnnotation->summary ?: $method->getName(),
            'description' => $routeAnnotation->description ?: '',
            'operationId' => $method->getDeclaringClass()->getName() . '::' . $method->getName(),
            'tags' => $controllerAnnotation->tag ? [$controllerAnnotation->tag] : [],
        ];

        // 添加参数
        $parameters = $this->buildParameters($method);
        if (!empty($parameters)) {
            $operation['parameters'] = $parameters;
        }

        // 添加请求体
        $requestBody = $this->buildRequestBody($method);
        if ($requestBody) {
            $operation['requestBody'] = $requestBody;
        }

        // 添加响应
        $operation['responses'] = $this->buildResponses($method);

        // 添加回调
        $callbacks = $this->buildCallbacks($method);
        if (!empty($callbacks)) {
            $operation['callbacks'] = $callbacks;
        }

        // 添加安全要求
        if ($routeAnnotation->security || $controllerAnnotation->security) {
            $operation['security'] = $this->config->get('swagger.security', []);
        }

        // 添加废弃标记
        if ($routeAnnotation->deprecated) {
            $operation['deprecated'] = true;
        }

        return array_filter($operation, fn($value) => $value !== null && $value !== []);
    }

    /**
     * 构建参数信息
     */
    private function buildParameters(ReflectionMethod $method): array
    {
        $parameters = [];
        
        // 获取HTTP方法类型
        $httpMethod = $this->getHttpMethod($method);
        
        // 路径参数
        foreach ($method->getParameters() as $param) {
            if ($param->getType() && !$param->getType()->isBuiltin()) {
                continue; // 跳过对象参数
            }
            
            $parameters[] = [
                'name' => $param->getName(),
                'in' => 'path',
                'required' => !$param->isOptional(),
                'schema' => $this->getParameterSchema($param),
                'description' => "路径参数: {$param->getName()}"
            ];
        }

        // 查询参数（从验证注解）
        if (class_exists(RequestValidation::class)) {
            $methodAnnotations = AnnotationCollector::getClassMethodAnnotation(
                $method->getDeclaringClass()->getName(),
                $method->getName()
            );
            $validation = $methodAnnotations[RequestValidation::class] ?? null;

            if ($validation && !empty($validation->rules)) {
                // 根据HTTP方法和dateType决定参数位置
                $shouldAddAsQuery = $this->shouldAddValidationAsQueryParams($httpMethod, $validation);
                
                if ($shouldAddAsQuery) {
                    foreach ($validation->rules as $field => $rule) {
                        [$fieldName, $description] = $this->parseFieldName($field);
                        
                        $parameters[] = [
                            'name' => $fieldName,
                            'in' => 'query',
                            'required' => str_contains($rule, 'required'),
                            'schema' => $this->ruleToSchema($rule),
                            'description' => $description ?: "查询参数: {$fieldName}"
                        ];
                    }
                }
            }
        }

        return $parameters;
    }

    /**
     * 构建请求体信息
     */
    private function buildRequestBody(ReflectionMethod $method): ?array
    {
        // 获取HTTP方法类型
        $httpMethod = $this->getHttpMethod($method);
        
        // 检查RequestBody注解
        $methodAnnotations = AnnotationCollector::getClassMethodAnnotation(
            $method->getDeclaringClass()->getName(),
            $method->getName()
        );
        $requestBodyAnnotation = $methodAnnotations[RequestBody::class] ?? null;

        if ($requestBodyAnnotation) {
            return [
                'description' => $requestBodyAnnotation->description,
                'required' => $requestBodyAnnotation->required,
                'content' => $requestBodyAnnotation->content ?: [
                    'application/json' => ['schema' => ['type' => 'object']]
                ]
            ];
        }

        // 从验证注解构建
        if (class_exists(RequestValidation::class) && class_exists(RuleParser::class)) {
            $methodAnnotations = AnnotationCollector::getClassMethodAnnotation(
                $method->getDeclaringClass()->getName(),
                $method->getName()
            );
            $validation = $methodAnnotations[RequestValidation::class] ?? null;

            if ($validation && !empty($validation->rules)) {
                // 只有非GET方法且dateType为json时才添加请求体
                $shouldAddAsRequestBody = $this->shouldAddValidationAsRequestBody($httpMethod, $validation);
                
                if ($shouldAddAsRequestBody) {
                    $schema = RuleParser::rulesToJsonSchema($validation->rules);
                    
                    return [
                        'description' => '请求数据',
                        'required' => true,
                        'content' => [
                            'application/json' => ['schema' => $schema]
                        ]
                    ];
                }
            }
        }

        return null;
    }

    /**
     * 构建响应信息
     */
    private function buildResponses(ReflectionMethod $method): array
    {
        $responses = [];
        
        $methodAnnotations = AnnotationCollector::getClassMethodAnnotation(
            $method->getDeclaringClass()->getName(),
            $method->getName()
        );

        foreach ($methodAnnotations as $annotationClass => $annotation) {
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
                $response = ['description' => $annotation->description ?? 'Success'];
                
                if ($annotation->schema || $annotation->schemaRef) {
                    $content = [
                        $annotation->mediaType => [
                            'schema' => $annotation->schemaRef ? 
                                ['$ref' => $annotation->schemaRef] : 
                                $annotation->schema
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
                
                $responses[(string)$annotation->code] = $response;
            }
        }

        // 默认响应
        if (empty($responses)) {
            $responses['200'] = [
                'description' => 'Success',
                'content' => [
                    'application/json' => ['schema' => ['type' => 'object']]
                ]
            ];
        }

        return $responses;
    }

    /**
     * 构建回调信息
     */
    private function buildCallbacks(ReflectionMethod $method): array
    {
        $callbacks = [];
        
        $methodAnnotations = AnnotationCollector::getClassMethodAnnotation(
            $method->getDeclaringClass()->getName(),
            $method->getName()
        );

        foreach ($methodAnnotations as $annotationClass => $annotation) {
            if ($annotation instanceof ApiCallback) {
                $callbacks[$annotation->name] = [
                    $annotation->expression => $annotation->pathItem
                ];
            }
        }

        return $callbacks;
    }

    /**
     * 构建组件信息
     */
    private function buildComponents(array $config): array
    {
        $components = [];

        // Schemas
        $schemas = $this->buildSchemas();
        if (!empty($schemas)) {
            $components['schemas'] = $schemas;
        }

        // Security Schemes
        if (!empty($config['security_schemes'])) {
            $components['securitySchemes'] = $config['security_schemes'];
        }

        return $components;
    }

    /**
     * 构建Schema定义
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
     * 从ApiDefinition构建Schema
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

    /**
     * 获取路由注解
     */
    private function getRouteAnnotation(ReflectionMethod $method): ?Mapping
    {
        $routeAnnotations = [
            GetApi::class, PostApi::class, PutApi::class, 
            DeleteApi::class, PatchApi::class
        ];

        foreach ($routeAnnotations as $annotationClass) {
            $methodAnnotations = AnnotationCollector::getClassMethodAnnotation(
                $method->getDeclaringClass()->getName(),
                $method->getName()
            );
            $annotation = $methodAnnotations[$annotationClass] ?? null;
            
            if ($annotation) {
                return $annotation;
            }
        }

        return null;
    }

    /**
     * 获取参数Schema
     */
    private function getParameterSchema(ReflectionParameter $param): array
    {
        $type = $param->getType();
        
        if (!$type) {
            return ['type' => 'string'];
        }

        $typeName = $type instanceof \ReflectionNamedType ? $type->getName() : 'string';
        
        return match ($typeName) {
            'int' => ['type' => 'integer'],
            'float' => ['type' => 'number'],
            'bool' => ['type' => 'boolean'],
            'array' => ['type' => 'array'],
            default => ['type' => 'string']
        };
    }

    /**
     * 解析字段名和描述
     */
    private function parseFieldName(string $field): array
    {
        if (class_exists(RuleParser::class)) {
            return RuleParser::parseFieldName($field);
        }
        
        if (str_contains($field, '|')) {
            [$fieldName, $description] = explode('|', $field, 2);
            return [trim($fieldName), trim($description)];
        }
        
        return [$field, ''];
    }

    /**
     * 简单的规则到Schema转换
     */
    private function ruleToSchema(string $rule): array
    {
        if (class_exists(RuleParser::class)) {
            return RuleParser::ruleToJsonSchema($rule);
        }
        
        // 兜底的简单实现
        $schema = ['type' => 'string'];
        
        if (str_contains($rule, 'integer')) {
            $schema['type'] = 'integer';
        } elseif (str_contains($rule, 'email')) {
            $schema['format'] = 'email';
        }
        
        return $schema;
    }

    /**
     * 规范化路径
     */
    private function normalizePath(string $path): string
    {
        $path = '/' . trim($path, '/');
        return $path === '/' ? '/' : rtrim($path, '/');
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

    /**
     * 转换路由参数为OpenAPI格式
     */
    private function convertRouteParameters(array $parameters): array
    {
        $openApiParams = [];
        
        foreach ($parameters as $param) {
            $openApiParam = [
                'name' => $param['name'],
                'in' => $param['type'] === 'path' ? 'path' : 'query',
                'required' => $param['required'] ?? false,
                'description' => $param['description'] ?? '',
                'schema' => $this->convertDataTypeToSchema($param['dataType'] ?? 'string')
            ];
            
            $openApiParams[] = $openApiParam;
        }
        
        return $openApiParams;
    }

    /**
     * 转换路由请求体为OpenAPI格式
     */
    private function convertRouteRequestBody(array $requestBody): array
    {
        $schema = [
            'type' => 'object',
            'properties' => []
        ];
        
        if (!empty($requestBody['properties'])) {
            foreach ($requestBody['properties'] as $fieldName => $fieldInfo) {
                $schema['properties'][$fieldName] = [
                    'type' => $fieldInfo['type'] ?? 'string',
                    'description' => $fieldInfo['description'] ?? '',
                ];
            }
        }
        
        if (!empty($requestBody['requiredFields'])) {
            $schema['required'] = $requestBody['requiredFields'];
        }
        
        return [
            'description' => $requestBody['description'] ?? '请求数据',
            'required' => $requestBody['required'] ?? true,
            'content' => [
                'application/json' => ['schema' => $schema]
            ]
        ];
    }

    /**
     * 转换数据类型为OpenAPI Schema
     */
    private function convertDataTypeToSchema(string $dataType): array
    {
        return match ($dataType) {
            'integer' => ['type' => 'integer'],
            'number' => ['type' => 'number'],
            'boolean' => ['type' => 'boolean'],
            'array' => ['type' => 'array'],
            'object' => ['type' => 'object'],
            default => ['type' => 'string']
        };
    }

    /**
     * 获取HTTP方法类型
     */
    private function getHttpMethod(ReflectionMethod $method): string
    {
        $routeAnnotation = $this->getRouteAnnotation($method);
        
        if ($routeAnnotation instanceof GetApi) {
            return 'GET';
        } elseif ($routeAnnotation instanceof PostApi) {
            return 'POST';
        } elseif ($routeAnnotation instanceof PutApi) {
            return 'PUT';
        } elseif ($routeAnnotation instanceof DeleteApi) {
            return 'DELETE';
        } elseif ($routeAnnotation instanceof PatchApi) {
            return 'PATCH';
        }
        
        return 'GET'; // 默认为GET
    }

    /**
     * 判断验证规则是否应该添加为查询参数
     */
    private function shouldAddValidationAsQueryParams(string $httpMethod, RequestValidation $validation): bool
    {
        // GET 和 DELETE 方法的参数总是作为查询参数
        if (in_array($httpMethod, ['GET', 'DELETE'])) {
            return true;
        }
        
        // 其他方法如果明确指定了非json类型，也作为查询参数
        if ($validation->dateType !== 'json') {
            return true;
        }
        
        return false;
    }

    /**
     * 判断验证规则是否应该添加为请求体
     */
    private function shouldAddValidationAsRequestBody(string $httpMethod, RequestValidation $validation): bool
    {
        // GET 和 DELETE 方法不应该有请求体
        if (in_array($httpMethod, ['GET', 'DELETE'])) {
            return false;
        }
        
        // 只有明确指定为json类型才作为请求体
        return $validation->dateType === 'json';
    }
} 