<?php

declare(strict_types=1);

namespace Plugin\MailTemplate;

use JTL\Events\Dispatcher;
use JTL\Plugin\Bootstrapper;

require_once __DIR__ . '/src/Config.php';
require_once __DIR__ . '/src/Labels.php';
require_once __DIR__ . '/src/Designer.php';
require_once __DIR__ . '/src/DesignRenderer.php';
require_once __DIR__ . '/src/Demo.php';
require_once __DIR__ . '/src/TestMail.php';

/**
 * Ersetzt das HTML der Bestellbestaetigung durch eines von 3 Designs (Auswahl im Admin).
 */
class Bootstrap extends Bootstrapper
{
    public function boot(Dispatcher $dispatcher): void
    {
        parent::boot($dispatcher);

        $dispatcher->hookInto(\HOOK_MAIL_PRERENDER, function (array $args): void {
            $mailer = $args['mailer'] ?? null;
            if (!\is_object($mailer) || !\method_exists($mailer, 'getRenderer')) {
                return;
            }
            if (!$mailer->getRenderer() instanceof DesignRenderer) {
                $mailer->setRenderer(new DesignRenderer($mailer->getRenderer()));
            }
        });
    }
}
