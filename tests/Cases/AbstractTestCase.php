<?php

declare(strict_types=1);

namespace HPlus\Swagger\Tests\Cases;

use PHPUnit\Framework\TestCase;
use Mockery;

/**
 * 测试基类
 */
abstract class AbstractTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * 创建 Mockery Mock 对象
     */
    protected function createMockeryMock(string $class): Mockery\MockInterface
    {
        return Mockery::mock($class);
    }

    /**
     * 获取私有方法用于测试
     */
    protected function getPrivateMethod(object $object, string $methodName): \ReflectionMethod
    {
        $reflection = new \ReflectionClass($object);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        
        return $method;
    }

    /**
     * 获取私有属性用于测试
     */
    protected function getPrivateProperty(object $object, string $propertyName): mixed
    {
        $reflection = new \ReflectionClass($object);
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        
        return $property->getValue($object);
    }

    /**
     * 设置私有属性值
     */
    protected function setPrivateProperty(object $object, string $propertyName, mixed $value): void
    {
        $reflection = new \ReflectionClass($object);
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        $property->setValue($object, $value);
    }
}
