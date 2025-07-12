<?php

declare(strict_types=1);

namespace HPlus\Swagger;

use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\BeforeServerStart;
use Hyperf\Logger\LoggerFactory;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Swagger 启动监听器
 * 替代原来的 BootAppConfListener，确保 Swagger 在服务启动时能够正确扫描路由
 */
class SwaggerBootListener implements ListenerInterface
{
    private LoggerInterface $logger;
    private ConfigInterface $config;
    private SwaggerBuilder $swaggerBuilder;

    public function __construct()
    {
        $container = ApplicationContext::getContainer();
        $this->logger = $container->get(LoggerFactory::class)->get('swagger');
        $this->config = $container->get(ConfigInterface::class);
        $this->swaggerBuilder = $container->get(SwaggerBuilder::class);
    }

    public function listen(): array
    {
        return [
            BeforeServerStart::class,
        ];
    }

    public function process(object $event): void
    {
        try {
            if (!$this->config->get('swagger.enable', false)) {
                $this->logger->debug('Swagger is disabled');
                return;
            }

            $this->logger->info('Starting Swagger document generation...');

            // 获取服务器配置
            $servers = $this->config->get('server.servers', []);
            $outputFile = $this->config->get('swagger.output_file');
            
            // 如果没有配置服务器，默认使用http
            if (empty($servers)) {
                $servers = [['name' => 'http']];
                $this->logger->info('No servers configured, using default HTTP server');
            }
            
            // 检查多服务器配置
            if (count($servers) > 1 && $outputFile && !str_contains($outputFile, '{server}')) {
                $this->logger->warning('You have multiple servers, but your swagger.output_file does not contain {server} variable. Files will be named with server suffix.');
            }

            $totalPaths = 0;
            $processedServers = 0;
            $httpServers = [];

            // 筛选HTTP服务器
            foreach ($servers as $server) {
                $serverName = $server['name'] ?? 'http';
                
                // 只处理HTTP相关服务
                if ($this->isHttpServer($serverName)) {
                    $httpServers[] = $server;
                }
            }

            if (empty($httpServers)) {
                $this->logger->warning('No HTTP servers found in configuration');
                return;
            }

            // 为每个HTTP服务器生成文档
            foreach ($httpServers as $server) {
                $serverName = $server['name'] ?? 'http';
                
                $this->logger->info("Processing server: {$serverName}");

                try {
                    // 构建当前服务器的OpenAPI文档
                    $openapi = $this->swaggerBuilder->build();

                    if (empty($openapi['paths'])) {
                        $this->logger->warning("No API paths found for server: {$serverName}. Please check your controllers have @ApiController annotations.");
                        continue;
                    }

                    $pathCount = count($openapi['paths']);
                    $totalPaths += $pathCount;
                    $processedServers++;

                    $this->logger->info("Generated {$pathCount} API paths for server: {$serverName}");

                    // 保存到文件
                    if ($outputFile) {
                        $serverOutputFile = $this->resolveServerOutputFile($outputFile, $serverName);
                        $this->saveToFile($openapi, $serverOutputFile, $serverName);
                    }
                } catch (Throwable $e) {
                    $this->logger->error("Failed to process server '{$serverName}': " . $e->getMessage());
                    continue;
                }
            }

            if ($processedServers === 0) {
                $this->logger->warning('No servers processed successfully');
            } else {
                $this->logger->info("Successfully generated Swagger documentation for {$processedServers} servers with total {$totalPaths} API paths");
            }

        } catch (Throwable $e) {
            $this->logger->error('Failed to generate Swagger documentation: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * 判断是否为HTTP服务器
     */
    private function isHttpServer(string $serverName): bool
    {
        // 标准HTTP服务器名称
        $httpServerNames = ['http', 'https'];
        
        // 检查是否是标准HTTP服务器
        if (in_array($serverName, $httpServerNames)) {
            return true;
        }
        
        // 检查是否以http开头（如http-admin, https-api等）
        if (str_starts_with($serverName, 'http')) {
            return true;
        }
        
        return false;
    }

    /**
     * 解析服务器输出文件路径
     */
    private function resolveServerOutputFile(string $outputFile, string $serverName): string
    {
        if (str_contains($outputFile, '{server}')) {
            return str_replace('{server}', $serverName, $outputFile);
        }
        
        // 如果没有{server}变量，在文件名前添加服务器名
        $pathInfo = pathinfo($outputFile);
        $dirname = $pathInfo['dirname'];
        $filename = $pathInfo['filename'];
        $extension = $pathInfo['extension'] ?? 'json';
        
        return $dirname . DIRECTORY_SEPARATOR . $filename . '_' . $serverName . '.' . $extension;
    }

    /**
     * 保存文档到文件
     */
    private function saveToFile(array $openapi, string $outputFile, string $serverName = 'http'): void
    {
        try {
            // 确保目录存在
            $dir = dirname($outputFile);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            // 应用ignore回调过滤
            $ignoreCallback = $this->config->get('swagger.ignore');
            if ($ignoreCallback && is_callable($ignoreCallback)) {
                $openapi = $this->applyIgnoreCallback($openapi, $ignoreCallback);
            }

            // 生成格式化的 JSON
            $json = json_encode($openapi, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            
            if (file_put_contents($outputFile, $json) !== false) {
                $this->logger->info("Swagger documentation for server '{$serverName}' saved to: {$outputFile}");
            } else {
                $this->logger->error("Failed to save Swagger documentation for server '{$serverName}' to: {$outputFile}");
            }
        } catch (Throwable $e) {
            $this->logger->error("Failed to save Swagger file for server '{$serverName}': " . $e->getMessage());
        }
    }

    /**
     * 应用ignore回调过滤
     */
    private function applyIgnoreCallback(array $openapi, callable $ignoreCallback): array
    {
        if (empty($openapi['paths'])) {
            return $openapi;
        }

        $filteredPaths = [];
        $ignoredCount = 0;
        
        foreach ($openapi['paths'] as $path => $pathItem) {
            $filteredPathItem = [];
            
            foreach ($pathItem as $method => $operation) {
                // 从operationId中提取controller和action
                $operationId = $operation['operationId'] ?? '';
                if ($operationId && str_contains($operationId, '::')) {
                    [$controller, $action] = explode('::', $operationId, 2);
                    
                    // 应用ignore回调
                    try {
                        if (!$ignoreCallback($controller, $action)) {
                            $filteredPathItem[$method] = $operation;
                        } else {
                            $ignoredCount++;
                        }
                    } catch (Throwable $e) {
                        $this->logger->warning("Ignore callback failed for {$controller}::{$action}: " . $e->getMessage());
                        // 回调失败时保留该操作
                        $filteredPathItem[$method] = $operation;
                    }
                } else {
                    // 如果无法解析，保留该操作
                    $filteredPathItem[$method] = $operation;
                }
            }
            
            // 只有当路径项不为空时才保留
            if (!empty($filteredPathItem)) {
                $filteredPaths[$path] = $filteredPathItem;
            }
        }
        
        if ($ignoredCount > 0) {
            $this->logger->info("Ignored {$ignoredCount} API operations via ignore callback");
        }
        
        $openapi['paths'] = $filteredPaths;
        return $openapi;
    }
} 