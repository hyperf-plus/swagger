<?php

declare(strict_types=1);

namespace HPlus\Swagger\Annotation;

use Hyperf\Di\Annotation\AbstractAnnotation;

/**
 * API回调注解 - 支持OpenAPI 3.1.1回调功能
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class ApiCallback extends AbstractAnnotation
{
    public ?string $name = null;
    public ?string $expression = null;
    public ?array $pathItem = null;
    public ?string $ref = null;
    
    // 扩展字段
    public ?array $extensions = null;

    public function __construct(
        ?string $name = null,
        ?string $expression = null,
        ?array $pathItem = null,
        ?string $ref = null,
        ?array $extensions = null
    ) {
        $this->name = $name;
        $this->expression = $expression;
        $this->pathItem = $pathItem;
        $this->ref = $ref;
        $this->extensions = $extensions;
    }
} 