<?php

declare(strict_types=1);

namespace HPlus\Swagger\Annotation;

use Hyperf\Di\Annotation\AbstractAnnotation;

/**
 * 支持完整 OpenAPI 3.1.1 规范的 API 定义注解
 * 兼容 JSON Schema Draft 2020-12
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class ApiDefinition extends AbstractAnnotation
{
    // 基本信息
    public ?string $name = null;
    public ?string $title = null; 
    public ?string $description = null;
    public mixed $example = null;
    public ?array $examples = null; // JSON Schema 2020-12 新增

    // JSON Schema 核心
    public ?string $type = null;
    public mixed $const = null; // JSON Schema 2020-12
    public ?array $enum = null;
    public ?array $properties = null;
    public ?array $required = null;
    public mixed $default = null;

    // 数值验证 (number/integer)
    public ?float $minimum = null;
    public ?float $maximum = null;
    public ?float $exclusiveMinimum = null;
    public ?float $exclusiveMaximum = null;
    public ?float $multipleOf = null;

    // 字符串验证
    public ?int $minLength = null;
    public ?int $maxLength = null;
    public ?string $pattern = null;
    public ?string $format = null;

    // 数组验证
    public ?int $minItems = null;
    public ?int $maxItems = null;
    public ?bool $uniqueItems = null;
    public mixed $items = null;
    public mixed $additionalItems = null;
    public mixed $unevaluatedItems = null; // JSON Schema 2020-12
    public ?bool $prefixItems = null; // JSON Schema 2020-12

    // 对象验证
    public ?int $minProperties = null;
    public ?int $maxProperties = null;
    public mixed $additionalProperties = null;
    public mixed $patternProperties = null;
    public mixed $unevaluatedProperties = null; // JSON Schema 2020-12
    public mixed $propertyNames = null;

    // 组合 Schema
    public ?array $allOf = null;
    public ?array $oneOf = null;
    public ?array $anyOf = null;
    public mixed $not = null;

    // 条件 Schema (JSON Schema 2019-09+)
    public mixed $if = null;
    public mixed $then = null;
    public mixed $else = null;

    // 依赖关系 (JSON Schema 2020-12)
    public ?array $dependentRequired = null;
    public ?array $dependentSchemas = null;

    // 内容相关 (二进制数据支持)
    public ?string $contentMediaType = null;
    public ?string $contentEncoding = null;

    // OpenAPI 特有
    public ?bool $nullable = null; // 为了向后兼容
    public ?bool $deprecated = null;
    public ?bool $readOnly = null;
    public ?bool $writeOnly = null;
    public ?array $xml = null;
    public ?array $discriminator = null;
    public ?string $externalDocs = null;

    // Hyperf 扩展
    public ?string $ref = null; // $ref 引用
    public ?array $validation = null; // 验证规则映射

    public function __construct(
        ?string $name = null,
        ?string $title = null,
        ?string $description = null,
        mixed $example = null,
        ?array $examples = null,
        ?string $type = null,
        mixed $const = null,
        ?array $enum = null,
        ?array $properties = null,
        ?array $required = null,
        mixed $default = null,
        ?float $minimum = null,
        ?float $maximum = null,
        ?float $exclusiveMinimum = null,
        ?float $exclusiveMaximum = null,
        ?float $multipleOf = null,
        ?int $minLength = null,
        ?int $maxLength = null,
        ?string $pattern = null,
        ?string $format = null,
        ?int $minItems = null,
        ?int $maxItems = null,
        ?bool $uniqueItems = null,
        mixed $items = null,
        mixed $additionalItems = null,
        mixed $unevaluatedItems = null,
        ?bool $prefixItems = null,
        ?int $minProperties = null,
        ?int $maxProperties = null,
        mixed $additionalProperties = null,
        mixed $patternProperties = null,
        mixed $unevaluatedProperties = null,
        mixed $propertyNames = null,
        ?array $allOf = null,
        ?array $oneOf = null,
        ?array $anyOf = null,
        mixed $not = null,
        mixed $if = null,
        mixed $then = null,
        mixed $else = null,
        ?array $dependentRequired = null,
        ?array $dependentSchemas = null,
        ?string $contentMediaType = null,
        ?string $contentEncoding = null,
        ?bool $nullable = null,
        ?bool $deprecated = null,
        ?bool $readOnly = null,
        ?bool $writeOnly = null,
        ?array $xml = null,
        ?array $discriminator = null,
        ?string $externalDocs = null,
        ?string $ref = null,
        ?array $validation = null
    ) {
        $this->name = $name;
        $this->title = $title;
        $this->description = $description;
        $this->example = $example;
        $this->examples = $examples;
        $this->type = $type;
        $this->const = $const;
        $this->enum = $enum;
        $this->properties = $properties;
        $this->required = $required;
        $this->default = $default;
        $this->minimum = $minimum;
        $this->maximum = $maximum;
        $this->exclusiveMinimum = $exclusiveMinimum;
        $this->exclusiveMaximum = $exclusiveMaximum;
        $this->multipleOf = $multipleOf;
        $this->minLength = $minLength;
        $this->maxLength = $maxLength;
        $this->pattern = $pattern;
        $this->format = $format;
        $this->minItems = $minItems;
        $this->maxItems = $maxItems;
        $this->uniqueItems = $uniqueItems;
        $this->items = $items;
        $this->additionalItems = $additionalItems;
        $this->unevaluatedItems = $unevaluatedItems;
        $this->prefixItems = $prefixItems;
        $this->minProperties = $minProperties;
        $this->maxProperties = $maxProperties;
        $this->additionalProperties = $additionalProperties;
        $this->patternProperties = $patternProperties;
        $this->unevaluatedProperties = $unevaluatedProperties;
        $this->propertyNames = $propertyNames;
        $this->allOf = $allOf;
        $this->oneOf = $oneOf;
        $this->anyOf = $anyOf;
        $this->not = $not;
        $this->if = $if;
        $this->then = $then;
        $this->else = $else;
        $this->dependentRequired = $dependentRequired;
        $this->dependentSchemas = $dependentSchemas;
        $this->contentMediaType = $contentMediaType;
        $this->contentEncoding = $contentEncoding;
        $this->nullable = $nullable;
        $this->deprecated = $deprecated;
        $this->readOnly = $readOnly;
        $this->writeOnly = $writeOnly;
        $this->xml = $xml;
        $this->discriminator = $discriminator;
        $this->externalDocs = $externalDocs;
        $this->ref = $ref;
        $this->validation = $validation;
    }
}