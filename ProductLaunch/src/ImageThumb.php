<?php
declare(strict_types=1);

namespace Plugin\ProductLaunch;

use JTL\Shop;

/**
 * Verkleinerte WebP-Kopien hochgeladener Bilder (mediafiles/productlaunch/_w<Breite>/).
 * Fällt bei Fehlern/fehlendem GD-WebP stets auf die Original-URL zurück.
 */
final class ImageThumb
{
    private const DIR          = '/mediafiles/productlaunch/';
    private const MAX_PER_CALL = 4;

    private static int $generated = 0;

    public static function url(string $url, int $width): string
    {
        try {
            $path = (string)\parse_url($url, \PHP_URL_PATH);
            if ($path === '' || \strpos($path, self::DIR) !== 0 || \strpos($path, '..') !== false) {
                return $url;
            }
            $root = \rtrim(\PFAD_ROOT, '/\\');
            $src  = $root . $path;
            if (!\is_file($src)) {
                return $url;
            }
            $relDir = \dirname($path) . '/_w' . $width . '/';
            $name   = \pathinfo($path, \PATHINFO_FILENAME) . '.webp';
            $dest   = $root . $relDir . $name;
            $public = \rtrim((string)Shop::getURL(), '/') . $relDir . \rawurlencode($name);
            if (\is_file($dest) && \filemtime($dest) >= \filemtime($src)) {
                return $public;
            }
            if (self::$generated >= self::MAX_PER_CALL || !\function_exists('imagewebp')) {
                return $url;
            }
            self::$generated++;

            return self::generate($src, $dest, $width) ? $public : $url;
        } catch (\Throwable $e) {
            return $url;
        }
    }

    private static function generate(string $src, string $dest, int $width): bool
    {
        $info = @\getimagesize($src);
        if ($info === false || (int)$info[0] <= $width) {
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
        $ok  = @\imagewebp($out, $tmp, 80) && @\rename($tmp, $dest);
        if (!$ok) {
            @\unlink($tmp);
        }

        return $ok;
    }
}
