<?php
declare(strict_types=1);

namespace Plugin\FourLayout;

use JTL\Shop;

/**
 * Verkleinerte WebP-Kopien lokaler Bilder (mediafiles/fourlayout/_w<Breite>/).
 * Fällt bei Fehlern/fehlendem GD-WebP stets auf die Original-URL zurück.
 */
final class ImageThumb
{
    private const OUT_DIR      = '/mediafiles/fourlayout/';
    private const SOURCES      = ['/mediafiles/', '/media/image/product/'];
    private const MAX_PER_CALL = 4;

    private static int $generated = 0;

    public static function url(string $url, int $width): string
    {
        try {
            $path = \rawurldecode((string)\parse_url($url, \PHP_URL_PATH));
            if ($path === '' || \strpos($path, '..') !== false || \strpos($path, self::OUT_DIR) === 0) {
                return $url;
            }
            $allowed = false;
            foreach (self::SOURCES as $prefix) {
                $allowed = $allowed || \strpos($path, $prefix) === 0;
            }
            $root = \rtrim(\PFAD_ROOT, '/\\');
            $src  = $root . $path;
            if (!$allowed || !\is_file($src)) {
                return $url;
            }
            $relDir = self::OUT_DIR . '_w' . $width . '/';
            $name   = \substr(\md5($path), 0, 8) . '-' . \pathinfo($path, \PATHINFO_FILENAME) . '.webp';
            $dest   = $root . $relDir . $name;
            $public = \rtrim((string)Shop::getURL(), '/') . $relDir . \rawurlencode($name);
            if (\is_file($dest) && \filemtime($dest) >= \filemtime($src)) {
                return \filesize($dest) < \filesize($src) ? $public : $url;
            }
            if (self::$generated >= self::MAX_PER_CALL || !\function_exists('imagewebp')) {
                return $url;
            }
            self::$generated++;

            return self::generate($src, $dest, $width) && \filesize($dest) < \filesize($src) ? $public : $url;
        } catch (\Throwable $e) {
            return $url;
        }
    }

    private static function generate(string $src, string $dest, int $width): bool
    {
        $info = @\getimagesize($src);
        if ($info === false || (int)$info[0] <= 0) {
            return false;
        }
        $img = match ((int)$info[2]) {
            \IMAGETYPE_JPEG => @\imagecreatefromjpeg($src),
            \IMAGETYPE_PNG  => @\imagecreatefrompng($src),
            \IMAGETYPE_WEBP => @\imagecreatefromwebp($src),
            default         => false,
        };
        if ($img === false) {
            return false;
        }
        $width  = \min($width, (int)$info[0]);
        $height = (int)\round((int)$info[1] * $width / (int)$info[0]);
        $out    = \imagecreatetruecolor($width, $height);
        \imagealphablending($out, false);
        \imagesavealpha($out, true);
        \imagecopyresampled($out, $img, 0, 0, 0, 0, $width, $height, (int)$info[0], (int)$info[1]);
        $dir = \dirname($dest);
        if (!\is_dir($dir) && !@\mkdir($dir, 0755, true) && !\is_dir($dir)) {
            return false;
        }
        $tmp = $dest . '.' . \getmypid() . '.tmp';
        $ok  = @\imagewebp($out, $tmp, 78) && @\rename($tmp, $dest);
        if (!$ok) {
            @\unlink($tmp);
        }

        return $ok;
    }
}
