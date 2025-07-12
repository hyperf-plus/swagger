# Swagger 多服务器支持指南

## 概述

Hyperf Plus Swagger 组件现已完全支持多服务器环境，能够为每个 HTTP 服务器生成独立的 API 文档。

## 功能特性

✅ **多服务器扫描** - 自动识别和处理多个HTTP服务器
✅ **独立文档生成** - 为每个服务器生成独立的API文档
✅ **智能服务器过滤** - 仅处理HTTP相关服务器（跳过如WebSocket、TCP等）
✅ **灵活文件命名** - 支持`{server}`变量或自动后缀
✅ **忽略回调** - 支持过滤不需要的控制器和方法
✅ **完整错误处理** - 详细的日志记录和异常处理

## 配置示例

### 基础配置

```php
<?php
// config/autoload/swagger.php
return [
    'enable' => true,
    
    // 使用 {server} 变量
    'output_file' => BASE_PATH . '/runtime/swagger/api-docs-{server}.json',
    
    // 忽略回调
    'ignore' => function ($controller, $action) {
        // 忽略测试控制器
        if (str_contains($controller, 'TestController')) {
            return true;
        }
        return false;
    },
];
```

### 多服务器配置

```php
<?php
// config/autoload/server.php
return [
    'servers' => [
        [
            'name' => 'http',
            'type' => Server::class,
            'host' => '0.0.0.0',
            'port' => 9501,
            // ...
        ],
        [
            'name' => 'https', 
            'type' => Server::class,
            'host' => '0.0.0.0',
            'port' => 9502,
            // ...
        ],
        [
            'name' => 'http-admin',
            'type' => Server::class,
            'host' => '0.0.0.0',
            'port' => 9503,
            // ...
        ],
        [
            'name' => 'ws', // 这个会被自动跳过
            'type' => \Hyperf\WebSocketServer\Server::class,
            'host' => '0.0.0.0',
            'port' => 9504,
            // ...
        ],
    ],
];
```

## 输出文件命名

### 使用 `{server}` 变量

```php
'output_file' => BASE_PATH . '/runtime/swagger/api-docs-{server}.json',
```

生成的文件：
- `api-docs-http.json`
- `api-docs-https.json`
- `api-docs-http-admin.json`

### 自动后缀（当未使用 `{server}` 变量时）

```php
'output_file' => BASE_PATH . '/runtime/swagger/api-docs.json',
```

生成的文件：
- `api-docs_http.json`
- `api-docs_https.json`  
- `api-docs_http-admin.json`

## 忽略回调配置

```php
'ignore' => function ($controller, $action) {
    // 1. 忽略魔术方法
    if (str_starts_with($action, '__')) {
        return true;
    }
    
    // 2. 忽略特定控制器
    $ignoreControllers = [
        'App\\Controller\\AbstractController',
        'App\\Controller\\TestController',
        'App\\Controller\\HealthController',
    ];
    
    if (in_array($controller, $ignoreControllers)) {
        return true;
    }
    
    // 3. 忽略特定方法
    $ignoreMethods = ['test', 'debug', 'internal'];
    if (in_array($action, $ignoreMethods)) {
        return true;
    }
    
    // 4. 忽略私有API（基于命名约定）
    if (str_starts_with($action, 'private') || str_starts_with($action, '_')) {
        return true;
    }
    
    return false;
},
```

## 服务器过滤规则

组件会自动识别HTTP服务器：

✅ **包含的服务器类型：**
- `http` - 标准HTTP服务器
- `https` - HTTPS服务器
- `http-*` - 以"http"开头的服务器（如 `http-admin`, `http-api`）
- `https-*` - 以"https"开头的服务器

❌ **排除的服务器类型：**
- `ws` - WebSocket服务器
- `tcp` - TCP服务器
- `udp` - UDP服务器
- 其他非HTTP协议服务器

## 启动日志示例

```bash
[2024-01-01 12:00:00] INFO: Starting Swagger document generation...
[2024-01-01 12:00:00] INFO: Processing server: http
[2024-01-01 12:00:00] INFO: Generated 25 API paths for server: http
[2024-01-01 12:00:00] INFO: Swagger documentation for server 'http' saved to: /app/runtime/swagger/api-docs-http.json
[2024-01-01 12:00:00] INFO: Processing server: https
[2024-01-01 12:00:00] INFO: Generated 25 API paths for server: https
[2024-01-01 12:00:00] INFO: Swagger documentation for server 'https' saved to: /app/runtime/swagger/api-docs-https.json
[2024-01-01 12:00:00] INFO: Successfully generated Swagger documentation for 2 servers with total 50 API paths
```

## 故障排除

### 1. 没有找到HTTP服务器

```bash
[WARNING] No HTTP servers found in configuration
```

**解决方案：**
- 检查 `config/autoload/server.php` 中是否配置了HTTP服务器
- 确保服务器名称符合命名规则（http、https或以http开头）

### 2. 没有生成API路径

```bash
[WARNING] No API paths found for server: http. Please check your controllers have @ApiController annotations.
```

**解决方案：**
- 确保控制器使用了 `@ApiController` 注解
- 检查控制器方法是否使用了路由注解（`@GetApi`, `@PostApi` 等）
- 确认扫描路径包含您的控制器目录

### 3. 忽略回调异常

```bash
[WARNING] Ignore callback failed for App\Controller\UserController::index: Call to undefined function
```

**解决方案：**
- 检查忽略回调函数的语法
- 确保回调函数中使用的函数和类存在
- 添加try-catch异常处理

### 4. 文件保存失败

```bash
[ERROR] Failed to save Swagger documentation for server 'http' to: /app/runtime/swagger/api-docs-http.json
```

**解决方案：**
- 检查目录权限
- 确保runtime目录可写
- 检查磁盘空间

## 访问文档

生成文档后，您可以通过以下方式访问：

1. **Swagger UI界面：** `http://localhost:9501/swagger`
2. **JSON接口：** `http://localhost:9501/swagger/json`
3. **生成的文件：** `/runtime/swagger/api-docs-{server}.json`

## 高级配置

### 禁用多服务器功能

如果您只需要为单个服务器生成文档，可以这样配置：

```php
'ignore' => function ($controller, $action) {
    // 根据当前请求的服务器进行过滤
    $currentServer = \Hyperf\Context\Context::get('server_name', 'http');
    
    if ($currentServer !== 'http') {
        return true; // 只为http服务器生成文档
    }
    
    return false;
},
```

### 自定义文档内容

您可以根据服务器类型定制文档内容：

```php
// 在控制器中
#[ApiController(tag: 'Admin API', prefix: '/admin')]
class AdminController 
{
    #[GetApi(summary: '管理员专用接口')]
    public function index() {}
}

#[ApiController(tag: 'Public API', prefix: '/api')]
class PublicController
{
    #[GetApi(summary: '公开接口')]
    public function index() {}
}
```

这样可以为不同的服务器生成不同用途的API文档。 