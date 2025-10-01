<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:manage-All')->only(['index']);
    }

    public function index(): JsonResponse
    {
        $permissions = Permission::all()->pluck('name')->toArray();
        return response()->json(['permissions' => $permissions], 200);
    }

    public function show($id): JsonResponse
    {
        $permission = Permission::findOrFail($id);
        return response()->json(['permission' => $permission], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:permissions,name',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $permission = Permission::create(['name' => $request->name]);

        return response()->json([
            'message' => 'تم إنشاء الصلاحية بنجاح',
            'permission' => $permission,
        ], 201);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $permission = Permission::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:permissions,name,' . $id,
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $permission->update(['name' => $request->name]);

        return response()->json([
            'message' => 'تم تحديث الصلاحية بنجاح',
            'permission' => $permission,
        ], 200);
    }

    public function destroy($id): JsonResponse
    {
        $permission = Permission::findOrFail($id);
        $permission->delete();

        return response()->json(['message' => 'تم حذف الصلاحية بنجاح'], 200);
    }
}
