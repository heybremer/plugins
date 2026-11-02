<?php
/**
 * JTL PageController includes this file before rendering the Smarty template.
 *
 * @var \JTL\Smarty\JTLSmarty $smarty
 */

declare(strict_types=1);

require_once \dirname(__DIR__) . '/src/MarkePageService.php';

use JTL\Shop;
use Plugin\MarkePage\MarkePageService;

$mpLink = $smarty->getTemplateVars('Link');
$mpSvc  = new MarkePageService(Shop::Container()->getDB());
$mpPage = (\is_object($mpLink) && \method_exists($mpLink, 'getID'))
    ? $mpSvc->findByLink((int)$mpLink->getID())
    : null;

$mpProducts = [];
if ($mpPage !== null) {
    $mpProducts = $mpSvc->loadProducts(MarkePageService::parseArtNrs((string)$mpPage->cArtNrs))['products'];
}

$mpIntro = $mpPage !== null ? \trim((string)$mpPage->cIntro) : '';

$smarty->assign('mpPage', $mpPage)
    ->assign('mpProducts', $mpProducts)
    ->assign('mpIntroHtml', $mpIntro !== '' ? \nl2br(\htmlspecialchars($mpIntro, \ENT_QUOTES, 'UTF-8')) : '')
    ->assign('mpCssUrl', Shop::getURL() . '/plugins/MarkePage/frontend/css/marke-page.css?v=1.0.1');
