<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin|manager|user')->only(['index', 'show', 'trashed', 'restore', 'forceDelete']);
        // $this->middleware('role:user')->only(['show']);
    }

    public function index(): JsonResponse
    {
        $users = User::with('roles', 'permissions')->get()->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role_id' => $user->role_id,
                'roles' => $user->roles->pluck('name')->toArray(),
                'permissions' => $user->getAllPermissions()->pluck('name')->toArray(),
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ];
        });

        return response()->json(['users' => $users], 200);
    }

    // الحصول على المستخدمين المحذوفين
    public function trashed(): JsonResponse
    {
        $users = User::onlyTrashed()->with('roles', 'permissions')->get()->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role_id' => $user->role_id,
                'roles' => $user->roles->pluck('name')->toArray(),
                'permissions' => $user->getAllPermissions()->pluck('name')->toArray(),
                'deleted_at' => $user->deleted_at,
                'created_at' => $user->created_at,
            ];
        });

        return response()->json(['users' => $users], 200);
    }

    // استعادة مستخدم محذوف
    public function restore($id): JsonResponse
    {
        $user = User::onlyTrashed()->findOrFail($id);
        $user->restore();

        return response()->json([
            'message' => 'تم استعادة المستخدم بنجاح',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ]
        ], 200);
    }

    // حذف نهائي لمستخدم
    public function forceDelete($id): JsonResponse
    {
        $user = User::onlyTrashed()->findOrFail($id);
        $user->forceDelete();

        return response()->json(['message' => 'تم الحذف النهائي للمستخدم بنجاح'], 200);
    }

    public function show($id): JsonResponse
    {
        $user = User::with('roles', 'permissions')->findOrFail($id);
        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role_id' => $user->role_id,
                'roles' => $user->roles->pluck('name')->toArray(),
                'permissions' => $user->getAllPermissions()->pluck('name')->toArray(),
            ]
        ], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'role_id' => 'nullable|exists:roles,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // التحقق إذا كان البريد الإلكتروني محذوف سابقاً
        $trashedUser = User::onlyTrashed()->where('email', $request->email)->first();
        if ($trashedUser) {
            return response()->json([
                'message' => 'لا يمكن إنشاء حساب بهذا البريد الإلكتروني لأنه محذوف سابقاً',
                'errors' => ['email' => ['البريد الإلكتروني محذوف ولا يمكن إعادة استخدامه']]
            ], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'role_id' => $request->role_id,
            'password' => Hash::make($request->password),
        ]);

        if ($request->role_id) {
            $role = Role::find($request->role_id);
            if ($role) {
                $user->assignRole($role->name);
            }
        }

        return response()->json([
            'message' => 'تم إنشاء المستخدم بنجاح',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role_id' => $user->role_id,
                'roles' => $user->roles->pluck('name')->toArray(),
                'permissions' => $user->getAllPermissions()->pluck('name')->toArray(),
            ]
        ], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:8',
            'role_id' => 'nullable|exists:roles,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
            'role_id' => $request->role_id,
            'password' => $request->password ? Hash::make($request->password) : $user->password,
        ]);

        if ($request->role_id) {
            $role = Role::find($request->role_id);
            if ($role) {
                $user->syncRoles([$role->name]);
            } else {
                $user->syncRoles([]);
            }
        } else {
            $user->syncRoles([]);
        }

        return response()->json([
            'message' => 'تم تعديل المستخدم بنجاح',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role_id' => $user->role_id,
                'roles' => $user->roles->pluck('name')->toArray(),
                'permissions' => $user->getAllPermissions()->pluck('name')->toArray(),
            ]
        ], 200);
    }

    public function destroy(User $user): JsonResponse
    {
        // منع حذف المستخدم الحالي
        if (auth()->id() === $user->id) {
            return response()->json(['message' => 'لا يمكنك حذف حسابك الخاص'], 422);
        }

        $user->delete();
        return response()->json(['message' => 'تم حذف المستخدم بنجاح'], 200);
    }

    public function assignRole(Request $request, User $user): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'role_id' => 'required|exists:roles,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $role = Role::find($request->role_id);
        if (!$role) {
            return response()->json(['message' => 'الدور غير موجود'], 404);
        }

        if ($user->hasRole($role->name)) {
            return response()->json(['message' => 'الدور موجود بالفعل للمستخدم'], 422);
        }

        $user->update(['role_id' => $request->role_id]);
        $user->assignRole($role->name);

        return response()->json([
            'message' => 'تم تعيين الدور بنجاح',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role_id' => $user->role_id,
                'roles' => $user->roles->pluck('name')->toArray(),
                'permissions' => $user->getAllPermissions()->pluck('name')->toArray(),
            ]
        ], 200);
    }

    public function removeRole(User $user, $role_id): JsonResponse
    {
        $role = Role::find($role_id);
        if (!$role) {
            return response()->json(['message' => 'الدور غير موجود'], 404);
        }

        $user->removeRole($role->name);
        if ($user->role_id == $role_id) {
            $user->update(['role_id' => null]);
        }

        return response()->json([
            'message' => 'تم إزالة الدور بنجاح',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role_id' => $user->role_id,
                'roles' => $user->roles->pluck('name')->toArray(),
                'permissions' => $user->getAllPermissions()->pluck('name')->toArray(),
            ]
        ], 200);
    }

    public function assignPermission(Request $request, User $user): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'permission' => 'required|string|exists:permissions,name',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($user->hasPermissionTo($request->permission)) {
            return response()->json(['message' => 'الصلاحية موجودة بالفعل للمستخدم'], 422);
        }

        $user->givePermissionTo($request->permission);
        $user->load(['roles', 'permissions']);

        return response()->json([
            'message' => 'تم تعيين الصلاحية بنجاح',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role_id' => $user->role_id,
                'roles' => $user->roles->pluck('name')->toArray(),
                'permissions' => $user->getAllPermissions()->pluck('name')->toArray(),
            ]
        ], 200);
    }

    public function removePermission(User $user, $permission): JsonResponse
    {
        $user->revokePermissionTo($permission);
        $user->load(['roles', 'permissions']);

        return response()->json([
            'message' => 'تم إزالة الصلاحية بنجاح',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role_id' => $user->role_id,
                'roles' => $user->roles->pluck('name')->toArray(),
                'permissions' => $user->getAllPermissions()->pluck('name')->toArray(),
            ]
        ], 200);
    }
}
