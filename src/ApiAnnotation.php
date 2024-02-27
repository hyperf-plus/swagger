<?php
declare(strict_types=1);
namespace HPlus\Swagger;

use Hyperf\Di\Annotation\AnnotationCollector;
use Hyperf\Di\Annotation\AnnotationReader;
use Hyperf\Di\ReflectionManager;
use function Hyperf\Config\config;

class ApiAnnotation
{
    public static function methodMetadata($className, $methodName)
    {
        $reflectMethod = ReflectionManager::reflectMethod($className, $methodName);
        $reader = new AnnotationReader(config('annotations.scan.ignore_annotations', []));
        return $reader->getMethodAnnotations($reflectMethod);
    }

    public static function classMetadata($className) {
        return AnnotationCollector::list()[$className]['_c'] ?? [];
    }
}
