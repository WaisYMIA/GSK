<?php

namespace App\Support\Content;

use App\Models\Media;

// Checks every block and returns a clean copy. Plain text only: rendering escapes everything.
class BlockValidator
{
    /**
     * @param  array<int, array<string, mixed>>  $blocks
     * @return array<int, array<string, mixed>>
     *
     * @throws ContentInvalid
     */
    public function validate(array $blocks, string $ownerType): array
    {
        $errors = [];
        $clean = [];
        $images = 0;
        $max = (int) config('gsk.content.max_blocks', 200);

        if (count($blocks) > $max) {
            throw new ContentInvalid(['blocks' => ["A page can have at most {$max} blocks."]]);
        }

        foreach (array_values($blocks) as $i => $b) {
            $n = $i + 1;
            $type = (string) ($b['type'] ?? '');
            switch ($type) {
                case 'paragraph':
                case 'quote':
                case 'callout':
                    $text = $this->text($b['text'] ?? null, 1, $type === 'paragraph' ? 5000 : 1000, "Block {$n} ({$type})", $errors);
                    $clean[] = ['type' => $type, 'text' => $text];
                    break;
                case 'heading':
                    $level = (int) ($b['level'] ?? 2);
                    if (! in_array($level, [2, 3], true)) {
                        $errors['blocks'][] = "Block {$n} (heading): level must be 2 or 3.";
                    }
                    $clean[] = ['type' => 'heading', 'level' => $level, 'text' => $this->text($b['text'] ?? null, 1, 200, "Block {$n} (heading)", $errors)];
                    break;
                case 'list':
                    $items = $b['items'] ?? null;
                    if (! is_array($items) || $items === [] || count($items) > 50) {
                        $errors['blocks'][] = "Block {$n} (list): needs between 1 and 50 items.";
                        break;
                    }
                    $clean[] = [
                        'type' => 'list',
                        'ordered' => ! empty($b['ordered']),
                        'items' => array_map(fn ($item) => $this->text($item, 1, 500, "Block {$n} (list item)", $errors), array_values($items)),
                    ];
                    break;
                case 'link':
                    $label = $this->text($b['label'] ?? null, 1, 200, "Block {$n} (link label)", $errors);
                    $url = trim((string) ($b['url'] ?? ''));
                    if (! LinkValidator::isAllowed($url)) {
                        $errors['blocks'][] = "Block {$n} (link): only http, https, mailto and site-relative links are allowed.";
                    }
                    $clean[] = ['type' => 'link', 'label' => $label, 'url' => $url];
                    break;
                case 'image':
                    $images++;
                    $align = (string) ($b['align'] ?? 'full');
                    if (! in_array($align, ['full', 'left', 'right', 'center'], true)) {
                        $errors['blocks'][] = "Block {$n} (image): align must be full, left, right or center.";
                    }
                    $mediaId = (int) ($b['media_id'] ?? 0);
                    if (! Media::query()->where('id', $mediaId)->where('status', 'approved')->exists()) {
                        $errors['blocks'][] = "Block {$n} (image): media {$mediaId} does not exist or is retired.";
                    }
                    $clean[] = ['type' => 'image', 'media_id' => $mediaId, 'align' => $align];
                    break;
                default:
                    $errors['blocks'][] = "Block {$n}: not understood" . ($type === 'invalid' ? ' (' . substr((string) ($b['raw'] ?? ''), 0, 40) . ').' : '.');
            }
        }

        $limit = (int) config('gsk.content.max_images_per_post', 3);
        if ($ownerType === 'post' && $images > $limit) {
            $errors['blocks'][] = "A news post can have at most {$limit} images.";
        }

        if ($errors !== []) {
            throw new ContentInvalid($errors);
        }

        return $clean;
    }

    private function text(mixed $value, int $min, int $max, string $label, array &$errors): string
    {
        $text = is_string($value) ? trim($value) : '';
        $length = mb_strlen($text);
        if ($length < $min || $length > $max) {
            $errors['blocks'][] = "{$label}: text must be between {$min} and {$max} characters.";
        }
        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $text) === 1) {
            $errors['blocks'][] = "{$label}: contains control characters.";
        }

        return $text;
    }
}
