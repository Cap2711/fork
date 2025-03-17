<?php

namespace Tests\Feature\Admin;

use App\Models\LearningPath;
use App\Models\Unit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class LearningPathTest extends AdminTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_unauthorized_user_cannot_access_learning_paths()
    {
        $response = $this->actingAsUser()
            ->getJson('/api/admin/learning-paths');

        $this->assertUnauthorized($response);
    }

    public function test_admin_can_list_learning_paths()
    {
        $learningPath = LearningPath::create([
            'title' => 'Japanese Basics',
            'description' => 'Learn basic Japanese',
            'target_level' => 'beginner',
            'status' => 'draft'
        ]);

        $response = $this->actingAsAdmin()
            ->getJson('/api/admin/learning-paths');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    [
                        'title' => 'Japanese Basics',
                        'description' => 'Learn basic Japanese',
                        'target_level' => 'beginner',
                        'status' => 'draft'
                    ]
                ]
            ]);
    }

    public function test_admin_can_create_learning_path()
    {
        $thumbnail = UploadedFile::fake()->image('thumbnail.jpg');

        $response = $this->actingAsAdmin()
            ->postJson('/api/admin/learning-paths', [
                'title' => 'Korean Basics',
                'description' => 'Learn basic Korean',
                'target_level' => 'beginner',
                'thumbnail' => $thumbnail
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'title' => 'Korean Basics',
                    'description' => 'Learn basic Korean',
                    'target_level' => 'beginner',
                    'status' => 'draft'
                ]
            ]);

        $this->assertDatabaseHas('learning_paths', [
            'title' => 'Korean Basics',
            'description' => 'Learn basic Korean',
            'target_level' => 'beginner',
            'status' => 'draft'
        ]);

        // Assert thumbnail was stored
        $learningPath = LearningPath::first();
        $this->assertNotNull($learningPath->getFirstMedia('thumbnail'));
    }

    public function test_admin_can_update_learning_path()
    {
        $learningPath = LearningPath::create([
            'title' => 'Japanese Basics',
            'description' => 'Learn basic Japanese',
            'target_level' => 'beginner',
            'status' => 'draft'
        ]);

        $response = $this->actingAsAdmin()
            ->putJson("/api/admin/learning-paths/{$learningPath->id}", [
                'title' => 'Japanese Basics Updated',
                'description' => 'Learn basic Japanese with updates',
                'target_level' => 'intermediate'
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'title' => 'Japanese Basics Updated',
                    'description' => 'Learn basic Japanese with updates',
                    'target_level' => 'intermediate'
                ]
            ]);

        // Verify version was created
        $this->assertDatabaseHas('content_versions', [
            'content_type' => 'learning_paths',
            'content_id' => $learningPath->id,
            'changes' => json_encode([
                'title' => [
                    'old' => 'Japanese Basics',
                    'new' => 'Japanese Basics Updated'
                ],
                'description' => [
                    'old' => 'Learn basic Japanese',
                    'new' => 'Learn basic Japanese with updates'
                ],
                'target_level' => [
                    'old' => 'beginner',
                    'new' => 'intermediate'
                ]
            ])
        ]);
    }

    public function test_admin_can_manage_learning_path_status()
    {
        $learningPath = LearningPath::create([
            'title' => 'Japanese Basics',
            'description' => 'Learn basic Japanese',
            'target_level' => 'beginner',
            'status' => 'draft'
        ]);

        // Publish the learning path
        $publishResponse = $this->actingAsAdmin()
            ->patchJson("/api/admin/learning-paths/{$learningPath->id}/status", [
                'status' => 'published'
            ]);

        $publishResponse->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'published'
                ]
            ]);

        // Unpublish the learning path
        $unpublishResponse = $this->actingAsAdmin()
            ->patchJson("/api/admin/learning-paths/{$learningPath->id}/status", [
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

    public function test_admin_can_review_learning_path()
    {
        $learningPath = LearningPath::create([
            'title' => 'Japanese Basics',
            'description' => 'Learn basic Japanese',
            'target_level' => 'beginner',
            'status' => 'draft'
        ]);

        // Submit for review
        $submitResponse = $this->actingAsAdmin()
            ->postJson("/api/admin/learning-paths/{$learningPath->id}/submit-for-review");

        $submitResponse->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'review_status' => 'pending'
                ]
            ]);

        // Verify review record
        $this->assertDatabaseHas('reviews', [
            'content_type' => 'learning_paths',
            'content_id' => $learningPath->id,
            'status' => 'pending'
        ]);

        // Approve review
        $approveResponse = $this->actingAsAdmin()
            ->postJson("/api/admin/learning-paths/{$learningPath->id}/approve-review", [
                'feedback' => 'Excellent content structure!'
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
            'content_type' => 'learning_paths',
            'content_id' => $learningPath->id,
            'status' => 'approved',
            'feedback' => 'Excellent content structure!'
        ]);
    }

    public function test_admin_can_reorder_units()
    {
        $learningPath = LearningPath::create([
            'title' => 'Japanese Basics',
            'description' => 'Learn basic Japanese',
            'target_level' => 'beginner',
            'status' => 'draft'
        ]);

        $units = [];
        for ($i = 1; $i <= 3; $i++) {
            $units[] = $learningPath->units()->create([
                'title' => "Unit $i",
                'description' => "Description $i",
                'order' => $i
            ]);
        }

        $newOrder = [
            ['id' => $units[2]->id, 'order' => 1],
            ['id' => $units[0]->id, 'order' => 2],
            ['id' => $units[1]->id, 'order' => 3],
        ];

        $response = $this->actingAsAdmin()
            ->postJson("/api/admin/learning-paths/{$learningPath->id}/reorder-units", [
                'units' => $newOrder
            ]);

        $response->assertOk()
            ->assertJson(['success' => true]);

        foreach ($newOrder as $item) {
            $this->assertDatabaseHas('units', [
                'id' => $item['id'],
                'order' => $item['order']
            ]);
        }
    }
}