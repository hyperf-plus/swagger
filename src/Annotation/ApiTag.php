<?php

namespace HPlus\Swagger\Annotation;

use Attribute;
use Hyperf\Di\Annotation\AbstractAnnotation;

/**
 * @Annotation
 * @Target({"CLASS", "METHOD"})
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class ApiTag extends AbstractAnnotation
{
    /**
     * @param string $name 标签名称
     * @param string $description 标签描述
     * @param array $externalDocs 外部文档
     */
    public function __construct(
        /**
         * 标签名称
         * @var string
         */
        public string $name = '',
        /**
         * 标签描述
         * @var string
         */
        public string $description = '',
        /**
         * 外部文档
         * @var array
         */
        public array  $externalDocs = []
    )
    {
    }
}