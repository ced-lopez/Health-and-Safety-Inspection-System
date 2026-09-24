<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Role;
use App\Models\User;
use App\Rules\StrongPassword;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AdminUserController extends BaseApiController
{
    public function index()
    {
        $users = User::query()
            ->with('role')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        return $this->success([
            'users' => $users->map(function (User $user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role' => $user->role ? [
                        'id' => $user->role->id,
                        'slug' => $user->role->slug,
                        'name' => $user->role->name,
                    ] : null,
                    'is_active' => (bool) $user->is_active,
                ];
            }),
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'min:2'],
            'email' => ['required', 'email', 'unique:users,email'],
            'phone' => ['nullable', 'string'],
            'password' => ['required', 'string', new StrongPassword],
            'role' => ['required', 'string', 'in:administrator,inspector,barangay_staff,resident'],
        ]);

        if ($validator->fails()) {
            return $this->error(
                'Validation failed',
                422,
                ['errors' => $validator->errors()]
            );
        }

        $data = $validator->validated();

        $role = Role::query()->where('slug', $data['role'])->first();

        if (! $role) {
            return $this->error('Role not found', 404);
        }

        $user = User::query()->create([
            'role_id' => $role->id,
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $user->load('role');

        AuditLogger::log(
            $request->user(),
            'User Management',
            'Created',
            "Created {$role->name} account for {$user->name}",
            $user,
            $request,
            newValues: ['name' => $user->name, 'email' => $user->email, 'role' => $role->name],
            event: 'admin.user.created',
        );

        return $this->success(
            [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role' => $user->role ? [
                        'id' => $user->role->id,
                        'slug' => $user->role->slug,
                        'name' => $user->role->name,
                    ] : null,
                ],
            ],
            'User created successfully',
            201
        );
    }

    public function update(Request $request, User $user)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'string', 'min:2'],
            'email' => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string'],
            'password' => ['nullable', 'string', new StrongPassword],
            'role' => ['required', 'string', 'in:administrator,inspector,barangay_staff,resident'],
            'is_active' => ['required', 'boolean'],
        ]);

        if ($validator->fails()) {
            return $this->error(
                'Validation failed',
                422,
                ['errors' => $validator->errors()]
            );
        }

        $data = $validator->validated();

        $role = Role::query()->where('slug', $data['role'])->first();

        if (! $role) {
            return $this->error('Role not found', 404);
        }

        $wasActive = (bool) $user->is_active;
        $passwordChanged = ! empty($data['password']);

        $user->update([
            'name' => $data['name'] ?? $user->name,
            'email' => $data['email'] ?? $user->email,
            'phone' => array_key_exists('phone', $data) ? $data['phone'] : $user->phone,
            'role_id' => $role->id,
            'is_active' => $data['is_active'],
        ]);

        if ($passwordChanged) {
            $user->update(['password' => Hash::make($data['password'])]);
        }

        if (($wasActive && ! $data['is_active']) || $passwordChanged) {
            $user->tokens()->delete();
        }

        $user->load('role');

        AuditLogger::log(
            $request->user(),
            'User Management',
            'Updated',
            "Updated user account {$user->name}",
            $user,
            $request,
            newValues: [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $role->name,
                'is_active' => $data['is_active'],
            ],
            event: 'admin.user.updated',
        );

        return $this->success([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role ? [
                    'id' => $user->role->id,
                    'slug' => $user->role->slug,
                    'name' => $user->role->name,
                ] : null,
                'is_active' => (bool) $user->is_active,
            ],
        ], 'User updated successfully');
    }

    public function destroy(User $user): JsonResponse
    {
        if ($user->id === request()->user()->id) {
            return $this->error('You cannot delete your own account', 422);
        }

        $user->delete();

        AuditLogger::log(
            request()->user(),
            'User Management',
            'Deleted',
            "Soft-deleted user account {$user->name} (ID {$user->id})",
            $user,
            request(),
            newValues: ['deleted_at' => now()->toDateTimeString()],
            event: 'admin.user.deleted',
        );

        return $this->success(null, 'User deleted successfully');
    }
}
