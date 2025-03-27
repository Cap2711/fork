<?php

namespace Tests\Feature\Admin;

use App\Models\LearningPath;
use App\Models\Unit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class AdminUnitTest extends AdminTestCase
{
    protected LearningPath $learningPath;

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
    }

    public function test_unauthorized_user_cannot_access_units()
    {
        $response = $this->actingAsUser()
            ->getJson('/api/admin/units');

        $this->assertUnauthorized($response);
    }

    public function test_admin_can_list_units()
    {
        $unit = Unit::create([
            'learning_path_id' => $this->learningPath->id,
            'title' => 'Introduction to Hiragana',
            'description' => 'Learn the basics of Hiragana writing system',
            'order' => 1,
            'status' => 'draft'
        ]);

        $response = $this->actingAsAdmin()
            ->getJson('/api/admin/units');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    [
                        'title' => 'Introduction to Hiragana',
                        'description' => 'Learn the basics of Hiragana writing system',
                        'order' => 1,
                        'status' => 'draft'
                    ]
                ]
            ]);
    }

    public function test_admin_can_create_unit()
    {
        $thumbnail = UploadedFile::fake()->image('thumbnail.jpg');

        $response = $this->actingAsAdmin()
            ->postJson('/api/admin/units', [
                'learning_path_id' => $this->learningPath->id,
                'title' => 'Introduction to Hiragana',
                'description' => 'Learn the basics of Hiragana writing system',
                'order' => 1,
                'thumbnail' => $thumbnail
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'title' => 'Introduction to Hiragana',
                    'description' => 'Learn the basics of Hiragana writing system',
                    'order' => 1,
                    'status' => 'draft'
                ]
            ]);

        $this->assertDatabaseHas('units', [
            'learning_path_id' => $this->learningPath->id,
            'title' => 'Introduction to Hiragana',
            'description' => 'Learn the basics of Hiragana writing system',
            'order' => 1
        ]);

        // Assert thumbnail was stored
        $unit = Unit::first();
        $this->assertNotNull($unit->getFirstMedia('thumbnail'));
    }

    public function test_admin_can_update_unit_with_version_tracking()
    {
        $unit = Unit::create([
            'learning_path_id' => $this->learningPath->id,
            'title' => 'Introduction to Hiragana',
            'description' => 'Learn the basics of Hiragana writing system',
            'order' => 1,
            'status' => 'draft'
        ]);

        $response = $this->actingAsAdmin()
            ->putJson("/api/admin/units/{$unit->id}", [
                'title' => 'Updated Hiragana Introduction',
                'description' => 'Updated description'
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'title' => 'Updated Hiragana Introduction',
                    'description' => 'Updated description'
                ]
            ]);

        // Verify version was created with changes
        $this->assertDatabaseHas('content_versions', [
            'content_type' => 'units',
            'content_id' => $unit->id,
            'changes' => json_encode([
                'title' => [
                    'old' => 'Introduction to Hiragana',
                    'new' => 'Updated Hiragana Introduction'
                ],
                'description' => [
                    'old' => 'Learn the basics of Hiragana writing system',
                    'new' => 'Updated description'
                ]
            ])
        ]);
    }

    public function test_admin_can_manage_unit_status()
    {
        $unit = Unit::create([
            'learning_path_id' => $this->learningPath->id,
            'title' => 'Introduction to Hiragana',
            'description' => 'Learn the basics of Hiragana writing system',
            'order' => 1,
            'status' => 'draft'
        ]);

        // Publish the unit
        $publishResponse = $this->actingAsAdmin()
            ->patchJson("/api/admin/units/{$unit->id}/status", [
                'status' => 'published'
            ]);

        $publishResponse->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'published'
                ]
            ]);

        // Unpublish the unit
        $unpublishResponse = $this->actingAsAdmin()
            ->patchJson("/api/admin/units/{$unit->id}/status", [
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

    public function test_admin_can_manage_unit_review_workflow()
    {
        $unit = Unit::create([
            'learning_path_id' => $this->learningPath->id,
            'title' => 'Introduction to Hiragana',
            'description' => 'Learn the basics of Hiragana writing system',
            'order' => 1,
            'status' => 'draft'
        ]);

        // Submit for review
        $submitResponse = $this->actingAsAdmin()
            ->postJson("/api/admin/units/{$unit->id}/submit-for-review");

        $submitResponse->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'review_status' => 'pending'
                ]
            ]);

        // Verify review record
        $this->assertDatabaseHas('reviews', [
            'content_type' => 'units',
            'content_id' => $unit->id,
            'status' => 'pending'
        ]);

        // Approve review
        $approveResponse = $this->actingAsAdmin()
            ->postJson("/api/admin/units/{$unit->id}/approve-review", [
                'feedback' => 'Great unit structure!'
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
            'content_type' => 'units',
            'content_id' => $unit->id,
            'status' => 'approved',
            'feedback' => 'Great unit structure!'
        ]);
    }

    public function test_admin_can_reorder_lessons()
    {
        $unit = Unit::create([
            'learning_path_id' => $this->learningPath->id,
            'title' => 'Introduction to Hiragana',
            'description' => 'Learn the basics of Hiragana writing system',
            'order' => 1,
            'status' => 'draft'
        ]);

        $lessons = [];
        for ($i = 1; $i <= 3; $i++) {
            $lessons[] = $unit->lessons()->create([
                'title' => "Lesson $i",
                'description' => "Description $i",
                'order' => $i
            ]);
        }

        $newOrder = [
            ['id' => $lessons[2]->id, 'order' => 1],
            ['id' => $lessons[0]->id, 'order' => 2],
            ['id' => $lessons[1]->id, 'order' => 3],
        ];

        $response = $this->actingAsAdmin()
            ->postJson("/api/admin/units/{$unit->id}/reorder-lessons", [
                'lessons' => $newOrder
            ]);

        $response->assertOk()
            ->assertJson(['success' => true]);

        foreach ($newOrder as $item) {
            $this->assertDatabaseHas('lessons', [
                'id' => $item['id'],
                'order' => $item['order']
            ]);
        }
    }

    public function test_admin_can_delete_unit()
    {
        $unit = Unit::create([
            'learning_path_id' => $this->learningPath->id,
            'title' => 'Introduction to Hiragana',
            'description' => 'Learn the basics of Hiragana writing system',
            'order' => 1,
            'status' => 'draft'
        ]);

        $response = $this->actingAsAdmin()
            ->deleteJson("/api/admin/units/{$unit->id}");

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('units', ['id' => $unit->id]);
    }
}