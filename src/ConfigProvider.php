<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://doc.hyperf.io
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf-cloud/hyperf/blob/master/LICENSE
 */

namespace HPlus\Swagger;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => [
                SwaggerBuilder::class => SwaggerBuilder::class,
            ],
            'annotations' => [
                'scan' => [
                    'paths' => [
                        __DIR__,
                    ],
                ],
            ],
            'publish' => [
                [
                    'id' => 'swagger-config',
                    'description' => 'Swagger configuration file',
                    'source' => __DIR__ . '/../publish/swagger.php',
                    'destination' => BASE_PATH . '/config/autoload/swagger.php',
                ],
            ],
            'listeners' => [
                BootAppConfListener::class,
            ],
        ];
    }
}
