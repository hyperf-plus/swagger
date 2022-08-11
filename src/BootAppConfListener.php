<?php
declare(strict_types=1);

namespace HPlus\Swagger;

use HPlus\Swagger\Swagger\SwaggerJson;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\BeforeServerStart;
use Hyperf\HttpServer\Router\DispatcherFactory;
use Hyperf\HttpServer\Router\Handler;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Utils\Str;

class BootAppConfListener implements ListenerInterface
{
    public function listen(): array
    {
        return [
            BeforeServerStart::class,
        ];
    }

    public function process(object $event):void
    {
        $container = ApplicationContext::getContainer();
        $logger = $container->get(LoggerFactory::class)->get('swagger');
        $config = $container->get(ConfigInterface::class);
        if (!$config->get('swagger.enable')) {
            $logger->debug('swagger not enable');
            return;
        }
        $output = $config->get('swagger.output_file');
        if (!$output) {
            $logger->error('/config/autoload/swagger.php need set output_file');
            return;
        }
        $router = $container->get(DispatcherFactory::class)->getRouter('http');
        $data = $router->getData();
        $servers = $config->get('server.servers');
        if (count($servers) > 1 && !Str::contains($output, '{server}')) {
            $logger->warning('You have multiple serve, but your swagger.output_file not contains {server} var');
        }
        foreach ($servers as $server) {
            $swagger = new SwaggerJson($server['name']);
            #跳过非http的服务
            if ($server['name'] != 'http'){
                continue;
            }
            
            $ignore = $config->get('swagger.ignore', function ($controller, $action) {
                return false;
            });
            array_walk_recursive($data, function ($item) use ($swagger, $ignore) {
                if ($item instanceof Handler && !($item->callback instanceof \Closure)) {
                    [$controller, $action] = $this->prepareHandler($item->callback);




                    (!$ignore($controller, $action)) && $swagger->addPath($controller, $action, $item->route);
                }
            });

            $swagger->save();
        }
    }

    protected function prepareHandler($handler): array
    {
        if (is_string($handler)) {
            if (strpos($handler, '@') !== false) {
                return explode('@', $handler);
            }
            return explode('::', $handler);
        }
        if (is_array($handler) && isset($handler[0], $handler[1])) {
            return $handler;
        }
        throw new \RuntimeException('Handler not exist.');
    }
}
