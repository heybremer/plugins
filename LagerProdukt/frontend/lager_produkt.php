<?php
/**
 * JTL PageController includes this file before rendering the Smarty template.
 *
 * @var \JTL\Smarty\JTLSmarty $smarty
 * @var \JTL\Plugin\PluginInterface $plugin
 */

require_once \dirname(__DIR__) . '/src/LagerProduktService.php';

use JTL\Shop;
use Plugin\LagerProdukt\LagerProduktService;

$shopUrl = Shop::getURL();
$page    = LagerProduktService::currentPage();

$pid = $page['kID'] ?? 0;
$smarty->assign('lpCssUrl', $shopUrl . '/plugins/LagerProdukt/templates/css/lager-produkt.css?v=1.0.34');
$smarty->assign('lpJsUrl', $shopUrl . '/plugins/LagerProdukt/templates/js/lager-produkt.js?v=1.0.34');
$smarty->assign('lpHeadline', $page['cName'] ?? 'Lagerprodukte');
$smarty->assign('lpCategoryName', $page['cName'] ?? '');
$smarty->assign('lpCategoryFound', $page !== null);
$smarty->assign('lpProducts', LagerProduktService::loadProducts($pid));
$smarty->assign('lpGroups', LagerProduktService::loadGroupedProducts($pid));
$smarty->assign('lpSeats', LagerProduktService::loadCompatibleSeats($pid));
