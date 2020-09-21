# hyperf swagger 

实现来源于 https://github.com/daodao97/apidog 本插件为了兼容hyperf-plus 做处理

hyperf-plus-swagger 注解自动生成文档，配合hyperf-plus/validate 验证器可自动根据验证规则、场景生成文档所需参数，无需手动编写doc文档
![Image 注解](./screenshot/2.png)
![Image 文档](./screenshot/3.png)
## 1、安装
```
composer require hyperf-plus/swagger
```
## 2、发布配置文件
```
php bin/hyperf.php vendor:publish hyperf-plus/swagger

默开启文档web访问，如需关闭，在 config/autoload/swagger.php 将enable设为false 
```

## 完成访问
### 通过以上配置后，启动项目访问
http://您的域名/swagger/index 即可直接展示

## 配置描述
```php
// config/autoload/swagger.php  swagger 基础信息
<?php
declare(strict_types=1);

return [
    'output_file' => BASE_PATH . '/runtime/swagger.json',
    'swagger' => '2.0',
    'enable' =>true, // 是否启用web访问
        // 忽略的hook, 非必须 用于忽略符合条件的接口, 将不会输出到上定义的文件中
     'ignore' => function($controller, $action) {
         return false;
     },
     // 自定义验证器错误码、错误描述字段
     'error_code' => 400,
     'http_status_code' => 400,
     'field_error_code' => 'code',
     'field_error_message' => 'message',
     // swagger 的基础配置
     'swagger' => [
         'swagger' => '2.0',
         'info' => [
             'description' => 'hyperf swagger api desc',
             'version' => '1.0.0',
             'title' => 'HYPERF API DOC',
         ],
         'host' => 'apidog.com',
         'schemes' => ['http'],
     ]
];
```

# 鸣谢
实现来源于 https://github.com/daodao97/apidog