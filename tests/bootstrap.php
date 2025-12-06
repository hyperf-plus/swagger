<?php

declare(strict_types=1);

// 优先使用 app 的 vendor（如果存在），否则使用自己的
$vendorPath = dirname(__DIR__, 2) . '/app/vendor/autoload.php';
if (!file_exists($vendorPath)) {
    $vendorPath = dirname(__DIR__) . '/vendor/autoload.php';
}
require_once $vendorPath;

// 确保错误报告级别
error_reporting(E_ALL);
ini_set('display_errors', '1');

// 设置时区
date_default_timezone_set('UTC');

// 创建基础路径常量
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__, 1));
}

echo "HPlus Swagger Test Suite Bootstrap Complete\n";
