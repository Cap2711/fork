<?php

namespace Tests\Feature\Admin;

use App\Models\MediaFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class MediaTest extends AdminTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_admin_can_upload_image()
    {
        $image = UploadedFile::fake()->image('test.jpg', 800, 600);

        $response = $this->actingAsAdmin()
            ->postJson('/api/admin/media/upload', [
                'file' => $image,
                'type' => 'image',
                'collection' => 'content_images'
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'filename' => true,
                    'url' => true,
                    'mime_type' => 'image/jpeg',
                    'size' => true
                ]
            ]);

        // Verify file was stored
        $mediaFile = MediaFile::first();
        $this->assertTrue(Storage::disk('public')->exists($mediaFile->path));
    }

    public function test_admin_can_upload_audio()
    {
        $audio = UploadedFile::fake()->create('audio.mp3', 1024, 'audio/mpeg');

        $response = $this->actingAsAdmin()
            ->postJson('/api/admin/media/upload', [
                'file' => $audio,
                'type' => 'audio',
                'collection' => 'lesson_audio'
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'filename' => true,
                    'url' => true,
                    'mime_type' => 'audio/mpeg',
                    'size' => true
                ]
            ]);
    }

    public function test_admin_can_upload_multiple_files()
    {
        $files = [
            'images' => [
                UploadedFile::fake()->image('image1.jpg'),
                UploadedFile::fake()->image('image2.jpg')
            ],
            'documents' => [
                UploadedFile::fake()->create('doc.pdf', 1024, 'application/pdf')
            ]
        ];

        $response = $this->actingAsAdmin()
            ->postJson('/api/admin/media/upload', [
                'files' => $files,
                'collection' => 'lesson_materials'
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'uploaded_count' => 3,
                    'files' => true
                ]
            ]);

        $this->assertDatabaseCount('media_files', 3);
    }

    public function test_file_size_validation()
    {
        $largeFile = UploadedFile::fake()->create('large.mp4', 51200); // 50MB+

        $response = $this->actingAsAdmin()
            ->postJson('/api/admin/media/upload', [
                'file' => $largeFile,
                'type' => 'video',
                'collection' => 'lesson_videos'
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    public function test_file_type_validation()
    {
        $invalidFile = UploadedFile::fake()->create('script.php', 100, 'text/php');

        $response = $this->actingAsAdmin()
            ->postJson('/api/admin/media/upload', [
                'file' => $invalidFile,
                'type' => 'document',
                'collection' => 'materials'
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    public function test_admin_can_delete_media()
    {
        $image = UploadedFile::fake()->image('test.jpg');
        
        // First upload a file
        $uploadResponse = $this->actingAsAdmin()
            ->postJson('/api/admin/media/upload', [
                'file' => $image,
                'type' => 'image',
                'collection' => 'content_images'
            ]);

        $mediaId = $uploadResponse->json('data.id');
        $filePath = $uploadResponse->json('data.path');

        // Then delete it
        $deleteResponse = $this->actingAsAdmin()
            ->deleteJson("/api/admin/media/{$mediaId}");

        $deleteResponse->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Media deleted successfully'
            ]);

        // Verify file was deleted from storage
        $mediaFile = MediaFile::find($mediaId);
        $this->assertNull($mediaFile);
        $this->assertFalse(Storage::disk('public')->exists($filePath));
    }

    public function test_unauthorized_user_cannot_manage_media()
    {
        $image = UploadedFile::fake()->image('test.jpg');

        $uploadResponse = $this->actingAsUser()
            ->postJson('/api/admin/media/upload', [
                'file' => $image,
                'type' => 'image'
            ]);

        $this->assertUnauthorized($uploadResponse);

        $deleteResponse = $this->actingAsUser()
            ->deleteJson('/api/admin/media/1');

        $this->assertUnauthorized($deleteResponse);
    }

    public function test_admin_can_get_media_info()
    {
        $image = UploadedFile::fake()->image('test.jpg');
        
        $uploadResponse = $this->actingAsAdmin()
            ->postJson('/api/admin/media/upload', [
                'file' => $image,
                'type' => 'image',
                'collection' => 'content_images'
            ]);

        $mediaId = $uploadResponse->json('data.id');

        $response = $this->actingAsAdmin()
            ->getJson("/api/admin/media/{$mediaId}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $mediaId,
                    'filename' => true,
                    'mime_type' => 'image/jpeg',
                    'url' => true,
                    'created_at' => true
                ]
            ]);
    }
}