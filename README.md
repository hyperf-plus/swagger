# HPlus Swagger 4.0

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.1-8892BF.svg)](https://php.net)
[![Hyperf Version](https://img.shields.io/badge/hyperf-%3E%3D3.1-brightgreen.svg)](https://hyperf.io)
[![OpenAPI](https://img.shields.io/badge/OpenAPI-3.1.1-green.svg)](https://www.openapis.org/)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

为 Hyperf 框架打造的智能 API 文档生成组件，支持 OpenAPI 3.1.1 规范，自动集成 Route 和 Validate 组件。

## ✨ 4.0 新特性

- 🚀 **OpenAPI 3.1.1** - 完整支持最新规范
- 🔄 **智能集成** - 自动识别 Route 和 Validate 注解
- 📝 **验证规则转换** - 自动将验证规则转为 JSON Schema
- ⚡ **软依赖设计** - validate 插件可选，无则跳过参数解析
- 🎨 **美观 UI** - 集成 Swagger UI，支持在线测试
- 💾 **懒加载+缓存** - 启动零开销，首次访问构建并缓存
- 📋 **FormRequest 支持** - 自动识别 Hyperf FormRequest 验证器

## 📦 安装

```bash
composer require hyperf-plus/swagger:^4.0
```

### 可选依赖

```bash
# 路由注解支持
composer require hyperf-plus/route:^4.0

# 验证规则转参数支持
composer require hyperf-plus/validate:^4.0
```

## 🚀 快速开始

### 1. 发布配置

```bash
php bin/hyperf.php vendor:publish hyperf-plus/swagger
```

### 2. 配置

编辑 `config/autoload/swagger.php`：

```php
return [
    'enable' => true,
    'port' => 9501,
    'json_dir' => BASE_PATH . '/runtime/swagger/',
    'html' => '/swagger',
    'url' => '/swagger/openapi.json',
    'auto_generate' => true,
    'scan' => [
        'paths' => [BASE_PATH . '/app'],
    ],
];
```

### 3. 使用示例

```php
<?php
use HPlus\Route\Annotation\ApiController;
use HPlus\Route\Annotation\GetApi;
use HPlus\Route\Annotation\PostApi;
use HPlus\Validate\Annotations\RequestValidation;

#[ApiController(tag: 'User Management')]
class UserController
{
    #[GetApi(summary: '获取用户列表')]
    #[RequestValidation(queryRules: [
        'page' => 'integer|min:1',
        'size' => 'integer|between:1,100',
    ])]
    public function index() {}
    
    #[PostApi(summary: '创建用户')]
    #[RequestValidation(rules: [
        'name' => 'required|string|max:50',
        'email' => 'required|email',
    ])]
    public function create() {}
}
```

### 4. 访问文档

启动服务后访问：`http://localhost:9501/swagger`

## 📋 注解说明

### @ApiResponse

```php
use HPlus\Route\Annotation\ApiResponse;

#[GetApi]
#[ApiResponse(code: 200, description: '成功', schema: [
    'id' => 'integer',
    'name' => 'string',
])]
public function show($id) {}
```

### @ApiResponseExample

```php
use HPlus\Route\Annotation\ApiResponseExample;

#[GetApi]
#[ApiResponseExample(code: 200, example: [
    'code' => 0,
    'message' => 'success',
    'data' => ['id' => 1, 'name' => 'John']
])]
public function show($id) {}
```

### @RequestBody

```php
use HPlus\Route\Annotation\RequestBody;

#[PostApi]
#[RequestBody(
    description: '用户信息',
    required: true,
    example: [
        'name' => 'john',
        'email' => 'john@example.com'
    ]
)]
public function create() {}
```

## 🎯 验证规则转换

当安装了 `hyperf-plus/validate` 时，Swagger 会自动将验证规则转换为 OpenAPI 参数定义：

| 验证规则 | JSON Schema |
|---------|-------------|
| `required` | `required: true` |
| `string` | `type: string` |
| `integer` | `type: integer` |
| `numeric` | `type: number` |
| `boolean` | `type: boolean` |
| `array` | `type: array` |
| `email` | `format: email` |
| `url` | `format: uri` |
| `date` | `format: date` |
| `min:N` | `minimum: N` / `minLength: N` |
| `max:N` | `maximum: N` / `maxLength: N` |
| `between:M,N` | `minimum: M, maximum: N` |
| `in:a,b,c` | `enum: [a, b, c]` |

### 示例

```php
#[RequestValidation(rules: [
    'name' => 'required|string|between:2,50',
    'age' => 'integer|min:18|max:100',
    'status' => 'in:active,inactive',
])]
```

生成的 Schema：

```json
{
  "type": "object",
  "required": ["name"],
  "properties": {
    "name": {
      "type": "string",
      "minLength": 2,
      "maxLength": 50
    },
    "age": {
      "type": "integer",
      "minimum": 18,
      "maximum": 100
    },
    "status": {
      "type": "string",
      "enum": ["active", "inactive"]
    }
  }
}
```

## 🔧 配置详解

```php
return [
    'enable' => env('SWAGGER_ENABLE', true),
    'port' => 9501,
    'json_dir' => BASE_PATH . '/runtime/swagger/',
    'html' => '/swagger',
    'url' => '/swagger/openapi.json',
    'auto_generate' => true,
    
    // OpenAPI 信息
    'info' => [
        'title' => 'My API',
        'version' => '4.0.0',
        'description' => 'API Documentation',
        'contact' => [
            'name' => 'API Support',
            'email' => 'support@example.com',
        ],
    ],
    
    // 服务器
    'servers' => [
        ['url' => 'http://localhost:9501', 'description' => 'Development'],
        ['url' => 'https://api.example.com', 'description' => 'Production'],
    ],
    
    // 安全方案
    'security_schemes' => [
        'bearerAuth' => [
            'type' => 'http',
            'scheme' => 'bearer',
            'bearerFormat' => 'JWT',
        ],
    ],
    
    // 扫描配置
    'scan' => [
        'paths' => [BASE_PATH . '/app/Controller'],
        'ignore' => [BASE_PATH . '/app/Controller/AbstractController.php'],
    ],
];
```

## 🛠️ 命令行工具

```bash
# 生成文档
php bin/hyperf.php swagger:generate

# 清除缓存
php bin/hyperf.php swagger:clear

# 验证文档
php bin/hyperf.php swagger:validate

# 导出文档
php bin/hyperf.php swagger:export --format=yaml
```

## ⚡ 性能优化

### 懒加载 + 缓存机制

Swagger 文档**不在启动时生成**，而是在首次访问 `/swagger/json` 时才构建，并缓存结果：

```
启动服务
  └─→ 零开销（不构建文档）

访问 /swagger/json（第1次）
  └─→ SwaggerBuilder::build() 构建文档
  └─→ 缓存结果

访问 /swagger/json（第N次）
  └─→ 直接返回缓存（极快）
```

### 手动刷新缓存

如果代码有更新，需要重新生成文档：

```php
// 注入 SwaggerBuilder
#[Inject]
private SwaggerBuilder $swaggerBuilder;

// 清除缓存后重新构建
$this->swaggerBuilder->clearCache();
$newDoc = $this->swaggerBuilder->build();
```

## 🤝 组件协作

```
┌─────────────────┐
│  Route 注解      │  路由信息
│  @ApiController │────────────┐
│  @GetApi/PostApi│            │
└─────────────────┘            ▼
                         ┌─────────────────┐
                         │  SwaggerBuilder │
┌─────────────────┐      │                 │     ┌─────────────────┐
│  Validate 注解   │──────│  合并生成       │────▶│  OpenAPI JSON   │
│  @RequestValidation    │  OpenAPI 文档    │     │                 │
│  (可选)          │      │                 │     │  Swagger UI     │
└─────────────────┘      └─────────────────┘     └─────────────────┘
```

## 🧪 测试覆盖

```
tests/
├── Cases/
│   ├── AbstractTestCase.php    # 测试基类
│   └── SwaggerBuilderTest.php  # 构建器测试
└── bootstrap.php
```

运行测试：

```bash
composer test
```

## 📊 与 3.x 版本对比

| 特性 | 3.x | 4.0 |
|------|-----|-----|
| OpenAPI 版本 | 3.0.x | 3.1.1 |
| validate 依赖 | 硬依赖 | 软依赖（可选） |
| `dateType` 参数 | ✅ | ❌ 改为 `mode` |
| 规则转换 | 部分支持 | 完整支持 |

## 📝 最佳实践

1. **文档质量**
   - 为每个接口添加 `summary` 和 `description`
   - 提供请求和响应示例
   - 使用有意义的标签分组

2. **安全考虑**
   - 生产环境关闭 `auto_generate`
   - 限制文档访问权限
   - 不暴露敏感信息

3. **版本管理**
   - 使用版本前缀区分 API 版本
   - 标记废弃的接口 `deprecated: true`

## 🔗 相关链接

- [OpenAPI Specification](https://www.openapis.org/)
- [Swagger UI](https://swagger.io/tools/swagger-ui/)
- [Hyperf Documentation](https://hyperf.wiki/)

## 📄 License

MIT
