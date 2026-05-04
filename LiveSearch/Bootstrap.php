<?php

namespace Plugin\LiveSearch;

require_once __DIR__ . '/src/Controller/LiveSearchController.php';

use JTL\Events\Dispatcher;
use JTL\Plugin\Bootstrapper;
use JTL\Router\Router;
use JTL\Smarty\JTLSmarty;
use Plugin\LiveSearch\Controller\LiveSearchController;
use Psr\Http\Message\ServerRequestInterface;

class Bootstrap extends Bootstrapper
{
    private const JS_PATH = 'plugins/LiveSearch/templates/js/livesearch.js';

    /**
     * Assets und Route /livesearch registrieren.
     *
     * @param Dispatcher $dispatcher
     */
    public function boot(Dispatcher $dispatcher): void
    {
        parent::boot($dispatcher);

        $dispatcher->hookInto(\HOOK_LETZTERINCLUDE_CSS_JS, function (array &$args): void {
            if (!isset($args['cPluginJsHead_arr']) || !\is_array($args['cPluginJsHead_arr'])) {
                $args['cPluginJsHead_arr'] = [];
            }
            if (!\in_array(self::JS_PATH, $args['cPluginJsHead_arr'], true)) {
                $args['cPluginJsHead_arr'][] = self::JS_PATH;
            }
        });

        $dispatcher->hookInto(\HOOK_ROUTER_PRE_DISPATCH, function (array $args): void {
            if (!isset($args['router']) || !($args['router'] instanceof Router)) {
                return;
            }
            /** @var Router $router */
            $router = $args['router'];

            $router->addRoute(
                '/livesearch',
                /**
                 * @param ServerRequestInterface $request
                 * @param array                  $routeArgs
                 * @param JTLSmarty              $smarty
                 */
                function (ServerRequestInterface $request, array $routeArgs, JTLSmarty $smarty) {
                    $controller = new LiveSearchController();

                    return $controller->search($request, $routeArgs);
                },
                'livesearch',
                ['GET']
            );
        });
    }
}

