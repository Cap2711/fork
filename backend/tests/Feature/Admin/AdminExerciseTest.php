<?php

namespace Tests\Feature\Admin;

use App\Models\LearningPath;
use App\Models\Unit;
use App\Models\Lesson;
use App\Models\Section;
use App\Models\Exercise;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class AdminExerciseTest extends AdminTestCase
{
    protected LearningPath $learningPath;
    protected Unit $unit;
    protected Lesson $lesson;
    protected Section $section;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        // Create full content hierarchy
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

        $this->section = Section::create([
            'lesson_id' => $this->lesson->id,
            'title' => 'Writing あ',
            'content' => '<p>Learn how to write あ character</p>',
            'order' => 1,
            'status' => 'draft'
        ]);
    }

    public function test_admin_can_create_multiple_choice_exercise()
    {
        $response = $this->actingAsAdmin()
            ->postJson('/api/admin/exercises', [
                'section_id' => $this->section->id,
                'title' => 'Identify あ',
                'type' => 'multiple_choice',
                'order' => 1,
                'content' => [
                    'question' => 'Which character is あ?',
                    'options' => ['あ', 'い', 'う', 'え'],
                    'correct_answer' => 'あ',
                    'explanation' => 'あ is the hiragana character for "a"'
                ]
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'title' => 'Identify あ',
                    'type' => 'multiple_choice',
                    'order' => 1
                ]
            ]);
    }

    public function test_admin_can_create_writing_exercise()
    {
        $strokeOrder = UploadedFile::fake()->image('stroke-order.gif');
        $audio = UploadedFile::fake()->create('pronunciation.mp3', 100, 'audio/mpeg');

        $response = $this->actingAsAdmin()
            ->postJson('/api/admin/exercises', [
                'section_id' => $this->section->id,
                'title' => 'Write あ',
                'type' => 'writing',
                'order' => 2,
                'content' => [
                    'character' => 'あ',
                    'stroke_count' => 3,
                    'instructions' => 'Write あ following the stroke order',
                    'example_words' => ['あめ (ame) - rain', 'あか (aka) - red']
                ],
                'stroke_order_animation' => $strokeOrder,
                'pronunciation' => $audio
            ]);

        $response->assertStatus(201);

        $exercise = Exercise::first();
        $this->assertNotNull($exercise->getFirstMedia('stroke_order_animation'));
        $this->assertNotNull($exercise->getFirstMedia('pronunciation'));
    }

    public function test_admin_can_create_listening_exercise()
    {
        $audio = UploadedFile::fake()->create('word.mp3', 100, 'audio/mpeg');

        $response = $this->actingAsAdmin()
            ->postJson('/api/admin/exercises', [
                'section_id' => $this->section->id,
                'title' => 'Listen and Choose',
                'type' => 'listening',
                'order' => 3,
                'content' => [
                    'question' => 'Listen and select the correct word',
                    'options' => ['あめ', 'いぬ', 'うみ'],
                    'correct_answer' => 'あめ',
                    'translation' => 'rain'
                ],
                'audio' => $audio
            ]);

        $response->assertStatus(201);
        
        $exercise = Exercise::first();
        $this->assertNotNull($exercise->getFirstMedia('audio'));
    }

    public function test_admin_can_create_matching_exercise()
    {
        $response = $this->actingAsAdmin()
            ->postJson('/api/admin/exercises', [
                'section_id' => $this->section->id,
                'title' => 'Match Hiragana',
                'type' => 'matching',
                'order' => 4,
                'content' => [
                    'pairs' => [
                        ['あ', 'a'],
                        ['い', 'i'],
                        ['う', 'u']
                    ],
                    'instructions' => 'Match the hiragana characters with their romaji'
                ]
            ]);

        $response->assertStatus(201);
    }

    public function test_exercise_versioning()
    {
        $exercise = Exercise::create([
            'section_id' => $this->section->id,
            'title' => 'Original Exercise',
            'type' => 'multiple_choice',
            'order' => 1,
            'content' => [
                'question' => 'Original question',
                'options' => ['A', 'B', 'C'],
                'correct_answer' => 'A'
            ]
        ]);

        $response = $this->actingAsAdmin()
            ->putJson("/api/admin/exercises/{$exercise->id}", [
                'title' => 'Updated Exercise',
                'content' => [
                    'question' => 'Updated question',
                    'options' => ['X', 'Y', 'Z'],
                    'correct_answer' => 'X'
                ]
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('content_versions', [
            'content_type' => 'exercises',
            'content_id' => $exercise->id
        ]);
    }

    public function test_exercise_review_workflow()
    {
        $exercise = Exercise::create([
            'section_id' => $this->section->id,
            'title' => 'Test Exercise',
            'type' => 'multiple_choice',
            'order' => 1,
            'content' => [
                'question' => 'Test question',
                'options' => ['A', 'B', 'C'],
                'correct_answer' => 'A'
            ],
            'status' => 'draft'
        ]);

        // Submit for review
        $submitResponse = $this->actingAsAdmin()
            ->postJson("/api/admin/exercises/{$exercise->id}/submit-for-review");

        $submitResponse->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'review_status' => 'pending'
                ]
            ]);

        // Approve review
        $approveResponse = $this->actingAsAdmin()
            ->postJson("/api/admin/exercises/{$exercise->id}/approve-review", [
                'feedback' => 'Good exercise!'
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

    public function test_admin_can_delete_exercise()
    {
        $exercise = Exercise::create([
            'section_id' => $this->section->id,
            'title' => 'Test Exercise',
            'type' => 'multiple_choice',
            'order' => 1,
            'content' => [
                'question' => 'Test question',
                'options' => ['A', 'B', 'C'],
                'correct_answer' => 'A'
            ]
        ]);

        $response = $this->actingAsAdmin()
            ->deleteJson("/api/admin/exercises/{$exercise->id}");

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('exercises', ['id' => $exercise->id]);
    }
}