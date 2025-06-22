# HPlus Swagger - 智能API文档生成组件

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.0-8892BF.svg)](https://php.net)
[![Hyperf Version](https://img.shields.io/badge/hyperf-%3E%3D3.0-brightgreen.svg)](https://hyperf.io)
[![OpenAPI](https://img.shields.io/badge/OpenAPI-3.1.1-green.svg)](https://www.openapis.org/)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

一个为 Hyperf 框架打造的智能 API 文档生成组件，支持 OpenAPI 3.1.1 规范，自动集成路由和验证信息，生成美观的交互式文档。

## ✨ 核心特性

- 📝 **自动文档生成** - 基于注解自动生成 OpenAPI 文档
- 🔄 **智能集成** - 自动识别 Route 和 Validate 组件信息
- 🎨 **美观界面** - 集成 Swagger UI，支持在线测试
- 📐 **完整规范** - 支持 OpenAPI 3.1.1 最新规范
- 🚀 **高性能** - 文档缓存、增量更新
- 🔧 **灵活配置** - 支持多文档版本、分组管理

## 📦 安装

```bash
composer require hyperf-plus/swagger
```

### ✅ 兼容性说明

**本包支持无缝升级**，完全向后兼容。主要改进：
- 保持所有注解和配置的兼容性
- 增强了对 Route 包新特性的支持（如智能参数识别）
- 优化了性能，但不改变任何公共接口
- 自动适配 Route 包的 RESTful 增强特性

**注意**：如果同时使用 Route 包，建议查看 Route 包的升级说明，因为路由生成规则有重大变化。

## 🚀 快速开始

### 1. 发布配置

```bash
php bin/hyperf.php vendor:publish hplus/swagger
```

### 2. 基础配置

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
use HPlus\Swagger\Annotation\ApiDefinition;
use HPlus\Swagger\Annotation\ApiServer;

#[ApiController(tag: 'User Management')]
#[ApiServer(url: 'http://localhost:9501', description: 'Development Server')]
class UserController
{
    #[GetApi(summary: '获取用户列表', description: '支持分页和搜索')]
    #[RequestValidation(rules: [
        'page|页码' => 'integer|min:1|default:1',
        'size|每页数量' => 'integer|min:1|max:100|default:20',
        'keyword|搜索关键词' => 'string|max:50'
    ])]
    public function index() {}
    
    #[PostApi(summary: '创建用户')]
    #[RequestValidation(
        rules: [
            'username|用户名' => 'required|string|min:3|max:20',
            'email|邮箱' => 'required|email',
            'password|密码' => 'required|string|min:6'
        ],
        dateType: 'json'
    )]
    public function create() {}
}
```

### 4. 访问文档

启动服务后访问：`http://localhost:9501/swagger`

## 📋 注解说明

### @ApiDefinition

定义数据模型（Schema）：

```php
#[ApiDefinition(
    name: 'User',
    type: 'object',
    description: '用户模型',
    properties: [
        'id' => ['type' => 'integer', 'description' => '用户ID'],
        'username' => ['type' => 'string', 'description' => '用户名'],
        'email' => ['type' => 'string', 'format' => 'email'],
        'profile' => [
            'type' => 'object',
            'properties' => [
                'nickname' => ['type' => 'string'],
                'avatar' => ['type' => 'string', 'format' => 'uri']
            ]
        ],
        'status' => ['type' => 'string', 'enum' => ['active', 'inactive']],
        'created_at' => ['type' => 'string', 'format' => 'date-time']
    ],
    required: ['id', 'username', 'email']
)]
class UserSchema {}
```

### @ApiServer

定义服务器信息：

```php
#[ApiServer(
    url: 'https://api.example.com',
    description: 'Production Server',
    variables: [
        'version' => [
            'default' => 'v1',
            'enum' => ['v1', 'v2'],
            'description' => 'API Version'
        ]
    ]
)]
```

### @ApiCallback

定义回调信息：

```php
#[ApiCallback(
    name: 'onUserCreated',
    url: '{$request.body#/callback_url}',
    method: 'POST',
    requestBody: [
        'user_id' => 'integer',
        'event' => 'string'
    ]
)]
```

### @ApiLink

定义链接关系：

```php
#[ApiLink(
    name: 'GetUserById',
    operationId: 'getUser',
    parameters: [
        'id' => '$response.body#/id'
    ]
)]
```

## 🎯 高级用法

### 1. 响应示例

使用 `@ApiResponse` 和 `@ApiResponseExample`：

```php
use HPlus\Route\Annotation\ApiResponse;
use HPlus\Route\Annotation\ApiResponseExample;

#[GetApi]
#[ApiResponse(code: 200, description: '成功')]
#[ApiResponseExample(
    code: 200,
    example: [
        'code' => 0,
        'message' => 'success',
        'data' => [
            'id' => 1,
            'username' => 'john_doe'
        ]
    ]
)]
public function show($id) {}
```

### 2. 请求体示例

```php
use HPlus\Route\Annotation\RequestBody;

#[PostApi]
#[RequestBody(
    description: '用户信息',
    required: true,
    example: [
        'username' => 'john_doe',
        'email' => 'john@example.com',
        'password' => 'secret123'
    ]
)]
public function create() {}
```

### 3. 文件上传

```php
#[PostApi(summary: '上传头像')]
#[RequestValidation(
    rules: [
        'avatar' => 'required|file|image|max:2048'
    ],
    dateType: 'form'
)]
public function uploadAvatar() {}
```

