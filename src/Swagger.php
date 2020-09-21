<?php

declare(strict_types=1);

namespace HPlus\Swagger;

use GuzzleHttp\Psr7\Stream;
use Hyperf\Contract\ConfigInterface;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\Utils\ApplicationContext;
use Psr\Http\Message\StreamInterface;


/**
 * @\Hyperf\HttpServer\Annotation\AutoController(prefix="/swagger")
 * Class SwaggerController
 */
class Swagger
{
    /**
     * @var ConfigInterface $config
     */
    protected $config;

    public function __construct()
    {
        $this->config = ApplicationContext::getContainer()->get(ConfigInterface::class);
    }

    public function index()
    {
        if (!$this->config->get('swagger.enable', false)) {
            return 'swagger not start';
        }
        $domain = $this->config->get('swagger.host', '');
        $url = $domain . '/swagger/api?s=' . time();
        $res = ApplicationContext::getContainer()->get(ResponseInterface::class);
        $html = '
<!-- HTML for static distribution bundle build -->
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <title>Swagger UI</title>
    <link rel="stylesheet" type="text/css" href="https://cdn.staticfile.org/swagger-ui/3.31.1/swagger-ui.min.css" >
    <link rel="icon" type="image/png" href="https://mail.qq.com/zh_CN/htmledition/images/favicon/qqmail_favicon_48h.png" sizes="32x32" />
    <style>
      html
      {
        box-sizing: border-box;
        overflow: -moz-scrollbars-vertical;
        overflow-y: scroll;
      }

      *,
      *:before,
      *:after
      {
        box-sizing: inherit;
      }

      body
      {
        margin:0;
        background: #fafafa;
      }
    </style>
  </head>

  <body>
    <div id="swagger-ui"></div>
        <script src="//cdn.staticfile.org/swagger-ui/3.31.1/swagger-ui-bundle.min.js"> </script>
    <script src="//cdn.staticfile.org/swagger-ui/3.31.1/swagger-ui-standalone-preset.min.js"> </script>
    <script>
    window.onload = function() {
      // Begin Swagger UI call region
      const ui = SwaggerUIBundle({
        url: "' . $url . '?t="+(new Date()).getDate(),
        dom_id: \'#swagger-ui\',
        deepLinking: true,
        presets: [
          SwaggerUIBundle.presets.apis,
          SwaggerUIStandalonePreset
        ],
        plugins: [
          SwaggerUIBundle.plugins.DownloadUrl
        ],
        layout: "StandaloneLayout"
      })
      // End Swagger UI call region

      window.ui = ui
    }
  </script>
  </body>
</html>
';
        return $res->withBody(new SwooleStream($html))->withHeader('content-type', 'text/html; charset=utf8');
    }

    public function api()
    {
        if (!$this->config->get('swagger.enable', false)) {
            return 'swagger not start';
        }
        $domain = $this->config->get('swagger.output_file', '');
        return json_decode(file_get_contents($domain), true);
    }
}