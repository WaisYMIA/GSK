<?php

namespace App\Support\Content;

use finfo;

// Re-encodes uploaded images with GD. Re-encoding drops embedded metadata (for example GPS). Fails closed:
// without GD there is no unprocessed fallback. Detects the real type from the bytes, never from the client.
class MediaProcessor
{
    public function available(): bool
    {
        return extension_loaded('gd') && function_exists('imagecreatefromstring');
    }

    /** @return array{contents: string, extension: string, mime: string, width: int, height: int} */
    public function process(string $bytes): array
    {
        if (! $this->available()) {
            throw new ContentInvalid(['file' => ['Image processing is not available on this server, so images cannot be uploaded yet.']]);
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        $allowed = (array) config('gsk.media.allowed_mimes', []);
        if (! is_string($mime) || ! in_array($mime, $allowed, true)) {
            throw new ContentInvalid(['file' => ['Only JPEG, PNG and WebP images are accepted.']]);
        }

        $info = @getimagesizefromstring($bytes);
        if ($info === false) {
            throw new ContentInvalid(['file' => ['The file is not a readable image.']]);
        }
        if ($info[0] * $info[1] > (int) config('gsk.media.max_pixels', 25000000)) {
            throw new ContentInvalid(['file' => ['The image is too large in pixels.']]);
        }

        $image = @imagecreatefromstring($bytes);
        if ($image === false) {
            throw new ContentInvalid(['file' => ['The file is not a readable image.']]);
        }

        $max = (int) config('gsk.media.max_dimension', 2000);
        $width = imagesx($image);
        $height = imagesy($image);
        if ($width > $max || $height > $max) {
            $scale = $max / max($width, $height);
            $scaled = imagescale($image, max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)));
            if ($scaled !== false) {
                $image = $scaled;
                $width = imagesx($image);
                $height = imagesy($image);
            }
        }

        ob_start();
        switch ($mime) {
            case 'image/jpeg':
                imagejpeg($image, null, 85);
                $extension = 'jpg';
                break;
            case 'image/png':
                imagealphablending($image, false);
                imagesavealpha($image, true);
                imagepng($image);
                $extension = 'png';
                break;
            default:
                if (! function_exists('imagewebp')) {
                    ob_end_clean();
                    throw new ContentInvalid(['file' => ['WebP is not supported on this server. Use JPEG or PNG.']]);
                }
                imagewebp($image, null, 85);
                $extension = 'webp';
        }
        $contents = (string) ob_get_clean();

        return ['contents' => $contents, 'extension' => $extension, 'mime' => $mime, 'width' => $width, 'height' => $height];
    }
}
