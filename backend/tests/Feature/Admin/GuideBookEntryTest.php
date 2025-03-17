<?php

namespace Tests\Feature\Admin;

use App\Models\LearningPath;
use App\Models\Unit;
use App\Models\GuideBookEntry;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class GuideBookEntryTest extends AdminTestCase
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

    public function test_admin_can_create_guide_book_entry()
    {
        $diagram = UploadedFile::fake()->image('diagram.jpg');
        $pdf = UploadedFile::fake()->create('reference.pdf', 100, 'application/pdf');

        $response = $this->actingAsAdmin()
            ->postJson('/api/admin/guide-entries', [
                'unit_id' => $this->unit->id,
                'title' => 'Hiragana Writing System',
                'content' => [
                    'sections' => [
                        [
                            'title' => 'Overview',
                            'text' => 'Hiragana is one of the Japanese writing systems...'
                        ],
                        [
                            'title' => 'Historical Background',
                            'text' => 'Hiragana developed from Chinese characters...'
                        ]
                    ],
                    'key_points' => [
                        'Used primarily for native Japanese words',
                        'Consists of 46 basic characters',
                        'Written in a flowing, cursive style'
                    ]
                ],
                'type' => 'writing_system',
                'tags' => ['grammar', 'writing', 'basics'],
                'diagrams' => [$diagram],
                'attachments' => [$pdf]
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'title' => 'Hiragana Writing System',
                    'type' => 'writing_system'
                ]
            ]);

        $entry = GuideBookEntry::first();
        $this->assertNotNull($entry->getFirstMedia('diagrams'));
        $this->assertNotNull($entry->getFirstMedia('attachments'));
    }

    public function test_admin_can_update_guide_entry_with_version_tracking()
    {
        $entry = GuideBookEntry::create([
            'unit_id' => $this->unit->id,
            'title' => 'Original Guide',
            'content' => [
                'sections' => [
                    [
                        'title' => 'Original Section',
                        'text' => 'Original content'
                    ]
                ]
            ],
            'type' => 'grammar',
            'tags' => ['basic']
        ]);

        $response = $this->actingAsAdmin()
            ->putJson("/api/admin/guide-entries/{$entry->id}", [
                'title' => 'Updated Guide',
                'content' => [
                    'sections' => [
                        [
                            'title' => 'Updated Section',
                            'text' => 'Updated content'
                        ]
                    ]
                ],
                'tags' => ['advanced', 'grammar']
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('content_versions', [
            'content_type' => 'guide_book_entries',
            'content_id' => $entry->id,
            'changes' => json_encode([
                'title' => [
                    'old' => 'Original Guide',
                    'new' => 'Updated Guide'
                ],
                'content' => [
                    'old' => [
                        'sections' => [
                            [
                                'title' => 'Original Section',
                                'text' => 'Original content'
                            ]
                        ]
                    ],
                    'new' => [
                        'sections' => [
                            [
                                'title' => 'Updated Section',
                                'text' => 'Updated content'
                            ]
                        ]
                    ]
                ],
                'tags' => [
                    'old' => ['basic'],
                    'new' => ['advanced', 'grammar']
                ]
            ])
        ]);
    }

    public function test_guide_entry_review_workflow()
    {
        $entry = GuideBookEntry::create([
            'unit_id' => $this->unit->id,
            'title' => 'Hiragana Guide',
            'content' => [
                'sections' => [
                    [
                        'title' => 'Introduction',
                        'text' => 'Guide content'
                    ]
                ]
            ],
            'type' => 'writing_system',
            'status' => 'draft'
        ]);

        // Submit for review
        $submitResponse = $this->actingAsAdmin()
            ->postJson("/api/admin/guide-entries/{$entry->id}/submit-for-review");

        $submitResponse->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'review_status' => 'pending'
                ]
            ]);

        // Approve review
        $approveResponse = $this->actingAsAdmin()
            ->postJson("/api/admin/guide-entries/{$entry->id}/approve-review", [
                'feedback' => 'Content is accurate and well-structured'
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

    public function test_admin_can_manage_guide_entry_media()
    {
        $entry = GuideBookEntry::create([
            'unit_id' => $this->unit->id,
            'title' => 'Hiragana Guide',
            'content' => ['sections' => []],
            'type' => 'writing_system'
        ]);

        $diagram = UploadedFile::fake()->image('new-diagram.jpg');

        // Add media
        $addResponse = $this->actingAsAdmin()
            ->postJson("/api/admin/guide-entries/{$entry->id}/media", [
                'type' => 'diagrams',
                'files' => [$diagram]
            ]);

        $addResponse->assertOk();
        $this->assertCount(1, $entry->fresh()->getMedia('diagrams'));

        // Remove media
        $media = $entry->getFirstMedia('diagrams');
        $removeResponse = $this->actingAsAdmin()
            ->deleteJson("/api/admin/guide-entries/{$entry->id}/media/{$media->id}");

        $removeResponse->assertOk();
        $this->assertCount(0, $entry->fresh()->getMedia('diagrams'));
    }

    public function test_admin_can_reorder_guide_entries()
    {
        $entries = [];
        for ($i = 1; $i <= 3; $i++) {
            $entries[] = GuideBookEntry::create([
                'unit_id' => $this->unit->id,
                'title' => "Guide Entry $i",
                'content' => ['sections' => []],
                'type' => 'grammar',
                'order' => $i
            ]);
        }

        $newOrder = [
            ['id' => $entries[2]->id, 'order' => 1],
            ['id' => $entries[0]->id, 'order' => 2],
            ['id' => $entries[1]->id, 'order' => 3],
        ];

        $response = $this->actingAsAdmin()
            ->postJson("/api/admin/guide-entries/reorder", [
                'entries' => $newOrder
            ]);

        $response->assertOk();

        foreach ($newOrder as $item) {
            $this->assertDatabaseHas('guide_book_entries', [
                'id' => $item['id'],
                'order' => $item['order']
            ]);
        }
    }

    public function test_admin_can_delete_guide_entry()
    {
        $entry = GuideBookEntry::create([
            'unit_id' => $this->unit->id,
            'title' => 'Test Guide',
            'content' => ['sections' => []],
            'type' => 'grammar'
        ]);

        $diagram = UploadedFile::fake()->image('diagram.jpg');
        $entry->addMedia($diagram)->toMediaCollection('diagrams');

        $response = $this->actingAsAdmin()
            ->deleteJson("/api/admin/guide-entries/{$entry->id}");

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('guide_book_entries', ['id' => $entry->id]);
        $this->assertEmpty($entry->getMedia('diagrams'));
    }
}