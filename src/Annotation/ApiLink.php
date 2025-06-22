<?php

declare(strict_types=1);

namespace HPlus\Swagger\Annotation;

use Hyperf\Di\Annotation\AbstractAnnotation;

/**
 * API链接注解 - 支持OpenAPI 3.1.1链接功能
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class ApiLink extends AbstractAnnotation
{
    public ?string $name = null;
    public ?string $operationRef = null;
    public ?string $operationId = null;
    public ?array $parameters = null;
    public mixed $requestBody = null;
    public ?string $description = null;
    public ?object $server = null;
    
    // 扩展字段
    public ?array $extensions = null;

    public function __construct(
        ?string $name = null,
        ?string $operationRef = null,
        ?string $operationId = null,
        ?array $parameters = null,
        mixed $requestBody = null,
        ?string $description = null,
        ?object $server = null,
        ?array $extensions = null
    ) {
        $this->name = $name;
        $this->operationRef = $operationRef;
        $this->operationId = $operationId;
        $this->parameters = $parameters;
        $this->requestBody = $requestBody;
        $this->description = $description;
        $this->server = $server;
        $this->extensions = $extensions;
    }
} 