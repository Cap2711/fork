<?php

namespace Tests\Feature\Admin;

use App\Models\LearningPath;
use App\Models\Unit;
use App\Models\Lesson;
use App\Models\Section;
use App\Models\Exercise;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class AdminSectionTest extends AdminTestCase
{
    protected LearningPath $learningPath;
    protected Unit $unit;
    protected Lesson $lesson;

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

        $this->lesson = Lesson::create([
            'unit_id' => $this->unit->id,
            'title' => 'Basic Hiragana Characters',
            'description' => 'Learn your first Hiragana characters',
            'order' => 1,
            'status' => 'draft'
        ]);
    }

    public function test_unauthorized_user_cannot_access_sections()
    {
        $response = $this->actingAsUser()
            ->getJson('/api/admin/sections');

        $this->assertUnauthorized($response);
    }

    public function test_admin_can_create_section_with_all_media_types()
    {
        $contentImage = UploadedFile::fake()->image('diagram.jpg');
        $audio = UploadedFile::fake()->create('pronunciation.mp3', 100, 'audio/mpeg');
        $video = UploadedFile::fake()->create('explanation.mp4', 1024 * 1024, 'video/mp4');
        $pdf = UploadedFile::fake()->create('worksheet.pdf', 100, 'application/pdf');

        $response = $this->actingAsAdmin()
            ->postJson('/api/admin/sections', [
                'lesson_id' => $this->lesson->id,
                'title' => 'Writing あ',
                'content' => '<p>Learn how to write あ character</p>',
                'order' => 1,
                'content_images' => [$contentImage],
                'audio_files' => [$audio],
                'video_files' => [$video],
                'attachments' => [$pdf]
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'title' => 'Writing あ',
                    'content' => '<p>Learn how to write あ character</p>',
                    'order' => 1,
                    'status' => 'draft'
                ]
            ]);

        $section = Section::first();
        
        // Verify all media types were properly attached
        $this->assertCount(1, $section->getMedia('content_images'));
        $this->assertCount(1, $section->getMedia('audio'));
        $this->assertCount(1, $section->getMedia('video'));
        $this->assertCount(1, $section->getMedia('attachments'));
    }

    public function test_admin_can_update_section_with_version_tracking()
    {
        $section = Section::create([
            'lesson_id' => $this->lesson->id,
            'title' => 'Writing あ',
            'content' => '<p>Original content</p>',
            'order' => 1,
            'status' => 'draft'
        ]);

        $newContentImage = UploadedFile::fake()->image('new-diagram.jpg');
        $newContent = '<p>Updated content with more detailed explanation</p>';

        $response = $this->actingAsAdmin()
            ->putJson("/api/admin/sections/{$section->id}", [
                'title' => 'Updated Writing あ',
                'content' => $newContent,
                'content_images' => [$newContentImage]
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'title' => 'Updated Writing あ',
                    'content' => $newContent
                ]
            ]);

        // Verify version was created with changes
        $this->assertDatabaseHas('content_versions', [
            'content_type' => 'sections',
            'content_id' => $section->id,
            'changes' => json_encode([
                'title' => [
                    'old' => 'Writing あ',
                    'new' => 'Updated Writing あ'
                ],
                'content' => [
                    'old' => '<p>Original content</p>',
                    'new' => $newContent
                ]
            ])
        ]);
    }

    public function test_admin_can_manage_section_status()
    {
        $section = Section::create([
            'lesson_id' => $this->lesson->id,
            'title' => 'Writing あ',
            'content' => '<p>Learn how to write あ character</p>',
            'order' => 1,
            'status' => 'draft'
        ]);

        // Publish the section
        $publishResponse = $this->actingAsAdmin()
            ->patchJson("/api/admin/sections/{$section->id}/status", [
                'status' => 'published'
            ]);

        $publishResponse->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'published'
                ]
            ]);

        // Unpublish the section
        $unpublishResponse = $this->actingAsAdmin()
            ->patchJson("/api/admin/sections/{$section->id}/status", [
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

    public function test_section_review_workflow()
    {
        $section = Section::create([
            'lesson_id' => $this->lesson->id,
            'title' => 'Writing あ',
            'content' => '<p>Learn how to write あ character</p>',
            'order' => 1,
            'status' => 'draft'
        ]);

        // Submit for review
        $submitResponse = $this->actingAsAdmin()
            ->postJson("/api/admin/sections/{$section->id}/submit-for-review");

        $submitResponse->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'review_status' => 'pending'
                ]
            ]);

        // Verify review record
        $this->assertDatabaseHas('reviews', [
            'content_type' => 'sections',
            'content_id' => $section->id,
            'status' => 'pending'
        ]);

        // Approve review
        $approveResponse = $this->actingAsAdmin()
            ->postJson("/api/admin/sections/{$section->id}/approve-review", [
                'feedback' => 'Content is clear and accurate'
            ]);

        $approveResponse->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'published',
                    'review_status' => 'approved'
                ]
            ]);
    }

    public function test_section_media_validations()
    {
        $oversizedVideo = UploadedFile::fake()->create(
            'big.mp4',
            51 * 1024, // 51MB
            'video/mp4'
        );

        $invalidAudio = UploadedFile::fake()->create(
            'invalid.m4a',
            100,
            'audio/m4a'
        );

        $invalidDocument = UploadedFile::fake()->create(
            'invalid.txt',
            100,
            'text/plain'
        );

        $response = $this->actingAsAdmin()
            ->postJson('/api/admin/sections', [
                'lesson_id' => $this->lesson->id,
                'title' => 'Writing あ',
                'content' => '<p>Content</p>',
                'order' => 1,
                'video_files' => [$oversizedVideo],
                'audio_files' => [$invalidAudio],
                'attachments' => [$invalidDocument]
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'video_files.0' => 'The video file may not be greater than 50MB.',
                'audio_files.0' => 'The audio file must be a file of type: audio/mpeg, audio/wav.',
                'attachments.0' => 'The attachment must be a file of type: application/pdf, application/msword, application/vnd.openxmlformats-officedocument.wordprocessingml.document.'
            ]);
    }

    public function test_section_exercise_management()
    {
        $section = Section::create([
            'lesson_id' => $this->lesson->id,
            'title' => 'Writing あ',
            'content' => '<p>Learn how to write あ character</p>',
            'order' => 1,
            'status' => 'draft'
        ]);

        // Create exercises
        $exercises = [];
        for ($i = 1; $i <= 3; $i++) {
            $exercises[] = Exercise::create([
                'section_id' => $section->id,
                'title' => "Exercise $i",
                'type' => 'multiple_choice',
                'content' => [
                    'question' => "Question $i",
                    'options' => ['A', 'B', 'C'],
                    'correct' => 'A'
                ],
                'order' => $i
            ]);
        }

        // Test reordering
        $newOrder = [
            ['id' => $exercises[2]->id, 'order' => 1],
            ['id' => $exercises[0]->id, 'order' => 2],
            ['id' => $exercises[1]->id, 'order' => 3],
        ];

        $response = $this->actingAsAdmin()
            ->postJson("/api/admin/sections/{$section->id}/reorder-exercises", [
                'exercises' => $newOrder
            ]);

        $response->assertOk()
            ->assertJson(['success' => true]);

        foreach ($newOrder as $item) {
            $this->assertDatabaseHas('exercises', [
                'id' => $item['id'],
                'order' => $item['order']
            ]);
        }
    }
}