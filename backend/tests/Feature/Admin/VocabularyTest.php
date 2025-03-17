<?php

namespace Tests\Feature\Admin;

use App\Models\LearningPath;
use App\Models\Unit;
use App\Models\Lesson;
use App\Models\VocabularyItem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class VocabularyTest extends AdminTestCase
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

    public function test_admin_can_create_vocabulary_item()
    {
        $pronunciation = UploadedFile::fake()->create('audio.mp3', 100, 'audio/mpeg');
        $image = UploadedFile::fake()->image('context.jpg');

        $response = $this->actingAsAdmin()
            ->postJson('/api/admin/vocabulary', [
                'lesson_id' => $this->lesson->id,
                'word' => 'あめ',
                'reading' => 'ame',
                'translation' => 'rain',
                'part_of_speech' => 'noun',
                'difficulty_level' => 'beginner',
                'context_sentence' => 'あめがふっています。',
                'context_translation' => 'It is raining.',
                'notes' => 'Common weather-related vocabulary',
                'pronunciation' => $pronunciation,
                'context_image' => $image
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'word' => 'あめ',
                    'translation' => 'rain',
                    'part_of_speech' => 'noun'
                ]
            ]);

        $vocab = VocabularyItem::first();
        $this->assertNotNull($vocab->getFirstMedia('pronunciation'));
        $this->assertNotNull($vocab->getFirstMedia('context_images'));
    }

    public function test_admin_can_create_vocabulary_batch()
    {
        $vocabularyList = [
            [
                'word' => 'あめ',
                'reading' => 'ame',
                'translation' => 'rain',
                'part_of_speech' => 'noun'
            ],
            [
                'word' => 'ほし',
                'reading' => 'hoshi',
                'translation' => 'star',
                'part_of_speech' => 'noun'
            ],
            [
                'word' => 'つき',
                'reading' => 'tsuki',
                'translation' => 'moon',
                'part_of_speech' => 'noun'
            ]
        ];

        $response = $this->actingAsAdmin()
            ->postJson('/api/admin/vocabulary/batch', [
                'lesson_id' => $this->lesson->id,
                'vocabulary' => $vocabularyList
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'created_count' => 3
                ]
            ]);

        $this->assertDatabaseCount('vocabulary_items', 3);
    }

    public function test_admin_can_update_vocabulary_with_version_tracking()
    {
        $vocab = VocabularyItem::create([
            'lesson_id' => $this->lesson->id,
            'word' => 'あめ',
            'reading' => 'ame',
            'translation' => 'rain',
            'part_of_speech' => 'noun'
        ]);

        $response = $this->actingAsAdmin()
            ->putJson("/api/admin/vocabulary/{$vocab->id}", [
                'translation' => 'rain (weather)',
                'context_sentence' => 'New context sentence',
                'notes' => 'Updated notes'
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('content_versions', [
            'content_type' => 'vocabulary_items',
            'content_id' => $vocab->id,
            'changes' => json_encode([
                'translation' => [
                    'old' => 'rain',
                    'new' => 'rain (weather)'
                ],
                'context_sentence' => [
                    'old' => null,
                    'new' => 'New context sentence'
                ],
                'notes' => [
                    'old' => null,
                    'new' => 'Updated notes'
                ]
            ])
        ]);
    }

    public function test_admin_can_manage_vocabulary_review_status()
    {
        $vocab = VocabularyItem::create([
            'lesson_id' => $this->lesson->id,
            'word' => 'あめ',
            'reading' => 'ame',
            'translation' => 'rain',
            'part_of_speech' => 'noun',
            'status' => 'draft'
        ]);

        // Submit for review
        $submitResponse = $this->actingAsAdmin()
            ->postJson("/api/admin/vocabulary/{$vocab->id}/submit-for-review");

        $submitResponse->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'review_status' => 'pending'
                ]
            ]);

        // Approve review
        $approveResponse = $this->actingAsAdmin()
            ->postJson("/api/admin/vocabulary/{$vocab->id}/approve-review", [
                'feedback' => 'Translation and context are accurate'
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

    public function test_admin_can_import_vocabulary_from_file()
    {
        $csvContent = "word,reading,translation,part_of_speech\n" .
                     "あめ,ame,rain,noun\n" .
                     "ほし,hoshi,star,noun\n" .
                     "つき,tsuki,moon,noun";

        $file = UploadedFile::fake()->createWithContent(
            'vocabulary.csv',
            $csvContent
        );

        $response = $this->actingAsAdmin()
            ->postJson("/api/admin/vocabulary/import", [
                'lesson_id' => $this->lesson->id,
                'file' => $file
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'imported_count' => 3
                ]
            ]);

        $this->assertDatabaseHas('vocabulary_items', [
            'lesson_id' => $this->lesson->id,
            'word' => 'あめ',
            'reading' => 'ame'
        ]);
    }

    public function test_admin_can_delete_vocabulary()
    {
        $vocab = VocabularyItem::create([
            'lesson_id' => $this->lesson->id,
            'word' => 'あめ',
            'reading' => 'ame',
            'translation' => 'rain',
            'part_of_speech' => 'noun'
        ]);

        $response = $this->actingAsAdmin()
            ->deleteJson("/api/admin/vocabulary/{$vocab->id}");

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('vocabulary_items', ['id' => $vocab->id]);
    }
}