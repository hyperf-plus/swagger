<?php
declare(strict_types=1);
namespace HPlus\Swagger\Annotation;

use Hyperf\Di\Annotation\AbstractAnnotation;

/**
 * API服务器配置注解 - 支持OpenAPI 3.1.1完整规范
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class ApiServer extends AbstractAnnotation
{
    public ?string $url = null;
    public ?string $description = null;
    public ?array $variables = null;
    
    // OpenAPI 3.1+ 扩展字段
    public ?array $extensions = null;

    public function __construct(
        ?string $url = null,
        ?string $description = null,
        ?array $variables = null,
        ?array $extensions = null
    ) {
        $this->url = $url;
        $this->description = $description;
        $this->variables = $variables;
        $this->extensions = $extensions;
    }
}
