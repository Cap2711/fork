<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\API\BaseAPIController;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RoleController extends BaseAPIController
{
    /**
     * Display a listing of roles.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Role::query();

        // Filter system/custom roles
        if ($request->has('system')) {
            $query->where('is_system', $request->boolean('system'));
        }

        // Search by name
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $roles = $query->with('permissions')
            ->orderBy('name')
            ->paginate($request->input('per_page', 15));

        return $this->sendPaginatedResponse($roles);
    }

    /**
     * Store a newly created role.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'description' => 'nullable|string',
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,id'
        ]);

        return DB::transaction(function () use ($request) {
            $role = Role::create([
                'name' => $request->name,
                'slug' => Str::slug($request->name),
                'description' => $request->description,
                'is_system' => false
            ]);

            if ($request->has('permissions')) {
                $role->permissions()->attach($request->permissions);
            }

            $role->load('permissions');
            return $this->sendCreatedResponse($role);
        });
    }

    /**
     * Display the specified role.
     */
    public function show(Role $role): JsonResponse
    {
        $role->load(['permissions', 'users']);
        return $this->sendResponse($role);
    }

    /**
     * Update the specified role.
     */
    public function update(Request $request, Role $role): JsonResponse
    {
        if ($role->is_system) {
            return $this->sendError('System roles cannot be modified.', 403);
        }

        $request->validate([
            'name' => "required|string|max:255|unique:roles,name,{$role->id}",
            'description' => 'nullable|string'
        ]);

        $role->update([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'description' => $request->description
        ]);

        return $this->sendResponse($role);
    }

    /**
     * Remove the specified role.
     */
    public function destroy(Role $role): JsonResponse
    {
        if ($role->is_system) {
            return $this->sendError('System roles cannot be deleted.', 403);
        }

        if ($role->users()->exists()) {
            return $this->sendError('Cannot delete role with assigned users.', 409);
        }

        $role->delete();
        return $this->sendNoContentResponse();
    }

    /**
     * Update role permissions.
     */
    public function updatePermissions(Request $request, Role $role): JsonResponse
    {
        if ($role->is_system) {
            return $this->sendError('System role permissions cannot be modified.', 403);
        }

        $request->validate([
            'permissions' => 'required|array',
            'permissions.*.id' => 'required|exists:permissions,id',
            'permissions.*.granted' => 'required|boolean',
            'permissions.*.conditions' => 'nullable|array'
        ]);

        return DB::transaction(function () use ($request, $role) {
            // Clear existing permissions
            $role->permissions()->detach();

            // Attach new permissions with conditions
            foreach ($request->permissions as $permission) {
                if ($permission['granted']) {
                    $role->permissions()->attach($permission['id'], [
                        'conditions' => $permission['conditions'] ?? null,
                        'is_denied' => false
                    ]);
                }
            }

            $role->load('permissions');
            return $this->sendResponse($role);
        });
    }

    /**
     * Get all available permissions grouped by area.
     */
    public function getAvailablePermissions(): JsonResponse
    {
        $permissions = Permission::all()
            ->groupBy('group')
            ->map(function ($group) {
                return $group->map(function ($permission) {
                    return [
                        'id' => $permission->id,
                        'name' => $permission->name,
                        'slug' => $permission->slug,
                        'description' => $permission->description,
                        'is_system' => $permission->is_system,
                        'conditions' => $permission->conditions
                    ];
                });
            });

        return $this->sendResponse($permissions);
    }

    /**
     * Get role statistics.
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'total_roles' => Role::count(),
            'system_roles' => Role::where('is_system', true)->count(),
            'custom_roles' => Role::where('is_system', false)->count(),
            'roles_with_users' => Role::whereHas('users')->count(),
            'permissions_per_role' => DB::table('permission_role')
                ->selectRaw('role_id, COUNT(*) as count')
                ->groupBy('role_id')
                ->get()
                ->avg('count') ?? 0,
            'most_used_permissions' => DB::table('permission_role')
                ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
                ->selectRaw('permissions.name, COUNT(*) as usage_count')
                ->groupBy('permissions.id', 'permissions.name')
                ->orderByDesc('usage_count')
                ->limit(5)
                ->get()
        ];

        return $this->sendResponse($stats);
    }
}