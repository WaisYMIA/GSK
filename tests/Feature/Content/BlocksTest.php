<?php

namespace Tests\Feature\Content;

use App\Support\Content\BlockParser;
use App\Support\Content\BlockValidator;
use App\Support\Content\ContentInvalid;
use App\Support\Content\LinkValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\AccessFixtures;
use Tests\TestCase;

class BlocksTest extends TestCase
{
    use RefreshDatabase, AccessFixtures;

    public static function allowedLinks(): array
    {
        return [['https://example.org'], ['http://example.org/a?b=1'], ['mailto:info@example.org'], ['/about'], ['/news/some-post']];
    }

    public static function refusedLinks(): array
    {
        return [['javascript:alert(1)'], ['JaVaScRiPt:alert(1)'], ['data:text/html,x'], ['//evil.example'], ['ftp://example.org'], ['https://'], [''],
            ['/a b'], ["java\nscript:alert(1)"], ['mailto:not-an-email'], ['vbscript:x'], ['/\\evil.example']];
    }

    #[Test]
    #[DataProvider('allowedLinks')]
    public function gsk_143_safe_links_are_accepted(string $url): void
    {
        $this->assertTrue(LinkValidator::isAllowed($url));
    }

    #[Test]
    #[DataProvider('refusedLinks')]
    public function gsk_143_unsafe_links_are_refused(string $url): void
    {
        $this->assertFalse(LinkValidator::isAllowed($url));
    }

    #[Test]
    public function gsk_143_an_unsafe_link_block_is_refused_when_content_is_saved(): void
    {
        $this->expectException(ContentInvalid::class);
        app(BlockValidator::class)->validate(BlockParser::parse('!link: Click | javascript:alert(1)'), 'page');
    }

    #[Test]
    public function the_editor_text_round_trips_through_blocks(): void
    {
        $text = "# Title\n\nFirst paragraph\nsecond line\n\n## Sub\n\n- one\n- two\n\n1. first\n2. second\n\n> A quote\n\n!callout: Take note\n\n!link: Read more | https://example.org";
        $blocks = BlockParser::parse($text);

        $this->assertSame($text, BlockParser::toText($blocks));
        $this->assertSame(['heading', 'paragraph', 'heading', 'list', 'list', 'quote', 'callout', 'link'], array_column($blocks, 'type'));
        $this->assertSame(2, $blocks[0]['level']);
        $this->assertSame(3, $blocks[2]['level']);
        $this->assertFalse($blocks[3]['ordered']);
        $this->assertTrue($blocks[4]['ordered']);
    }

    #[Test]
    public function unknown_commands_and_html_like_tags_are_handled_safely(): void
    {
        $blocks = BlockParser::parse("!script: alert(1)\n\n<b>bold</b> stays as plain text");

        $this->assertSame('invalid', $blocks[0]['type']);
        $this->assertSame('paragraph', $blocks[1]['type']);
        $this->expectException(ContentInvalid::class);
        app(BlockValidator::class)->validate($blocks, 'page');
    }

    #[Test]
    public function the_validator_enforces_lengths_levels_and_control_characters(): void
    {
        $validator = app(BlockValidator::class);
        $bad = [
            [['type' => 'heading', 'level' => 5, 'text' => 'x']],
            [['type' => 'paragraph', 'text' => str_repeat('a', 5001)]],
            [['type' => 'paragraph', 'text' => '']],
            [['type' => 'paragraph', 'text' => "bad\x01char"]],
            [['type' => 'list', 'ordered' => false, 'items' => []]],
            [['type' => 'image', 'media_id' => 99999, 'align' => 'full']],
            [['type' => 'image', 'media_id' => 1, 'align' => 'diagonal']],
        ];
        foreach ($bad as $blocks) {
            try {
                $validator->validate($blocks, 'page');
                $this->fail('Expected invalid blocks to be refused.');
            } catch (ContentInvalid $e) {
                $this->assertArrayHasKey('blocks', $e->errors);
            }
        }
    }

    #[Test]
    public function gsk_003_a_news_post_can_hold_three_images_but_not_four(): void
    {
        $ids = [$this->makeMedia()->id, $this->makeMedia()->id, $this->makeMedia()->id, $this->makeMedia()->id];
        $image = fn (int $id) => ['type' => 'image', 'media_id' => $id, 'align' => 'left'];
        $validator = app(BlockValidator::class);

        $this->assertCount(3, $validator->validate([$image($ids[0]), $image($ids[1]), $image($ids[2])], 'post'));
        $this->assertCount(4, $validator->validate(array_map($image, $ids), 'page'), 'The three-image rule applies to news posts only.');

        $this->expectException(ContentInvalid::class);
        $validator->validate(array_map($image, $ids), 'post');
    }

    #[Test]
    public function a_retired_image_cannot_be_used(): void
    {
        $media = $this->makeMedia(null, 'retired');

        $this->expectException(ContentInvalid::class);
        app(BlockValidator::class)->validate([['type' => 'image', 'media_id' => $media->id, 'align' => 'full']], 'page');
    }

    #[Test]
    public function gsk_142_rendering_escapes_html_and_script_in_every_block_type(): void
    {
        $payload = '<script>alert(1)</script>';
        $text = "# <img src=x onerror=alert(1)>\n\n{$payload}\n\n- {$payload}\n\n> {$payload}\n\n!callout: {$payload}\n\n!link: {$payload} | https://example.org";
        $this->publishedContent('page', 'escape-check', 'Escape check', $text);

        $response = $this->get('/escape-check')->assertOk();

        $response->assertDontSee($payload, false);
        $response->assertDontSee('<img src=x onerror=alert(1)>', false);
        $response->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }
}
