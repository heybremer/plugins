<?php

namespace Plugin\SideLeftPanel;

use JTL\Events\Dispatcher;
use JTL\Plugin\Bootstrapper;
use JTL\Shop;

class Bootstrap extends Bootstrapper
{
    public function boot(Dispatcher $dispatcher): void
    {
        parent::boot($dispatcher);

        $dispatcher->hookInto(\HOOK_LETZTERINCLUDE_CSS_JS, function (array &$args): void {
            // Only load on frontend pages where the sidepanel is typically used
            $pageType = Shop::getPageType();
            $allowedPages = [];
            foreach ([
                'PAGE_STARTSEITE',
                'PAGE_ARTIKELLISTE',
                'PAGE_LIVESUCHE',
                'PAGE_HERSTELLER',
                'PAGE_EIGENE_SEITE'
            ] as $const) {
                if (\defined($const)) {
                    $allowedPages[] = \constant($const);
                }
            }

            if (!\in_array($pageType, $allowedPages, true)) {
                return;
            }

            if (!isset($args['cPluginCss_arr']) || !\is_array($args['cPluginCss_arr'])) {
                $args['cPluginCss_arr'] = [];
            }
            if (!isset($args['cPluginJsHead_arr']) || !\is_array($args['cPluginJsHead_arr'])) {
                $args['cPluginJsHead_arr'] = [];
            }

            $cssPath = 'plugins/SideLeftPanel/templates/css/sideleftpanel.css';
            if (!\in_array($cssPath, $args['cPluginCss_arr'], true)) {
                $args['cPluginCss_arr'][] = $cssPath;
            }

            $jsPath = 'plugins/SideLeftPanel/templates/js/sideleftpanel.js';
            if (!\in_array($jsPath, $args['cPluginJsHead_arr'], true)) {
                $args['cPluginJsHead_arr'][] = $jsPath;
            }
        });

        // Inject settings into the sidepanel HTML
        $dispatcher->hookInto(\HOOK_SMARTY_OUTPUTFILTER, function (array &$args): void {
            if (!isset($args['document'])) {
                return;
            }

            $config = $this->getPlugin()->getConfig();
            $accordion = (int)$config->getOption('slp_accordion_active');
            $blur = $config->getOption('slp_glass_blur') ?: '12px';
            $opacity = $config->getOption('slp_glass_opacity') ?: '0.75';

            $doc = $args['document'];
            $root = $doc->find('#sidepanel_left')->eq(0);
            if ($root->length > 0) {
                $val = $accordion ? '1' : '0';
                $root->attr('data-slp-accordion', $val);
                $root->attr('style', ($root->attr('style') ?: '') 
                    . " --slp-blur: {$blur}; --slp-opacity: {$opacity};");
            }
        });
    }
}

