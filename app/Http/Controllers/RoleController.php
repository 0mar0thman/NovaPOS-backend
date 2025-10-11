<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Http\JsonResponse;

class RoleController extends Controller
{
    public function __construct()
    {
        // $this->middleware('auth:sanctum');
        // $this->middleware('role:admin|manager')->only(['index']);
    }

    public function index(): JsonResponse
    {
        $roles = Role::with('permissions')->get()->map(function ($role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name')->toArray(),
            ];
        });
        return response()->json(['roles' => $roles], 200);
    }

    public function show($id): JsonResponse
    {
        $role = Role::with('permissions')->findOrFail($id);
        return response()->json([
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name')->toArray(),
            ]
        ], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:roles,name',
            'permissions' => 'array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $role = Role::create([
            'name' => $request->name,
            'guard_name' => 'web',
        ]);

        if ($request->permissions) {
            $role->syncPermissions($request->permissions);
        }

        return response()->json([
            'message' => 'تم إنشاء الدور بنجاح',
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name')->toArray(),
            ],
        ], 201);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $role = Role::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:roles,name,' . $id,
            'permissions' => 'array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $role->update(['name' => $request->name]);
        if ($request->permissions) {
            $role->syncPermissions($request->permissions);
        }

        return response()->json([
            'message' => 'تم تحديث الدور بنجاح',
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name')->toArray(),
            ],
        ], 200);
    }

    public function destroy($id): JsonResponse
    {
        $role = Role::findOrFail($id);

        if ($role->users()->count() > 0) {
            return response()->json(['message' => 'لا يمكن حذف الدور لأنه مرتبط بمستخدمين'], 400);
        }

        $role->delete();
        return response()->json(['message' => 'تم حذف الدور بنجاح'], 200);
    }

    public function assignPermissionToRole(Request $request, $roleId): JsonResponse
    {
        $role = Role::findOrFail($roleId);

        $validator = Validator::make($request->all(), [
            'permission' => 'required|string|exists:permissions,name',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($role->hasPermissionTo($request->permission)) {
            return response()->json(['message' => 'الصلاحية موجودة بالفعل للدور'], 422);
        }

        $role->givePermissionTo($request->permission);

        return response()->json([
            'message' => 'تم تعيين الصلاحية للدور بنجاح',
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name')->toArray(),
            ]
        ], 200);
    }

    public function removePermissionFromRole($roleId, $permission): JsonResponse
    {
        $role = Role::findOrFail($roleId);
        $role->revokePermissionTo($permission);

        return response()->json([
            'message' => 'تم إزالة الصلاحية من الدور بنجاح',
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name')->toArray(),
            ]
        ], 200);
    }
}
