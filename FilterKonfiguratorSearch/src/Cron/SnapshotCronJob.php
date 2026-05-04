<?php

declare(strict_types=1);

namespace Plugin\FilterKonfiguratorSearch\Cron;

use JTL\Cron\Job;
use JTL\Cron\JobInterface;
use JTL\Cron\QueueEntry;
use JTL\Shop;
use Plugin\FilterKonfiguratorSearch\Service\SnapshotService;

/**
 * CronJob to refresh FilterKonfigurator snapshots weekly.
 */
class SnapshotCronJob extends Job
{
    /**
     * @inheritdoc
     */
    public function hydrate(QueueEntry $queueEntry): JobInterface
    {
        parent::hydrate($queueEntry);
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function start(): JobInterface
    {
        $db = Shop::Container()->getDB();
        $service = new SnapshotService($db);
        
        // Refresh for default language
        $service->refreshAll(1);
        
        // Refresh for current shop language if different
        $currentLang = (int)Shop::getLanguageID();
        if ($currentLang > 0 && $currentLang !== 1) {
            $service->refreshAll($currentLang);
        }

        $this->setFinished(true);
        return $this;
    }
}
