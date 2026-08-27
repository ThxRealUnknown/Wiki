<?php

namespace App;

use RuntimeException;

/**
 * Image uploads for image fields. Files land in public/uploads/YYYY/MM under a
 * random name; the database only ever stores the relative path.
 */
final class Uploads
{
    private const ALLOWED = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG  => 'png',
        IMAGETYPE_GIF  => 'gif',
        IMAGETYPE_WEBP => 'webp',
    ];

    public static function baseDir(): string
    {
        return APP_ROOT . '/public/uploads';
    }

    /**
     * @param array $file one entry of $_FILES
     * @return string relative path such as "uploads/2026/08/a1b2c3.jpg"
     */
    public static function store(array $file): string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException(self::errorMessage((int) ($file['error'] ?? -1)));
        }

        $tmp = (string) $file['tmp_name'];
        if (!is_uploaded_file($tmp)) {
            throw new RuntimeException(t('That upload did not arrive intact.'));
        }

        $max = (int) Config::get('upload_max_bytes', 8 * 1024 * 1024);
        if (filesize($tmp) > $max) {
            throw new RuntimeException(
                t('Image is larger than %s MB.', round($max / 1048576, 1))
            );
        }

        // Trust the actual bytes, not the filename or the browser's MIME claim.
        $info = @getimagesize($tmp);
        if ($info === false || !isset(self::ALLOWED[$info[2]])) {
            throw new RuntimeException(t('Only JPEG, PNG, GIF and WebP images are accepted.'));
        }

        $extension = self::ALLOWED[$info[2]];
        $relativeDir = 'uploads/' . date('Y/m');
        $absoluteDir = APP_ROOT . '/public/' . $relativeDir;

        if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0777, true) && !is_dir($absoluteDir)) {
            throw new RuntimeException(t('Could not create the upload folder.'));
        }

        $name = bin2hex(random_bytes(8)) . '.' . $extension;
        $destination = $absoluteDir . '/' . $name;

        if (!move_uploaded_file($tmp, $destination)) {
            throw new RuntimeException(t('Could not save the uploaded image.'));
        }

        return $relativeDir . '/' . $name;
    }

    /**
     * Deletes a stored image, ignoring anything that does not look like one of
     * ours so a tampered value cannot reach outside the uploads folder.
     */
    public static function remove(?string $relativePath): void
    {
        $relativePath = trim((string) $relativePath);
        if ($relativePath === '' || !str_starts_with($relativePath, 'uploads/')) {
            return;
        }

        if (str_contains($relativePath, '..')) {
            return;
        }

        $absolute = APP_ROOT . '/public/' . $relativePath;
        $real = realpath($absolute);
        $base = realpath(self::baseDir());

        if ($real !== false && $base !== false && str_starts_with($real, $base) && is_file($real)) {
            @unlink($real);
        }
    }

    private static function errorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => t('That image is too large to upload.'),
            UPLOAD_ERR_PARTIAL                        => t('The upload was interrupted.'),
            UPLOAD_ERR_NO_TMP_DIR                     => t('PHP has no temp folder to write to.'),
            UPLOAD_ERR_CANT_WRITE                     => t('PHP could not write the file to disk.'),
            UPLOAD_ERR_EXTENSION                      => t('A PHP extension blocked the upload.'),
            default                                   => t('The image could not be uploaded.'),
        };
    }
}