### 4. 安全认证

```php
// 全局安全配置
#[ApiController(security: true)]
class SecureController {}

// 方法级别配置
#[GetApi(security: true)]
public function privateData() {}

// 在配置文件中定义安全方案
'security_schemes' => [
    'bearerAuth' => [
        'type' => 'http',
        'scheme' => 'bearer',
        'bearerFormat' => 'JWT'
    ],
    'apiKey' => [
        'type' => 'apiKey',
        'in' => 'header',
        'name' => 'X-API-Key'
    ]
]
```

### 5. 分组和标签

```php
// 控制器级别标签
#[ApiController(tag: 'User Management', description: '用户管理相关接口')]

// 多标签支持
#[GetApi(tags: ['User', 'Admin'])]

// 标签描述（在配置中）
'tags' => [
    [
        'name' => 'User Management',
        'description' => '用户相关操作',
        'externalDocs' => [
            'description' => 'Find more info',
            'url' => 'https://example.com/docs/user'
        ]
    ]
]
```

## 🔧 配置详解

### 完整配置示例

```php
return [
    'enable' => env('SWAGGER_ENABLE', true),
    'port' => 9501,
    'json_dir' => BASE_PATH . '/runtime/swagger/',
    'html' => '/swagger',
    'url' => '/swagger/openapi.json',
    'auto_generate' => true,
    
    // OpenAPI 基础信息
    'info' => [
        'title' => 'My API',
        'version' => '1.0.0',
        'description' => 'API Documentation',
        'termsOfService' => 'https://example.com/terms',
        'contact' => [
            'name' => 'API Support',
            'email' => 'support@example.com',
            'url' => 'https://example.com/support'
        ],
        'license' => [
            'name' => 'MIT',
            'url' => 'https://opensource.org/licenses/MIT'
        ]
    ],
    
    // 服务器配置
    'servers' => [
        [
            'url' => 'http://localhost:9501',
            'description' => 'Development server'
        ],
        [
            'url' => 'https://api.example.com',
            'description' => 'Production server'
        ]
    ],
    
    // 安全方案
    'security_schemes' => [
        'bearerAuth' => [
            'type' => 'http',
            'scheme' => 'bearer',
            'bearerFormat' => 'JWT'
        ]
    ],
    
    // 扫描配置
    'scan' => [
        'paths' => [
            BASE_PATH . '/app/Controller',
        ],
        'ignore' => [
            BASE_PATH . '/app/Controller/AbstractController.php',
        ]
    ],
    
    // 外部文档
    'externalDocs' => [
        'description' => 'Find out more',
        'url' => 'https://example.com/docs'
    ]
];
```

## 🎨 UI 定制

### 自定义 UI 配置

```php
'ui' => [
    'title' => 'My API Documentation',
    'favicon' => '/favicon.ico',
    'css' => '/custom.css',
    'js' => '/custom.js',
    'theme' => 'dark', // light, dark
    'tryItOutEnabled' => true,
    'docExpansion' => 'list', // none, list, full
    'defaultModelsExpandDepth' => 1,
    'persistAuthorization' => true,
]
```

## 🚀 性能优化

1. **文档缓存**
   ```php
   'cache' => [
       'enable' => true,
       'ttl' => 3600, // 缓存时间（秒）
       'dir' => BASE_PATH . '/runtime/swagger/cache/'
   ]
   ```

2. **增量更新**
   - 只更新修改的控制器
   - 智能检测文件变化

3. **生产环境优化**
   ```php
   'production' => [
       'enable' => false, // 生产环境关闭自动生成
       'cache_forever' => true, // 永久缓存
   ]
   ```

## 🤝 与其他组件协作

### Route 组件集成

- 自动识别所有路由注解
- 提取路径、方法、参数信息
- 支持 RESTful 和自定义路径

### Validate 组件集成

- 自动转换验证规则为参数定义
- 生成请求体 Schema
- 提取字段描述和示例

### 集成流程

```
Route 注解 → 路由信息提取 ↘
                          → Swagger 文档生成 → OpenAPI JSON → Swagger UI
Validate 注解 → 参数信息提取 ↗
```

## 📝 最佳实践

1. **文档质量**
   - 为每个接口添加 summary 和 description
   - 提供请求和响应示例
   - 使用有意义的标签分组

2. **版本管理**
   - 使用版本前缀区分 API 版本
   - 保持向后兼容
   - 标记废弃的接口

3. **安全考虑**
   - 生产环境关闭自动生成
   - 限制文档访问权限
   - 不暴露敏感信息

## 🐛 问题排查

1. **文档不更新**
   - 清除缓存：`php bin/hyperf.php swagger:clear`
   - 检查自动生成是否开启
   - 手动生成：`php bin/hyperf.php swagger:generate`

2. **接口未显示**
   - 确认控制器有 `@ApiController` 注解
   - 检查扫描路径配置
   - 验证注解语法正确

3. **参数信息缺失**
   - 确认 Validate 组件已安装
   - 检查验证规则格式
   - 查看生成的 JSON 文件

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

## 📄 许可证

MIT License

## 🤝 贡献

欢迎提交 Issue 和 Pull Request！

## 🔗 相关链接

- [OpenAPI Specification](https://www.openapis.org/)
- [Swagger UI](https://swagger.io/tools/swagger-ui/)
- [Hyperf Documentation](https://hyperf.wiki/)