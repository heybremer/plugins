<?php
/**
 * JTL PageController includes this file before rendering the Smarty template.
 *
 * @var \JTL\Smarty\JTLSmarty $smarty
 * @var \JTL\Plugin\PluginInterface $plugin
 */

require_once \dirname(__DIR__) . '/src/MpkService.php';

use JTL\Helpers\Form;
use JTL\Shop;
use Plugin\MessePremiumKunden\MpkService;

$shopUrl = Shop::getURL();
$svc     = new MpkService(\dirname(__DIR__) . '/uploads');
$svc->ensureTables();
$set     = $svc->fetchSettings();

$smarty->assign('mpkCssUrl', $shopUrl . '/plugins/MessePremiumKunden/templates/css/messe-premium-kunden.css');
$smarty->assign('mpkJsUrl', $shopUrl . '/plugins/MessePremiumKunden/templates/js/messe-premium-kunden.js');
$smarty->assign('mpkRabatt', $set['rabatt']);
$smarty->assign('mpkGueltigTage', $set['gueltigTage']);
$smarty->assign('mpkMarken', $svc->loadMarken());
$smarty->assign('mpkMaxFotos', MpkService::MAX_FOTOS);
$smarty->assign('mpkMaxFotoKb', $set['maxFotoKb']);
$smarty->assign('mpkFormTs', \time());
$smarty->assign('mpkFormAction', '/messe-premium-kunden');
$smarty->assign('mpkModelleUrl', '/messe-premium-kunden-modelle');
$smarty->assign('mpkActive', (int)$set['active']);
$smarty->assign('mpkCsrf', \method_exists(Form::class, 'getTokenInput') ? Form::getTokenInput() : '');
