<?php
declare(strict_types=1);

namespace HPlus\Swagger;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\BootApplication;
use Hyperf\HttpServer\Router\Router;
use Psr\Container\ContainerInterface;

class BootAppConfListener implements ListenerInterface
{
    public function __construct(private ContainerInterface $container)
    {
    }

    public function listen(): array
    {
        return [
            BootApplication::class,
        ];
    }

    public function process(object $event): void
    {
        $config = $this->container->get(ConfigInterface::class);
        
        if (!$config->get('swagger.enable', false)) {
            return;
        }

        // 注册 Swagger 路由
        Router::addGroup('/swagger', function () {
            Router::get('/', [Swagger::class, 'index']);
            Router::get('/json', [Swagger::class, 'json']);
            Router::get('/ui', [Swagger::class, 'ui']);
            Router::get('/oauth2-redirect.html', [Swagger::class, 'oauth2Redirect']);
        });
    }
}
