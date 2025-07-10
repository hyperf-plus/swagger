<?php

namespace HPlus\Swagger\Annotation;

use Attribute;
use Hyperf\Di\Annotation\AbstractAnnotation;

/**
 * @Annotation
 * @Target({"METHOD"})
 */
#[Attribute(Attribute::TARGET_METHOD)]
class ApiOperation extends AbstractAnnotation
{
    /**
     * @param string $summary 操作摘要
     * @param string $description 操作描述
     * @param array $tags 操作标签
     * @param string $operationId 操作ID
     * @param array $produces 响应内容类型
     * @param array $consumes 消费内容类型
     * @param array $parameters 参数
     * @param array $responses 响应
     * @param array $security 安全配置
     * @param bool $deprecated 是否废弃
     * @param array $externalDocs 外部文档
     */
    public function __construct(
        /**
         * 操作摘要
         * @var string
         */
        public string $summary = '',
        /**
         * 操作描述
         * @var string
         */
        public string $description = '',
        /**
         * 操作标签
         * @var array
         */
        public array  $tags = [],
        /**
         * 操作ID
         * @var string
         */
        public string $operationId = '',
        /**
         * 响应内容类型
         * @var array
         */
        public array  $produces = [],
        /**
         * 消费内容类型
         * @var array
         */
        public array  $consumes = [],
        /**
         * 参数
         * @var array
         */
        public array  $parameters = [],
        /**
         * 响应
         * @var array
         */
        public array  $responses = [],
        /**
         * 安全配置
         * @var array
         */
        public array  $security = [],
        /**
         * 是否废弃
         * @var bool
         */
        public bool   $deprecated = false,
        /**
         * 外部文档
         * @var array
         */
        public array  $externalDocs = []
    )
    {
    }
}