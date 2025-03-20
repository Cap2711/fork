<?php

namespace Tests\Feature\Admin;

use App\Models\LearningPath;
use App\Models\Unit;
use App\Models\Lesson;
use App\Models\Section;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class AdminLessonTest extends AdminTestCase
{
    protected LearningPath $learningPath;
    protected Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->learningPath = LearningPath::create([
            'title' => 'Japanese Basics',
            'description' => 'Learn basic Japanese',
            'target_level' => 'beginner',
            'status' => 'draft'
        ]);

        $this->unit = Unit::create([
            'learning_path_id' => $this->learningPath->id,
            'title' => 'Introduction to Hiragana',
            'description' => 'Learn the basics of Hiragana writing system',
            'order' => 1,
            'status' => 'draft'
        ]);
    }

    public function test_unauthorized_user_cannot_access_lessons()
    {
        $response = $this->actingAsUser()
            ->getJson('/api/admin/lessons');

        $this->assertUnauthorized($response);
    }

    public function test_admin_can_list_lessons()
    {
        $lesson = Lesson::create([
            'unit_id' => $this->unit->id,
            'title' => 'Basic Hiragana Characters',
            'description' => 'Learn your first Hiragana characters',
            'order' => 1,
            'status' => 'draft'
        ]);

        $response = $this->actingAsAdmin()
            ->getJson('/api/admin/lessons');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    [
                        'title' => 'Basic Hiragana Characters',
                        'description' => 'Learn your first Hiragana characters',
                        'order' => 1,
                        'status' => 'draft'
                    ]
                ]
            ]);
    }

    public function test_admin_can_create_lesson_with_media()
    {
        $thumbnail = UploadedFile::fake()->image('thumbnail.jpg');
        $contentImage = UploadedFile::fake()->image('content.jpg');
        $audio = UploadedFile::fake()->create('pronunciation.mp3', 100, 'audio/mpeg');

        $response = $this->actingAsAdmin()
            ->postJson('/api/admin/lessons', [
                'unit_id' => $this->unit->id,
                'title' => 'Basic Hiragana Characters',
                'description' => 'Learn your first Hiragana characters',
                'order' => 1,
                'thumbnail' => $thumbnail,
                'content_images' => [$contentImage],
                'audio_files' => [$audio]
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'title' => 'Basic Hiragana Characters',
                    'description' => 'Learn your first Hiragana characters',
                    'order' => 1,
                    'status' => 'draft'
                ]
            ]);

        $lesson = Lesson::first();
        $this->assertNotNull($lesson->getFirstMedia('thumbnail'));
        $this->assertNotNull($lesson->getFirstMedia('content_images'));
        $this->assertNotNull($lesson->getFirstMedia('audio'));
    }

    public function test_admin_can_update_lesson_with_version_tracking()
    {
        $lesson = Lesson::create([
            'unit_id' => $this->unit->id,
            'title' => 'Basic Hiragana Characters',
            'description' => 'Learn your first Hiragana characters',
            'order' => 1,
            'status' => 'draft'
        ]);

        $newThumbnail = UploadedFile::fake()->image('new-thumbnail.jpg');

        $response = $this->actingAsAdmin()
            ->putJson("/api/admin/lessons/{$lesson->id}", [
                'title' => 'Updated Hiragana Lesson',
                'description' => 'Updated description',
                'order' => 2,
                'thumbnail' => $newThumbnail
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'title' => 'Updated Hiragana Lesson',
                    'description' => 'Updated description',
                    'order' => 2
                ]
            ]);

        // Verify version was created with changes
        $this->assertDatabaseHas('content_versions', [
            'content_type' => 'lessons',
            'content_id' => $lesson->id,
            'changes' => json_encode([
                'title' => [
                    'old' => 'Basic Hiragana Characters',
                    'new' => 'Updated Hiragana Lesson'
                ],
                'description' => [
                    'old' => 'Learn your first Hiragana characters',
                    'new' => 'Updated description'
                ],
                'order' => [
                    'old' => 1,
                    'new' => 2
                ]
            ])
        ]);
    }

    public function test_admin_can_manage_lesson_status()
    {
        $lesson = Lesson::create([
            'unit_id' => $this->unit->id,
            'title' => 'Basic Hiragana Characters',
            'description' => 'Learn your first Hiragana characters',
            'order' => 1,
            'status' => 'draft'
        ]);

        // Publish the lesson
        $publishResponse = $this->actingAsAdmin()
            ->patchJson("/api/admin/lessons/{$lesson->id}/status", [
                'status' => 'published'
            ]);

        $publishResponse->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'published'
                ]
            ]);

        // Unpublish the lesson
        $unpublishResponse = $this->actingAsAdmin()
            ->patchJson("/api/admin/lessons/{$lesson->id}/status", [
                'status' => 'draft'
            ]);

        $unpublishResponse->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'draft'
                ]
            ]);
    }

    public function test_lesson_review_workflow()
    {
        $lesson = Lesson::create([
            'unit_id' => $this->unit->id,
            'title' => 'Basic Hiragana Characters',
            'description' => 'Learn your first Hiragana characters',
            'order' => 1,
            'status' => 'draft'
        ]);

        // Submit for review
        $submitResponse = $this->actingAsAdmin()
            ->postJson("/api/admin/lessons/{$lesson->id}/submit-for-review");

        $submitResponse->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'review_status' => 'pending'
                ]
            ]);

        // Verify review record
        $this->assertDatabaseHas('reviews', [
            'content_type' => 'lessons',
            'content_id' => $lesson->id,
            'status' => 'pending'
        ]);

        // Approve review
        $approveResponse = $this->actingAsAdmin()
            ->postJson("/api/admin/lessons/{$lesson->id}/approve-review", [
                'feedback' => 'Excellent lesson content!'
            ]);

        $approveResponse->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'published',
                    'review_status' => 'approved'
                ]
            ]);

        // Verify review was updated
        $this->assertDatabaseHas('reviews', [
            'content_type' => 'lessons',
            'content_id' => $lesson->id,
            'status' => 'approved',
            'feedback' => 'Excellent lesson content!'
        ]);
    }

    public function test_admin_can_reorder_sections()
    {
        $lesson = Lesson::create([
            'unit_id' => $this->unit->id,
            'title' => 'Basic Hiragana Characters',
            'description' => 'Learn your first Hiragana characters',
            'order' => 1,
            'status' => 'draft'
        ]);

        $sections = [];
        for ($i = 1; $i <= 3; $i++) {
            $sections[] = $lesson->sections()->create([
                'title' => "Section $i",
                'content' => "<p>Content $i</p>",
                'order' => $i
            ]);
        }

        $newOrder = [
            ['id' => $sections[2]->id, 'order' => 1],
            ['id' => $sections[0]->id, 'order' => 2],
            ['id' => $sections[1]->id, 'order' => 3],
        ];

        $response = $this->actingAsAdmin()
            ->postJson("/api/admin/lessons/{$lesson->id}/reorder-sections", [
                'sections' => $newOrder
            ]);

        $response->assertOk()
            ->assertJson(['success' => true]);

        foreach ($newOrder as $item) {
            $this->assertDatabaseHas('sections', [
                'id' => $item['id'],
                'order' => $item['order']
            ]);
        }
    }

    public function test_admin_can_delete_lesson_with_cleanup()
    {
        $lesson = Lesson::create([
            'unit_id' => $this->unit->id,
            'title' => 'Basic Hiragana Characters',
            'description' => 'Learn your first Hiragana characters',
            'order' => 1,
            'status' => 'draft'
        ]);

        // Add some media
        $thumbnail = UploadedFile::fake()->image('thumbnail.jpg');
        $lesson->addMedia($thumbnail)->toMediaCollection('thumbnail');

        // Add some sections
        $lesson->sections()->create([
            'title' => 'Section 1',
            'content' => '<p>Content</p>',
            'order' => 1
        ]);

        $response = $this->actingAsAdmin()
            ->deleteJson("/api/admin/lessons/{$lesson->id}");

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('lessons', ['id' => $lesson->id]);
        $this->assertDatabaseMissing('sections', ['lesson_id' => $lesson->id]);
        $this->assertEmpty($lesson->getMedia('thumbnail'));
    }
}