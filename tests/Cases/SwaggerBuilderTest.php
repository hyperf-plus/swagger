<?php

declare(strict_types=1);

namespace HPlus\Swagger\Tests\Cases;

use HPlus\Swagger\SwaggerBuilder;
use Hyperf\Contract\ConfigInterface;
use Mockery;

/**
 * SwaggerBuilder 单元测试
 */
final class SwaggerBuilderTest extends AbstractTestCase
{
    private SwaggerBuilder $builder;
    private $mockConfig;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockConfig = Mockery::mock(ConfigInterface::class);
        $this->mockConfig->shouldReceive('get')
            ->with('swagger', [])
            ->andReturn([
                'title' => 'Test API',
                'version' => '1.0.0',
            ]);
        $this->mockConfig->shouldReceive('get')
            ->with('app_url', 'http://localhost:9501')
            ->andReturn('http://localhost:9501');
        $this->mockConfig->shouldReceive('get')
            ->with('swagger.security', [])
            ->andReturn([]);
            
        $this->builder = new SwaggerBuilder($this->mockConfig);
    }

    // ==================== 基础构建测试 ====================

    /**
     * @test
     */
    public function it_builds_valid_openapi_document(): void
    {
        $doc = $this->builder->build();
        
        $this->assertIsArray($doc);
        $this->assertArrayHasKey('openapi', $doc);
        $this->assertArrayHasKey('info', $doc);
        $this->assertArrayHasKey('paths', $doc);
    }

    /**
     * @test
     */
    public function it_uses_openapi_311_version(): void
    {
        $doc = $this->builder->build();
        
        $this->assertEquals('3.1.1', $doc['openapi']);
    }

    // ==================== Info 构建测试 ====================

    /**
     * @test
     */
    public function it_builds_info_with_defaults(): void
    {
        $mockConfig = Mockery::mock(ConfigInterface::class);
        $mockConfig->shouldReceive('get')
            ->with('swagger', [])
            ->andReturn([]);
        $mockConfig->shouldReceive('get')
            ->with('app_url', 'http://localhost:9501')
            ->andReturn('http://localhost:9501');
            
        $builder = new SwaggerBuilder($mockConfig);
        $doc = $builder->build();
        
        $this->assertEquals('API Documentation', $doc['info']['title']);
        $this->assertEquals('1.0.0', $doc['info']['version']);
    }

    /**
     * @test
     */
    public function it_builds_info_with_custom_values(): void
    {
        $mockConfig = Mockery::mock(ConfigInterface::class);
        $mockConfig->shouldReceive('get')
            ->with('swagger', [])
            ->andReturn([
                'title' => 'My Custom API',
                'version' => '2.0.0',
                'description' => 'A comprehensive API',
                'contact' => ['email' => 'test@example.com'],
            ]);
        $mockConfig->shouldReceive('get')
            ->with('app_url', 'http://localhost:9501')
            ->andReturn('http://localhost:9501');
            
        $builder = new SwaggerBuilder($mockConfig);
        $doc = $builder->build();
        
        $this->assertEquals('My Custom API', $doc['info']['title']);
        $this->assertEquals('2.0.0', $doc['info']['version']);
        $this->assertEquals('A comprehensive API', $doc['info']['description']);
        $this->assertEquals(['email' => 'test@example.com'], $doc['info']['contact']);
    }

    /**
     * @test
     */
    public function it_builds_info_with_optional_fields(): void
    {
        $mockConfig = Mockery::mock(ConfigInterface::class);
        $mockConfig->shouldReceive('get')
            ->with('swagger', [])
            ->andReturn([
                'title' => 'API',
                'version' => '1.0.0',
                'summary' => 'API Summary',
                'termsOfService' => 'https://example.com/terms',
                'license' => ['name' => 'MIT', 'url' => 'https://opensource.org/licenses/MIT'],
            ]);
        $mockConfig->shouldReceive('get')
            ->with('app_url', 'http://localhost:9501')
            ->andReturn('http://localhost:9501');
            
        $builder = new SwaggerBuilder($mockConfig);
        $doc = $builder->build();
        
        $this->assertEquals('API Summary', $doc['info']['summary']);
        $this->assertEquals('https://example.com/terms', $doc['info']['termsOfService']);
        $this->assertEquals(['name' => 'MIT', 'url' => 'https://opensource.org/licenses/MIT'], $doc['info']['license']);
    }

    // ==================== Servers 构建测试 ====================

    /**
     * @test
     */
    public function it_builds_default_servers(): void
    {
        $doc = $this->builder->build();
        
        $this->assertArrayHasKey('servers', $doc);
        $this->assertIsArray($doc['servers']);
        $this->assertNotEmpty($doc['servers']);
        $this->assertEquals('http://localhost:9501', $doc['servers'][0]['url']);
        $this->assertEquals('Development Server', $doc['servers'][0]['description']);
    }

    /**
     * @test
     */
    public function it_uses_custom_servers(): void
    {
        $mockConfig = Mockery::mock(ConfigInterface::class);
        $mockConfig->shouldReceive('get')
            ->with('swagger', [])
            ->andReturn([
                'servers' => [
                    ['url' => 'https://api.example.com', 'description' => 'Production'],
                    ['url' => 'https://staging.example.com', 'description' => 'Staging'],
                ]
            ]);
            
        $builder = new SwaggerBuilder($mockConfig);
        $doc = $builder->build();
        
        $this->assertCount(2, $doc['servers']);
        $this->assertEquals('https://api.example.com', $doc['servers'][0]['url']);
        $this->assertEquals('Production', $doc['servers'][0]['description']);
    }

    // ==================== 可选字段测试 ====================

    /**
     * @test
     */
    public function it_includes_tags_when_configured(): void
    {
        $mockConfig = Mockery::mock(ConfigInterface::class);
        $mockConfig->shouldReceive('get')
            ->with('swagger', [])
            ->andReturn([
                'tags' => [
                    ['name' => 'Users', 'description' => 'User operations'],
                    ['name' => 'Posts', 'description' => 'Post operations'],
                ]
            ]);
        $mockConfig->shouldReceive('get')
            ->with('app_url', 'http://localhost:9501')
            ->andReturn('http://localhost:9501');
            
        $builder = new SwaggerBuilder($mockConfig);
        $doc = $builder->build();
        
        $this->assertArrayHasKey('tags', $doc);
        $this->assertCount(2, $doc['tags']);
    }

    /**
     * @test
     */
    public function it_includes_security_when_configured(): void
    {
        $mockConfig = Mockery::mock(ConfigInterface::class);
        $mockConfig->shouldReceive('get')
            ->with('swagger', [])
            ->andReturn([
                'security' => [['bearerAuth' => []]]
            ]);
        $mockConfig->shouldReceive('get')
            ->with('app_url', 'http://localhost:9501')
            ->andReturn('http://localhost:9501');
            
        $builder = new SwaggerBuilder($mockConfig);
        $doc = $builder->build();
        
        $this->assertArrayHasKey('security', $doc);
    }

    /**
     * @test
     */
    public function it_includes_external_docs_when_configured(): void
    {
        $mockConfig = Mockery::mock(ConfigInterface::class);
        $mockConfig->shouldReceive('get')
            ->with('swagger', [])
            ->andReturn([
                'externalDocs' => [
                    'url' => 'https://docs.example.com',
                    'description' => 'External documentation'
                ]
            ]);
        $mockConfig->shouldReceive('get')
            ->with('app_url', 'http://localhost:9501')
            ->andReturn('http://localhost:9501');
            
        $builder = new SwaggerBuilder($mockConfig);
        $doc = $builder->build();
        
        $this->assertArrayHasKey('externalDocs', $doc);
        $this->assertEquals('https://docs.example.com', $doc['externalDocs']['url']);
    }

    // ==================== OpenAPI 3.1+ 特性测试 ====================

    /**
     * @test
     */
    public function it_includes_webhooks_when_configured(): void
    {
        $mockConfig = Mockery::mock(ConfigInterface::class);
        $mockConfig->shouldReceive('get')
            ->with('swagger', [])
            ->andReturn([
                'webhooks' => [
                    'newUser' => [
                        'post' => [
                            'summary' => 'New user webhook',
                            'requestBody' => ['content' => ['application/json' => []]]
                        ]
                    ]
                ]
            ]);
        $mockConfig->shouldReceive('get')
            ->with('app_url', 'http://localhost:9501')
            ->andReturn('http://localhost:9501');
            
        $builder = new SwaggerBuilder($mockConfig);
        $doc = $builder->build();
        
        $this->assertArrayHasKey('webhooks', $doc);
    }

    /**
     * @test
     */
    public function it_includes_json_schema_dialect_when_configured(): void
    {
        $mockConfig = Mockery::mock(ConfigInterface::class);
        $mockConfig->shouldReceive('get')
            ->with('swagger', [])
            ->andReturn([
                'jsonSchemaDialect' => 'https://json-schema.org/draft/2020-12/schema'
            ]);
        $mockConfig->shouldReceive('get')
            ->with('app_url', 'http://localhost:9501')
            ->andReturn('http://localhost:9501');
            
        $builder = new SwaggerBuilder($mockConfig);
        $doc = $builder->build();
        
        $this->assertArrayHasKey('jsonSchemaDialect', $doc);
    }

    /**
     * @test
     */
    public function it_includes_extension_fields(): void
    {
        $mockConfig = Mockery::mock(ConfigInterface::class);
        $mockConfig->shouldReceive('get')
            ->with('swagger', [])
            ->andReturn([
                'x-custom-field' => 'custom value',
                'x-api-id' => 'my-api-123',
            ]);
        $mockConfig->shouldReceive('get')
            ->with('app_url', 'http://localhost:9501')
            ->andReturn('http://localhost:9501');
            
        $builder = new SwaggerBuilder($mockConfig);
        $doc = $builder->build();
        
        $this->assertArrayHasKey('x-custom-field', $doc);
        $this->assertEquals('custom value', $doc['x-custom-field']);
        $this->assertArrayHasKey('x-api-id', $doc);
    }

    // ==================== Components 构建测试 ====================

    /**
     * @test
     */
    public function it_includes_security_schemes_in_components(): void
    {
        $mockConfig = Mockery::mock(ConfigInterface::class);
        $mockConfig->shouldReceive('get')
            ->with('swagger', [])
            ->andReturn([
                'security_schemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'JWT'
                    ]
                ]
            ]);
        $mockConfig->shouldReceive('get')
            ->with('app_url', 'http://localhost:9501')
            ->andReturn('http://localhost:9501');
            
        $builder = new SwaggerBuilder($mockConfig);
        $doc = $builder->build();
        
        $this->assertArrayHasKey('components', $doc);
        $this->assertArrayHasKey('securitySchemes', $doc['components']);
        $this->assertArrayHasKey('bearerAuth', $doc['components']['securitySchemes']);
    }

    // ==================== 私有方法测试 ====================

    /**
     * @test
     */
    public function it_parses_field_names_correctly(): void
    {
        $method = $this->getPrivateMethod($this->builder, 'parseFieldName');
        
        // 简单字段名
        $result = $method->invoke($this->builder, 'username');
        $this->assertEquals(['username', ''], $result);
        
        // 带描述的字段名
        $result = $method->invoke($this->builder, 'username|用户名');
        $this->assertEquals(['username', '用户名'], $result);
        
        // 带空格的描述
        $result = $method->invoke($this->builder, 'email | 邮箱地址');
        $this->assertEquals(['email', '邮箱地址'], $result);
    }

    /**
     * @test
     */
    public function it_converts_rules_to_schema_correctly(): void
    {
        $method = $this->getPrivateMethod($this->builder, 'ruleToSchema');
        
        // 整数类型
        $schema = $method->invoke($this->builder, 'required|integer');
        $this->assertEquals('integer', $schema['type']);
        
        // 数字类型
        $schema = $method->invoke($this->builder, 'numeric');
        $this->assertEquals('number', $schema['type']);
        
        // 布尔类型
        $schema = $method->invoke($this->builder, 'boolean');
        $this->assertEquals('boolean', $schema['type']);
        
        // 数组类型
        $schema = $method->invoke($this->builder, 'array');
        $this->assertEquals('array', $schema['type']);
        
        // 邮箱格式
        $schema = $method->invoke($this->builder, 'required|email');
        $this->assertEquals('email', $schema['format']);
        
        // URL格式
        $schema = $method->invoke($this->builder, 'url');
        $this->assertEquals('uri', $schema['format']);
        
        // 默认字符串
        $schema = $method->invoke($this->builder, 'required|max:255');
        $this->assertEquals('string', $schema['type']);
    }

    /**
     * @test
     */
    public function it_converts_int_rule_to_integer_schema(): void
    {
        $method = $this->getPrivateMethod($this->builder, 'ruleToSchema');
        
        // 'int' 也应该转换为 integer
        $schema = $method->invoke($this->builder, 'required|int');
        $this->assertEquals('integer', $schema['type']);
    }

    /**
     * @test
     */
    public function it_converts_float_rule_to_number_schema(): void
    {
        $method = $this->getPrivateMethod($this->builder, 'ruleToSchema');
        
        $schema = $method->invoke($this->builder, 'required|float');
        $this->assertEquals('number', $schema['type']);
    }

    /**
     * @test
     */
    public function it_converts_bool_rule_to_boolean_schema(): void
    {
        $method = $this->getPrivateMethod($this->builder, 'ruleToSchema');
        
        $schema = $method->invoke($this->builder, 'required|bool');
        $this->assertEquals('boolean', $schema['type']);
    }

    /**
     * @test
     */
    public function it_converts_rules_array_to_json_schema(): void
    {
        $method = $this->getPrivateMethod($this->builder, 'rulesToJsonSchema');
        
        $rules = [
            'name|姓名' => 'required|string|max:50',
            'age' => 'required|integer|min:0|max:150',
            'email|邮箱' => 'required|email',
            'score' => 'numeric',
        ];
        
        $schema = $method->invoke($this->builder, $rules);
        
        $this->assertEquals('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('required', $schema);
        
        // 验证属性
        $this->assertArrayHasKey('name', $schema['properties']);
        $this->assertArrayHasKey('age', $schema['properties']);
        $this->assertArrayHasKey('email', $schema['properties']);
        $this->assertArrayHasKey('score', $schema['properties']);
        
        // 验证类型
        $this->assertEquals('integer', $schema['properties']['age']['type']);
        $this->assertEquals('email', $schema['properties']['email']['format']);
        $this->assertEquals('number', $schema['properties']['score']['type']);
        
        // 验证描述
        $this->assertEquals('姓名', $schema['properties']['name']['description']);
        $this->assertEquals('邮箱', $schema['properties']['email']['description']);
        
        // 验证 required 字段
        $this->assertContains('name', $schema['required']);
        $this->assertContains('age', $schema['required']);
        $this->assertContains('email', $schema['required']);
        $this->assertNotContains('score', $schema['required']);
    }

    /**
     * @test
     */
    public function it_converts_array_format_rules_to_json_schema(): void
    {
        $method = $this->getPrivateMethod($this->builder, 'rulesToJsonSchema');
        
        // FormRequest 可能返回数组格式的规则
        $rules = [
            'name' => ['required', 'string', 'max:50'],
            'age' => ['required', 'integer'],
        ];
        
        $schema = $method->invoke($this->builder, $rules);
        
        $this->assertEquals('object', $schema['type']);
        $this->assertArrayHasKey('name', $schema['properties']);
        $this->assertArrayHasKey('age', $schema['properties']);
        $this->assertEquals('integer', $schema['properties']['age']['type']);
        $this->assertContains('name', $schema['required']);
        $this->assertContains('age', $schema['required']);
    }

    /**
     * @test
     */
    public function it_uses_attributes_for_descriptions(): void
    {
        $method = $this->getPrivateMethod($this->builder, 'rulesToJsonSchema');
        
        $rules = [
            'name' => 'required|string',
            'email' => 'required|email',
        ];
        
        $attributes = [
            'name' => '用户姓名',
            'email' => '邮箱地址',
        ];
        
        $schema = $method->invoke($this->builder, $rules, $attributes);
        
        $this->assertEquals('用户姓名', $schema['properties']['name']['description']);
        $this->assertEquals('邮箱地址', $schema['properties']['email']['description']);
    }

    /**
     * @test
     */
    public function it_determines_query_params_correctly(): void
    {
        $method = $this->getPrivateMethod($this->builder, 'shouldAddAsQueryParams');
        
        // 创建模拟的验证对象
        $jsonValidation = new \stdClass();
        $jsonValidation->mode = 'json';
        
        $queryValidation = new \stdClass();
        $queryValidation->mode = 'query';
        
        $formValidation = new \stdClass();
        $formValidation->mode = 'form';
        
        // GET 方法总是 query
        $this->assertTrue($method->invoke($this->builder, 'GET', $jsonValidation));
        $this->assertTrue($method->invoke($this->builder, 'GET', $queryValidation));
        
        // DELETE 方法总是 query
        $this->assertTrue($method->invoke($this->builder, 'DELETE', $jsonValidation));
        
        // HEAD 方法总是 query
        $this->assertTrue($method->invoke($this->builder, 'HEAD', $jsonValidation));
        
        // OPTIONS 方法总是 query
        $this->assertTrue($method->invoke($this->builder, 'OPTIONS', $jsonValidation));
        
        // POST 方法根据 mode 判断
        $this->assertFalse($method->invoke($this->builder, 'POST', $jsonValidation));
        $this->assertTrue($method->invoke($this->builder, 'POST', $queryValidation));
        $this->assertTrue($method->invoke($this->builder, 'POST', $formValidation));
        
        // PUT 方法根据 mode 判断
        $this->assertFalse($method->invoke($this->builder, 'PUT', $jsonValidation));
        $this->assertTrue($method->invoke($this->builder, 'PUT', $queryValidation));
        
        // PATCH 方法根据 mode 判断
        $this->assertFalse($method->invoke($this->builder, 'PATCH', $jsonValidation));
        $this->assertTrue($method->invoke($this->builder, 'PATCH', $formValidation));
    }

    /**
     * @test
     */
    public function it_determines_request_body_correctly(): void
    {
        $method = $this->getPrivateMethod($this->builder, 'shouldAddAsRequestBody');
        
        $jsonValidation = new \stdClass();
        $jsonValidation->mode = 'json';
        
        $queryValidation = new \stdClass();
        $queryValidation->mode = 'query';
        
        // GET 方法不应该有请求体
        $this->assertFalse($method->invoke($this->builder, 'GET', $jsonValidation));
        
        // DELETE 方法不应该有请求体
        $this->assertFalse($method->invoke($this->builder, 'DELETE', $jsonValidation));
        
        // HEAD 方法不应该有请求体
        $this->assertFalse($method->invoke($this->builder, 'HEAD', $jsonValidation));
        
        // OPTIONS 方法不应该有请求体
        $this->assertFalse($method->invoke($this->builder, 'OPTIONS', $jsonValidation));
        
        // POST + json mode 应该有请求体
        $this->assertTrue($method->invoke($this->builder, 'POST', $jsonValidation));
        
        // POST + query mode 不应该有请求体
        $this->assertFalse($method->invoke($this->builder, 'POST', $queryValidation));
        
        // PUT + json mode 应该有请求体
        $this->assertTrue($method->invoke($this->builder, 'PUT', $jsonValidation));
        
        // PATCH + json mode 应该有请求体
        $this->assertTrue($method->invoke($this->builder, 'PATCH', $jsonValidation));
    }

    /**
     * @test
     */
    public function it_handles_missing_mode_property(): void
    {
        $method = $this->getPrivateMethod($this->builder, 'shouldAddAsRequestBody');
        
        // 没有 mode 属性时，默认为 json
        $validation = new \stdClass();
        
        $this->assertTrue($method->invoke($this->builder, 'POST', $validation));
    }

    // ==================== 缓存测试 ====================

    /**
     * @test
     */
    public function it_has_annotation_cache(): void
    {
        $property = $this->getPrivateProperty($this->builder, 'annotationCache');
        
        $this->assertIsArray($property);
    }

    /**
     * @test
     */
    public function it_has_form_request_cache(): void
    {
        $property = $this->getPrivateProperty($this->builder, 'formRequestCache');
        
        $this->assertIsArray($property);
    }

    // ==================== 参数 Schema 测试 ====================

    /**
     * @test
     */
    public function it_gets_parameter_schema_for_int(): void
    {
        $method = $this->getPrivateMethod($this->builder, 'getParameterSchema');
        
        // 创建一个模拟的 ReflectionParameter
        $mockParam = $this->createMockParameter('int');
        
        $schema = $method->invoke($this->builder, $mockParam);
        
        $this->assertEquals(['type' => 'integer'], $schema);
    }

    /**
     * @test
     */
    public function it_gets_parameter_schema_for_float(): void
    {
        $method = $this->getPrivateMethod($this->builder, 'getParameterSchema');
        
        $mockParam = $this->createMockParameter('float');
        
        $schema = $method->invoke($this->builder, $mockParam);
        
        $this->assertEquals(['type' => 'number'], $schema);
    }

    /**
     * @test
     */
    public function it_gets_parameter_schema_for_bool(): void
    {
        $method = $this->getPrivateMethod($this->builder, 'getParameterSchema');
        
        $mockParam = $this->createMockParameter('bool');
        
        $schema = $method->invoke($this->builder, $mockParam);
        
        $this->assertEquals(['type' => 'boolean'], $schema);
    }

    /**
     * @test
     */
    public function it_gets_parameter_schema_for_array(): void
    {
        $method = $this->getPrivateMethod($this->builder, 'getParameterSchema');
        
        $mockParam = $this->createMockParameter('array');
        
        $schema = $method->invoke($this->builder, $mockParam);
        
        $this->assertEquals(['type' => 'array'], $schema);
    }

    /**
     * @test
     */
    public function it_gets_parameter_schema_for_string(): void
    {
        $method = $this->getPrivateMethod($this->builder, 'getParameterSchema');
        
        $mockParam = $this->createMockParameter('string');
        
        $schema = $method->invoke($this->builder, $mockParam);
        
        $this->assertEquals(['type' => 'string'], $schema);
    }

    /**
     * @test
     */
    public function it_gets_string_schema_for_no_type(): void
    {
        $method = $this->getPrivateMethod($this->builder, 'getParameterSchema');
        
        $mockParam = $this->createMockParameter(null);
        
        $schema = $method->invoke($this->builder, $mockParam);
        
        $this->assertEquals(['type' => 'string'], $schema);
    }

    // ==================== 辅助方法 ====================

    /**
     * 创建模拟的 ReflectionParameter
     */
    private function createMockParameter(?string $typeName): \ReflectionParameter
    {
        $mockType = null;
        
        if ($typeName !== null) {
            $mockType = Mockery::mock(\ReflectionNamedType::class);
            $mockType->shouldReceive('getName')->andReturn($typeName);
        }
        
        $mockParam = Mockery::mock(\ReflectionParameter::class);
        $mockParam->shouldReceive('getType')->andReturn($mockType);
        
        return $mockParam;
    }
}
