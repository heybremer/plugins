<?php

declare(strict_types=1);

namespace Plugin\MailTemplate;

use JTL\Mail\Mail\MailInterface;
use JTL\Mail\Renderer\RendererInterface;
use JTL\Mail\Template\TemplateInterface;
use JTL\Shop;
use JTL\Smarty\JTLSmarty;

/**
 * Decorator um den Shop-Renderer: rendert zuerst wie gewohnt (Betreff, Text, Rechtstexte),
 * ersetzt danach nur den HTML-Teil der Bestellbestaetigung durch das gewaehlte Design.
 * Bei jedem Fehler bleibt das Original-HTML unveraendert.
 */
final class DesignRenderer implements RendererInterface
{
    public function __construct(private readonly RendererInterface $inner)
    {
    }

    public function getSmarty(): JTLSmarty
    {
        return $this->inner->getSmarty();
    }

    public function setSmarty(JTLSmarty $smarty): void
    {
        $this->inner->setSmarty($smarty);
    }

    public function renderHTML(string $id): string
    {
        return $this->inner->renderHTML($id);
    }

    public function renderText(string $id): string
    {
        return $this->inner->renderText($id);
    }

    public function renderMail(MailInterface $mail): void
    {
        $this->inner->renderMail($mail);
    }

    public function renderTemplate(TemplateInterface $template, int $languageID): void
    {
        $this->inner->renderTemplate($template, $languageID);
        if ($template->getID() !== \MAILTEMPLATE_BESTELLBESTAETIGUNG) {
            return;
        }
        try {
            $cfg = Config::all();
            if ($cfg['enabled'] !== '1' || \trim((string)$template->getHTML()) === '') {
                return;
            }
            $smarty = $this->inner->getSmarty();
            $lang   = $smarty->getTemplateVars('int_lang');
            $iso    = \is_object($lang) && \method_exists($lang, 'getIso639') ? (string)$lang->getIso639() : 'de';
            $html   = Designer::render($cfg['design'], $smarty, $iso, $cfg);
            if (\mb_strlen($html) < 500 || \stripos($html, '</html>') === false) {
                return;
            }
            $template->setHTML($this->applyLegal($template, $languageID, $html));
        } catch (\Throwable $e) {
            Shop::Container()->getLogService()->error('MailTemplate: ' . $e->getMessage());
        }
    }

    /**
     * Wie SmartyRenderer::renderLegalDataHTML (Platzhalter [AKZ] / [LEGAL_DATA]).
     */
    private function applyLegal(TemplateInterface $template, int $languageID, string $html): string
    {
        $model = $template->getModel();
        $legal = '';
        $akz   = '';
        if ($model !== null) {
            $data = $template->getLegalData();
            if ($model->getShowAKZ()) {
                $rendered = $this->inner->renderHTML('core_jtl_anbieterkennzeichnung_' . $languageID);
                if (\mb_strlen($rendered) > 0) {
                    $akz = $rendered;
                }
            }
            $blocks = [
                [$model->getShowWRB(), 'wrb', 'wrb'],
                [$model->getShowWRBForm(), 'wrbform', 'wrbform'],
                [$model->getShowAGB(), 'agb', 'agb'],
                [$model->getShowDSE(), 'dse', 'dse'],
            ];
            foreach ($blocks as [$show, $key, $langKey]) {
                $content = (string)($data[$key]->cContentHtml ?? '');
                if ($show && \mb_strlen($content) > 0) {
                    $legal .= '<h3 style="margin:24px 0 8px;font-size:14px;">' . Shop::Lang()->get($langKey) . '</h3>'
                        . $content;
                }
            }
        }

        return \str_replace(['[AKZ]', '[LEGAL_DATA]'], [$akz, $legal], $html);
    }
}
