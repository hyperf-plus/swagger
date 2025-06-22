<?php

declare(strict_types=1);

namespace HPlus\Swagger;

use Hyperf\Contract\ConfigInterface;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\HttpServer\Annotation\AutoController;
use Hyperf\Di\Annotation\Inject;
use Throwable;

#[AutoController(prefix: '/swagger')]
class Swagger
{
    #[Inject]
    private SwaggerBuilder $swaggerBuilder;
    
    public function __construct(
        private ConfigInterface $config,
        private ResponseInterface $response
    ) {}

    /**
     * Swagger UI 界面
     */
    public function index(?string $server = null)
    {
        if (!$this->config->get('swagger.enable', false)) {
            return $this->notFound('Swagger UI is disabled');
        }

        $uiConfig = $this->config->get('swagger.ui', []);
        $title = $uiConfig['title'] ?? 'API Documentation';
        $cdnUrl = $uiConfig['cdn_url'] ?? 'https://cdn.jsdelivr.net/npm/swagger-ui-dist@5.17.2';
        $customCss = $uiConfig['custom_css'] ?? '';
        $customJs = $uiConfig['custom_js'] ?? '';

        $html = <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
  <head>
    <meta charset="UTF-8">
    <title>{$title}</title>
    <link rel="stylesheet" type="text/css" href="{$cdnUrl}/swagger-ui.css" />
    <link rel="icon" type="image/png" href="{$cdnUrl}/favicon-32x32.png" sizes="32x32" />
    <link rel="icon" type="image/png" href="{$cdnUrl}/favicon-16x16.png" sizes="16x16" />
    <style>
        html { box-sizing: border-box; overflow: -moz-scrollbars-vertical; overflow-y: scroll; }
        *, *:before, *:after { box-sizing: inherit; }
        body { margin:0; background: #fafafa; }
        {$customCss}
    </style>
  </head>
  <body>
    <div id="swagger-ui"></div>
    <script src="{$cdnUrl}/swagger-ui-bundle.js"></script>
    <script src="{$cdnUrl}/swagger-ui-standalone-preset.js"></script>
    <script>
    window.onload = function() {
      const ui = SwaggerUIBundle({
                url: '/swagger/json',
                dom_id: '#swagger-ui',
        deepLinking: true,
        presets: [
          SwaggerUIBundle.presets.apis,
          SwaggerUIStandalonePreset
        ],
        plugins: [
          SwaggerUIBundle.plugins.DownloadUrl
        ],
                layout: "StandaloneLayout",
                tryItOutEnabled: true,
                docExpansion: 'list',
                filter: true,
                defaultModelsExpandDepth: 2,
                defaultModelExpandDepth: 2
            });
        };
        {$customJs}
  </script>
  </body>
</html>
HTML;

        return $this->response->withBody(new SwooleStream($html))
            ->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
    
    /**
     * 获取 OpenAPI JSON 文档
     */
    public function json()
    {
        if (!$this->config->get('swagger.enable', false)) {
            return $this->notFound('Swagger is disabled');
        }

        try {
            $openapi = $this->swaggerBuilder->build();
            
            return $this->response->json($openapi)
                ->withHeader('Access-Control-Allow-Origin', '*')
                ->withHeader('Access-Control-Allow-Methods', 'GET, OPTIONS')
                ->withHeader('Access-Control-Allow-Headers', 'Content-Type');
        } catch (\Throwable $e) {
            return $this->response->json([
                'error' => 'Failed to generate OpenAPI documentation',
                'message' => $e->getMessage(),
                'code' => $e->getCode()
            ])->withStatus(500);
        }
    }

    /**
     * Swagger UI（兼容性接口）
     */
    public function ui()
    {
        return $this->index();
    }

    /**
     * OAuth2 重定向页面
     */
    public function oauth2Redirect()
    {
        $html = <<<HTML
<!doctype html>
<html lang="en-US">
<head>
    <title>Swagger UI: OAuth2 Redirect</title>
</head>
<body>
<script>
    'use strict';
    function run () {
        var oauth2 = window.opener.swaggerUIRedirectOauth2;
        var sentState = localStorage.getItem("scribe_oauth2_state");
        var redirectUrl = oauth2.redirectUrl;
        var isValid, qp, arr;

        if (/code|token|error/.test(window.location.hash)) {
            qp = window.location.hash.substring(1);
        } else {
            qp = location.search.substring(1);
        }

        arr = qp.split("&");
        arr.forEach(function (v,i,_arr) { _arr[i] = '"' + v.replace('=', '":"') + '"';});
        qp = qp ? JSON.parse('{' + arr.join() + '}',
                function(key, value) {
                    return key === "" ? value : decodeURIComponent(value);
                }
        ) : {};

        isValid = qp.state === sentState;

        if ((
          oauth2.auth.schema.get("flow") === "accessCode" ||
          oauth2.auth.schema.get("flow") === "authorizationCode" ||
          oauth2.auth.schema.get("flow") === "authorization_code"
        ) && !oauth2.auth.code) {
            if (!isValid) {
                oauth2.errCb({
                    authId: oauth2.auth.name,
                    source: "auth",
                    level: "warning",
                    message: "Authorization may be unsafe, passed state was changed in server Passed state wasn't returned from auth server"
                });
            }

            if (qp.code) {
                delete oauth2.state;
                oauth2.auth.code = qp.code;
                oauth2.callback({auth: oauth2.auth, redirectUrl: redirectUrl});
            } else {
                let oauthErrorMsg;
                if (qp.error) {
                    oauthErrorMsg = "["+qp.error+"]: " +
                        (qp.error_description ? qp.error_description+ ". " : "no accessCode received from the server. ") +
                        (qp.error_uri ? "More info: "+qp.error_uri : "");
                }

                oauth2.errCb({
                    authId: oauth2.auth.name,
                    source: "auth",
                    level: "error",
                    message: oauthErrorMsg || "[Authorization failed]: no accessCode received from the server"
                });
            }
        } else {
            oauth2.callback({auth: oauth2.auth, token: qp, isValid: isValid, redirectUrl: redirectUrl});
        }
        window.close();
    }

    window.addEventListener('DOMContentLoaded', function () {
        run();
    });
</script>
</body>
</html>
HTML;

        return $this->response->withBody(new SwooleStream($html))
            ->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * 404 响应
     */
    private function notFound(string $message): ResponseInterface
    {
        return $this->response->json([
            'error' => 'Not Found',
            'message' => $message
        ])->withStatus(404);
    }
}