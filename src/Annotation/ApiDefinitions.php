<?php


namespace HPlus\Swagger\Annotation;

use Attribute;
use Hyperf\Di\Annotation\AbstractAnnotation;
/**
 * @Annotation
 * @Target({"CLASS"})
 */
#[Attribute(Attribute::TARGET_CLASS)]
class ApiDefinitions extends AbstractAnnotation
{
    /**
     * @var array
     */
    public $definitions;
    
    public function __construct($value = null)
    {
        $this->bindMainProperty('definitions', $value);
    }
}