<?php
/**
 * JTL PageController includes this file before rendering the Smarty template.
 * $smarty and $plugin are available as globals here.
 */

/** @var \JTL\Smarty\JTLSmarty $smarty */
/** @var \JTL\Plugin\PluginInterface $plugin */

$shopUrl = \JTL\Shop::getURL();

$smarty->assign('asfCssUrl', $shopUrl . '/plugins/AirbagSafeFix/templates/css/airbag-safe-fix.css');
$smarty->assign('asfJsUrl',  $shopUrl . '/plugins/AirbagSafeFix/templates/js/airbag-safe-fix.js');
