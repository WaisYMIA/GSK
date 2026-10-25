<?php

namespace Tests\Feature\Content;

use App\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\AccessFixtures;
use Tests\TestCase;

class MediaTest extends TestCase
{
    use RefreshDatabase, AccessFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seedPermissions();
    }

    private function fields(array $overrides = []): array
    {
        return array_merge([
            'file' => UploadedFile::fake()->image('Holiday Photo 1.jpg', 800, 600),
            'alt_text' => 'Trainees practising welding in a workshop',
            'credit_or_source' => 'Synthetic test source',
            'rights_confirmed' => '1',
        ], $overrides);
    }

    #[Test]
    public function the_test_environment_has_the_gd_extension(): void
    {
        $this->assertTrue(extension_loaded('gd'), 'Image tests need PHP GD. Add it to the CI extension list.');
    }

    #[Test]
    public function gsk_144_a_valid_image_is_re_encoded_stored_under_a_generated_name_and_recorded(): void
    {
        $staff = $this->staffWith('MD1', $this->editorKeys());

        $this->actingAs($staff)->post(route('staff.media.store'), $this->fields(['people_consent_recorded' => '1', 'caption' => 'A caption']))
            ->assertRedirect(route('staff.media.index'));

        $media = Media::query()->firstOrFail();
        Storage::disk('public')->assertExists($media->stored_path);
        $this->assertStringStartsWith('media/', $media->stored_path);
        $this->assertStringNotContainsString('Holiday', $media->stored_path, 'The uploaded file name must not be used.');
        $this->assertSame('image/jpeg', $media->mime);
        $this->assertSame(800, $media->width);
        $this->assertSame(600, $media->height);
        $this->assertTrue($media->rights_confirmed);
        $this->assertTrue($media->people_consent_recorded);
        $this->assertSame($staff->id, $media->uploaded_by);
    }

    #[Test]
    public function gsk_144_alt_text_source_and_the_rights_confirmation_are_required(): void
    {
        $staff = $this->staffWith('MD2', $this->editorKeys());

        $this->actingAs($staff)->post(route('staff.media.store'), $this->fields(['alt_text' => '']))->assertSessionHasErrors('alt_text');
        $this->post(route('staff.media.store'), $this->fields(['credit_or_source' => '']))->assertSessionHasErrors('credit_or_source');
        $this->post(route('staff.media.store'), $this->fields(['rights_confirmed' => '']))->assertSessionHasErrors('rights_confirmed');
        $this->assertSame(0, Media::query()->count());
    }

    #[Test]
    public function gsk_144_other_file_types_and_disguised_files_are_refused(): void
    {
        $staff = $this->staffWith('MD3', $this->editorKeys());

        $this->actingAs($staff)->post(route('staff.media.store'), $this->fields(['file' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf')]))
            ->assertSessionHasErrors('file');
        $this->post(route('staff.media.store'), $this->fields(['file' => UploadedFile::fake()->createWithContent('photo.jpg', 'this is not an image')]))
            ->assertSessionHasErrors('file');
        $this->post(route('staff.media.store'), $this->fields(['file' => UploadedFile::fake()->createWithContent('vector.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')]))
            ->assertSessionHasErrors('file');
        $this->assertSame(0, Media::query()->count());
    }

    #[Test]
    public function gsk_144_an_oversize_file_is_refused(): void
    {
        $staff = $this->staffWith('MD4', $this->editorKeys());

        $this->actingAs($staff)->post(route('staff.media.store'), $this->fields(['file' => UploadedFile::fake()->image('big.jpg', 600, 400)->size(3000)]))
            ->assertSessionHasErrors('file');
        $this->assertSame(0, Media::query()->count());
    }

    #[Test]
    public function uploading_needs_the_media_permission_and_a_signed_in_staff_member(): void
    {
        $this->post(route('staff.media.store'), $this->fields())->assertRedirect(route('login'));

        $viewer = $this->staffWith('MD5', ['content.pages.view']);
        $this->actingAs($viewer)->post(route('staff.media.store'), $this->fields())->assertForbidden();
        $this->get(route('staff.media.index'))->assertForbidden();
    }

    #[Test]
    public function a_retired_image_can_no_longer_be_used_in_new_content(): void
    {
        $staff = $this->staffWith('MD6', $this->editorKeys());
        $media = $this->makeMedia($staff);

        $this->actingAs($staff)->post(route('staff.media.retire', $media->id))->assertRedirect(route('staff.media.index'));

        $this->assertSame('retired', $media->refresh()->status);
        $this->expectException(\App\Support\Content\ContentInvalid::class);
        $this->workflow()->create($staff, 'page', ['title' => 'With image', 'slug' => 'with-image'], '!image:' . $media->id);
    }

    #[Test]
    public function an_image_block_renders_with_alt_text_credit_and_alignment(): void
    {
        $media = $this->makeMedia();
        $media->forceFill(['caption' => 'Workshop day'])->save();
        $this->publishedContent('page', 'gallery', 'Gallery', "Intro text.\n\n!image:{$media->id} align=left");

        $this->get('/gallery')->assertOk()
            ->assertSee('class="img-left"', false)
            ->assertSee('alt="Synthetic alt text"', false)
            ->assertSee('Workshop day (Synthetic source)');
    }
}
