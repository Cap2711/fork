<?php

namespace Tests\Feature\Admin;

use App\Models\LearningPath;
use App\Models\Unit;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class QuizTest extends AdminTestCase
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

    public function test_admin_can_create_unit_quiz()
    {
        $response = $this->actingAsAdmin()
            ->postJson('/api/admin/quizzes', [
                'unit_id' => $this->unit->id,
                'title' => 'Hiragana Basics Quiz',
                'description' => 'Test your knowledge of basic Hiragana characters',
                'pass_score' => 80,
                'time_limit' => 600, // 10 minutes
                'questions' => [
                    [
                        'type' => 'multiple_choice',
                        'content' => [
                            'question' => 'What is the correct reading for あ?',
                            'options' => ['a', 'i', 'u', 'e'],
                            'correct_answer' => 'a',
                            'explanation' => 'あ is pronounced as "a" in Japanese'
                        ],
                        'points' => 10
                    ],
                    [
                        'type' => 'writing',
                        'content' => [
                            'prompt' => 'Write the hiragana character for "i"',
                            'correct_answer' => 'い',
                            'stroke_count' => 2
                        ],
                        'points' => 15
                    ]
                ]
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'title' => 'Hiragana Basics Quiz',
                    'pass_score' => 80,
                    'time_limit' => 600
                ]
            ]);

        $this->assertDatabaseHas('quizzes', [
            'unit_id' => $this->unit->id,
            'title' => 'Hiragana Basics Quiz'
        ]);

        $quiz = Quiz::first();
        $this->assertCount(2, $quiz->questions);
    }

    public function test_admin_can_update_quiz_with_version_tracking()
    {
        $quiz = Quiz::create([
            'unit_id' => $this->unit->id,
            'title' => 'Original Quiz',
            'description' => 'Test quiz',
            'pass_score' => 70,
            'time_limit' => 300
        ]);

        $response = $this->actingAsAdmin()
            ->putJson("/api/admin/quizzes/{$quiz->id}", [
                'title' => 'Updated Quiz',
                'description' => 'Updated description',
                'pass_score' => 75,
                'time_limit' => 400
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('content_versions', [
            'content_type' => 'quizzes',
            'content_id' => $quiz->id,
            'changes' => json_encode([
                'title' => [
                    'old' => 'Original Quiz',
                    'new' => 'Updated Quiz'
                ],
                'description' => [
                    'old' => 'Test quiz',
                    'new' => 'Updated description'
                ],
                'pass_score' => [
                    'old' => 70,
                    'new' => 75
                ],
                'time_limit' => [
                    'old' => 300,
                    'new' => 400
                ]
            ])
        ]);
    }

    public function test_admin_can_manage_quiz_questions()
    {
        $quiz = Quiz::create([
            'unit_id' => $this->unit->id,
            'title' => 'Hiragana Quiz',
            'description' => 'Test your knowledge',
            'pass_score' => 70,
            'time_limit' => 300
        ]);

        // Add question
        $addResponse = $this->actingAsAdmin()
            ->postJson('/api/admin/quiz-questions', [
                'quiz_id' => $quiz->id,
                'type' => 'multiple_choice',
                'content' => [
                    'question' => 'What is あ?',
                    'options' => ['a', 'i', 'u'],
                    'correct_answer' => 'a'
                ],
                'points' => 10,
                'order' => 1
            ]);

        $addResponse->assertStatus(201);

        // Update question
        $question = QuizQuestion::first();
        $updateResponse = $this->actingAsAdmin()
            ->putJson("/api/admin/quiz-questions/{$question->id}", [
                'content' => [
                    'question' => 'Updated question',
                    'options' => ['x', 'y', 'z'],
                    'correct_answer' => 'x'
                ]
            ]);

        $updateResponse->assertOk();

        // Delete question
        $deleteResponse = $this->actingAsAdmin()
            ->deleteJson("/api/admin/quiz-questions/{$question->id}");

        $deleteResponse->assertOk();
        $this->assertDatabaseMissing('quiz_questions', ['id' => $question->id]);
    }

    public function test_quiz_review_workflow()
    {
        $quiz = Quiz::create([
            'unit_id' => $this->unit->id,
            'title' => 'Hiragana Quiz',
            'description' => 'Test your knowledge',
            'pass_score' => 70,
            'time_limit' => 300,
            'status' => 'draft'
        ]);

        // Submit for review
        $submitResponse = $this->actingAsAdmin()
            ->postJson("/api/admin/quizzes/{$quiz->id}/submit-for-review");

        $submitResponse->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'review_status' => 'pending'
                ]
            ]);

        // Approve review
        $approveResponse = $this->actingAsAdmin()
            ->postJson("/api/admin/quizzes/{$quiz->id}/approve-review", [
                'feedback' => 'Good quiz structure!'
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

    public function test_admin_can_import_quiz_template()
    {
        $template = [
            'title' => 'Template Quiz',
            'description' => 'Quiz from template',
            'pass_score' => 70,
            'time_limit' => 300,
            'questions' => [
                [
                    'type' => 'multiple_choice',
                    'content' => [
                        'question' => 'Template question 1',
                        'options' => ['A', 'B', 'C'],
                        'correct_answer' => 'A'
                    ],
                    'points' => 10
                ],
                [
                    'type' => 'writing',
                    'content' => [
                        'prompt' => 'Template question 2',
                        'correct_answer' => 'Answer'
                    ],
                    'points' => 15
                ]
            ]
        ];

        $response = $this->actingAsAdmin()
            ->postJson("/api/admin/quizzes/import", [
                'unit_id' => $this->unit->id,
                'template' => $template
            ]);

        $response->assertStatus(201);

        $quiz = Quiz::first();
        $this->assertCount(2, $quiz->questions);
    }
}