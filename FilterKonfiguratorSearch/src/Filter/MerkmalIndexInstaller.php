<?php

declare(strict_types=1);

namespace Plugin\FilterKonfiguratorSearch\Filter;

/**
 * idx_fk_mw_art ist bereits auf der Live-DB. Kein ALTER mehr im Frontend —
 * JTL loggt sonst Duplicate key (1061), SHOW INDEX ... WHERE ist unzuverlässig.
 */
final class MerkmalIndexInstaller
{
    public static function ensure(): void
    {
    }
}
