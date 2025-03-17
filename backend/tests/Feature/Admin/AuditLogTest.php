<?php

namespace Tests\Feature\Admin;

use App\Models\LearningPath;
use App\Models\AuditLog;
use App\Models\User;

class AuditLogTest extends AdminTestCase
{
    protected LearningPath $learningPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Create some test data and generate audit logs
        $this->learningPath = LearningPath::create([
            'title' => 'Original Title',
            'description' => 'Original description',
            'status' => 'draft'
        ]);

        // Create audit logs for various actions
        AuditLog::create([
            'user_id' => $this->admin->id,
            'action' => 'created',
            'area' => 'learning_paths',
            'auditable_type' => LearningPath::class,
            'auditable_id' => $this->learningPath->id,
            'changes' => [
                'title' => 'Original Title',
                'description' => 'Original description',
                'status' => 'draft'
            ],
            'ip_address' => '127.0.0.1'
        ]);

        // Update learning path to generate another audit log
        $this->learningPath->update([
            'title' => 'Updated Title'
        ]);
    }

    public function test_admin_can_view_audit_logs()
    {
        $response = $this->actingAsAdmin()
            ->getJson('/api/admin/audit-logs');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    [
                        'action' => 'updated',
                        'area' => 'learning_paths',
                        'changes' => [
                            'title' => [
                                'old' => 'Original Title',
                                'new' => 'Updated Title'
                            ]
                        ]
                    ],
                    [
                        'action' => 'created',
                        'area' => 'learning_paths',
                        'changes' => [
                            'title' => 'Original Title',
                            'description' => 'Original description',
                            'status' => 'draft'
                        ]
                    ]
                ]
            ]);
    }

    public function test_admin_can_filter_audit_logs_by_date_range()
    {
        $response = $this->actingAsAdmin()
            ->getJson('/api/admin/audit-logs?from=2025-01-01&to=2025-12-31');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'user_id',
                        'action',
                        'area',
                        'auditable_type',
                        'auditable_id',
                        'changes',
                        'ip_address',
                        'created_at'
                    ]
                ],
                'meta' => [
                    'date_range' => [
                        'from',
                        'to'
                    ]
                ]
            ]);
    }

    public function test_admin_can_filter_audit_logs_by_area()
    {
        $response = $this->actingAsAdmin()
            ->getJson('/api/admin/audit-logs?area=learning_paths');

        $response->assertOk();
        $data = $response->json('data');
        foreach ($data as $log) {
            $this->assertEquals('learning_paths', $log['area']);
        }
    }

    public function test_admin_can_filter_audit_logs_by_action()
    {
        $response = $this->actingAsAdmin()
            ->getJson('/api/admin/audit-logs?action=created');

        $response->assertOk();
        $data = $response->json('data');
        foreach ($data as $log) {
            $this->assertEquals('created', $log['action']);
        }
    }

    public function test_admin_can_filter_audit_logs_by_user()
    {
        $response = $this->actingAsAdmin()
            ->getJson("/api/admin/audit-logs?user_id={$this->admin->id}");

        $response->assertOk();
        $data = $response->json('data');
        foreach ($data as $log) {
            $this->assertEquals($this->admin->id, $log['user_id']);
        }
    }

    public function test_admin_can_view_specific_audit_log()
    {
        $log = AuditLog::first();

        $response = $this->actingAsAdmin()
            ->getJson("/api/admin/audit-logs/{$log->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $log->id,
                    'action' => $log->action,
                    'area' => $log->area,
                    'changes' => $log->changes
                ]
            ]);
    }

    public function test_unauthorized_user_cannot_view_audit_logs()
    {
        $response = $this->actingAsUser()
            ->getJson('/api/admin/audit-logs');

        $this->assertUnauthorized($response);
    }

    public function test_audit_log_export()
    {
        $response = $this->actingAsAdmin()
            ->getJson('/api/admin/audit-logs?format=csv');

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/csv')
            ->assertHeader('Content-Disposition', 'attachment; filename=audit-logs.csv');
    }

    public function test_audit_logs_pagination()
    {
        // Create many audit logs
        for ($i = 0; $i < 30; $i++) {
            AuditLog::create([
                'user_id' => $this->admin->id,
                'action' => 'viewed',
                'area' => 'learning_paths',
                'auditable_type' => LearningPath::class,
                'auditable_id' => $this->learningPath->id,
                'changes' => [],
                'ip_address' => '127.0.0.1'
            ]);
        }

        $response = $this->actingAsAdmin()
            ->getJson('/api/admin/audit-logs?page=2&per_page=15');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data',
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total'
                ]
            ]);

        $this->assertEquals(2, $response->json('meta.current_page'));
        $this->assertEquals(15, $response->json('meta.per_page'));
    }
}