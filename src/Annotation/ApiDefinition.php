<?php


namespace HPlus\Swagger\Annotation;

use Hyperf\Di\Annotation\AbstractAnnotation;

/**
 * @Annotation
 * @Target({"ALL"})
 */
class ApiDefinition extends AbstractAnnotation
{
    public $name;
    public $properties;
}