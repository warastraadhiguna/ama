<?php

namespace Tests\Feature\Evidence;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Covers AppServiceProvider's buildTemporaryUrlsUsing() + the
 * evidence-photos.show route — the local-disk stand-in for S3's
 * temporaryUrl(), used when no object storage account is configured yet
 * (config/filesystems.php's "honest gap" note on MinIO's discontinuation).
 *
 * Deliberately does NOT use Storage::fake(): that swaps in a fresh disk
 * instance that never had buildTemporaryUrlsUsing() called on it (that
 * only happened once, at boot, on the real 'local' disk object), so
 * temporaryUrl() would throw exactly the "unsupported" error this test
 * exists to rule out. Writes real (tiny) files under a throwaway path
 * instead, cleaned up in tearDown.
 */
class LocalEvidencePhotoUrlTest extends TestCase
{
    use RefreshDatabase;

    private string $path = 'activities/999999999/photos/local-url-test.jpg';

    protected function tearDown(): void
    {
        Storage::disk('local')->delete($this->path);
        parent::tearDown();
    }

    public function test_a_temporary_url_on_the_local_disk_serves_the_file(): void
    {
        Storage::disk('local')->put($this->path, 'fake-photo-bytes');

        $url = Storage::disk('local')->temporaryUrl($this->path, now()->addMinutes(10));

        $response = $this->get($url);

        $response->assertOk();
        $this->assertSame('fake-photo-bytes', $response->streamedContent());
    }

    public function test_a_tampered_or_expired_link_is_rejected(): void
    {
        Storage::disk('local')->put($this->path, 'fake-photo-bytes');

        $expired = URL::temporarySignedRoute('evidence-photos.show', now()->subMinute(), ['path' => $this->path]);
        $this->get($expired)->assertForbidden();

        $valid = Storage::disk('local')->temporaryUrl($this->path, now()->addMinutes(10));
        $this->get($valid.'&tampered=1')->assertForbidden();
    }

    public function test_a_missing_file_is_a_404_even_with_a_valid_signature(): void
    {
        $url = Storage::disk('local')->temporaryUrl(
            'activities/999999999/photos/does-not-exist.jpg',
            now()->addMinutes(10),
        );

        $this->get($url)->assertNotFound();
    }
}
