<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Railway'de kapak/defter görselleri herkese kapalı bucket'ta; tarayıcıya /media/... üzerinden
 * uygulama veriyor (MediaController). Belgeler bu yoldan erişilemez.
 */
class MediaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['filesystems.covers_disk' => 'bucket_public']);
        Storage::fake('bucket_public');
        Storage::fake('bucket_private');
    }

    public function test_stored_cover_is_served_with_long_cache(): void
    {
        $path = UploadedFile::fake()->image('kapak.jpg')->store('covers/books', 'bucket_public');

        $response = $this->get('/media/public/'.$path);

        $response->assertOk();
        $this->assertStringContainsString('immutable', $response->headers->get('Cache-Control'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertSame(Storage::disk('bucket_public')->get($path), $response->streamedContent());
    }

    public function test_missing_file_is_404(): void
    {
        $this->get('/media/public/covers/books/yok.jpg')->assertNotFound();
    }

    public function test_paths_outside_the_public_folder_are_404(): void
    {
        // Belgeler aynı bucket'ın private/ klasöründe; JPG/PNG belge de /media'dan açılmamalı.
        $path = UploadedFile::fake()->image('belge.jpg')->store('documents', 'bucket_private');

        $this->get('/media/private/'.$path)->assertNotFound();
        $this->get('/media/'.$path)->assertNotFound();
    }

    public function test_only_image_paths_without_traversal_match(): void
    {
        Storage::disk('bucket_public')->put('notlar.txt', 'gizli');
        Storage::disk('bucket_private')->put('documents/belge.pdf', '%PDF');

        $this->get('/media/notlar.txt')->assertNotFound();
        $this->get('/media/../private/documents/belge.pdf')->assertNotFound();
        $this->get('/media/covers/..%2F..%2Fprivate/x.jpg')->assertNotFound();
    }

    public function test_bucket_urls_point_to_the_app(): void
    {
        $this->assertSame(
            rtrim(config('app.url'), '/').'/media/public/covers/books/a.jpg',
            Storage::build(config('filesystems.disks.bucket_public'))->url('covers/books/a.jpg'),
        );
    }
}
