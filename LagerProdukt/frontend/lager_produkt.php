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
$smarty->assign('lpCssUrl', $shopUrl . '/plugins/LagerProdukt/templates/css/lager-produkt.css?v=1.0.38');
$smarty->assign('lpJsUrl', $shopUrl . '/plugins/LagerProdukt/templates/js/lager-produkt.js?v=1.0.38');
$smarty->assign('lpHeadline', $page['cName'] ?? 'Lagerprodukte');
$smarty->assign('lpCategoryName', $page['cName'] ?? '');
$smarty->assign('lpCategoryFound', $page !== null);
$smarty->assign('lpProducts', LagerProduktService::loadProducts($pid));
$smarty->assign('lpGroups', LagerProduktService::loadGroupedProducts($pid));
$lpMode = $page['cMode'] ?? LagerProduktService::MODE_SEAT;
$smarty->assign('lpMode', $lpMode);
// PKW-Seiten: Fahrzeug-Auswahl (Marke/Modell/Jahr) statt Sitz-Auswahl.
$smarty->assign('lpSeats', $lpMode === LagerProduktService::MODE_PKW ? [] : LagerProduktService::loadCompatibleSeats($pid));
