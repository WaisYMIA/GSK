<?php

namespace App\Support\Content;

// Turns the simple editor text into blocks, and back. No HTML is ever accepted.
//   # Heading          ## Smaller heading
//   - item             1. item
//   > quote
//   !image:12 align=left      (align: full, left, right, center)
//   !callout: text
//   !link: Label | https://example.org
// Blank lines separate blocks. Anything else is a paragraph.
final class BlockParser
{
    public static function parse(string $text): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", trim($text));
        if ($text === '') {
            return [];
        }
        $blocks = [];
        foreach (preg_split('/\n{2,}/', $text) as $chunk) {
            $chunk = trim($chunk);
            if ($chunk === '') {
                continue;
            }
            $lines = explode("\n", $chunk);
            $first = $lines[0];

            if (str_starts_with($first, '## ') && count($lines) === 1) {
                $blocks[] = ['type' => 'heading', 'level' => 3, 'text' => trim(substr($first, 3))];
            } elseif (str_starts_with($first, '# ') && count($lines) === 1) {
                $blocks[] = ['type' => 'heading', 'level' => 2, 'text' => trim(substr($first, 2))];
            } elseif (str_starts_with($first, '> ')) {
                $blocks[] = ['type' => 'quote', 'text' => implode("\n", array_map(fn (string $l) => trim(preg_replace('/^>\s?/', '', $l)), $lines))];
            } elseif (str_starts_with($first, '- ')) {
                $blocks[] = ['type' => 'list', 'ordered' => false, 'items' => array_map(fn (string $l) => trim(preg_replace('/^-\s?/', '', $l)), $lines)];
            } elseif (preg_match('/^\d+\.\s/', $first) === 1) {
                $blocks[] = ['type' => 'list', 'ordered' => true, 'items' => array_map(fn (string $l) => trim(preg_replace('/^\d+\.\s?/', '', $l)), $lines)];
            } elseif (str_starts_with($first, '!image:') && count($lines) === 1) {
                if (preg_match('/^!image:(\d+)(?:\s+align=(full|left|right|center))?\s*$/', $first, $m) === 1) {
                    $blocks[] = ['type' => 'image', 'media_id' => (int) $m[1], 'align' => $m[2] ?? 'full'];
                } else {
                    $blocks[] = ['type' => 'invalid', 'raw' => $first];
                }
            } elseif (str_starts_with($first, '!callout:') && count($lines) === 1) {
                $blocks[] = ['type' => 'callout', 'text' => trim(substr($first, 9))];
            } elseif (str_starts_with($first, '!link:') && count($lines) === 1) {
                $parts = array_map('trim', explode('|', trim(substr($first, 6)), 2));
                if (count($parts) === 2) {
                    $blocks[] = ['type' => 'link', 'label' => $parts[0], 'url' => $parts[1]];
                } else {
                    $blocks[] = ['type' => 'invalid', 'raw' => $first];
                }
            } elseif (str_starts_with($first, '!')) {
                $blocks[] = ['type' => 'invalid', 'raw' => $first];
            } else {
                $blocks[] = ['type' => 'paragraph', 'text' => $chunk];
            }
        }

        return $blocks;
    }

    public static function toText(array $blocks): string
    {
        $out = [];
        foreach ($blocks as $b) {
            switch ($b['type'] ?? '') {
                case 'heading':
                    $out[] = (($b['level'] ?? 2) === 3 ? '## ' : '# ') . $b['text'];
                    break;
                case 'paragraph':
                    $out[] = $b['text'];
                    break;
                case 'quote':
                    $out[] = implode("\n", array_map(fn (string $l) => '> ' . $l, explode("\n", $b['text'])));
                    break;
                case 'list':
                    $i = 0;
                    $out[] = implode("\n", array_map(function (string $item) use ($b, &$i) {
                        $i++;

                        return ! empty($b['ordered']) ? $i . '. ' . $item : '- ' . $item;
                    }, $b['items']));
                    break;
                case 'image':
                    $out[] = '!image:' . $b['media_id'] . (($b['align'] ?? 'full') !== 'full' ? ' align=' . $b['align'] : '');
                    break;
                case 'callout':
                    $out[] = '!callout: ' . $b['text'];
                    break;
                case 'link':
                    $out[] = '!link: ' . $b['label'] . ' | ' . $b['url'];
                    break;
            }
        }

        return implode("\n\n", $out);
    }
}
