<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = Supplier::query();

            if ($request->has('search')) {
                $search = $request->input('search');
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%");
                });
            }

            $sortField = $request->input('sort_field', 'created_at');
            $sortDirection = $request->input('sort_direction', 'desc');
            $query->orderBy($sortField, $sortDirection);

            $perPage = $request->input('per_page', 15);
            $suppliers = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $suppliers,
                'message' => 'تم جلب بيانات الموردين بنجاح'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل في جلب بيانات الموردين',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'phone' => 'required|string|max:20|unique:suppliers,phone',
                'notes' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                    'message' => 'البيانات المدخلة غير صالحة'
                ], 422);
            }

            $supplier = Supplier::create([
                'name' => $request->name,
                'phone' => $request->phone,
                'notes' => $request->notes,
                // 'created_by' => auth()->id()
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $supplier,
                'message' => 'تم إنشاء المورد بنجاح'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'فشل في إنشاء المورد',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $supplier = Supplier::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $supplier,
                'message' => 'تم جلب بيانات المورد بنجاح'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'المورد غير موجود',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function update(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $supplier = Supplier::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'phone' => 'required|string|max:20|unique:suppliers,phone,'.$id,
                'notes' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                    'message' => 'البيانات المدخلة غير صالحة'
                ], 422);
            }

            $supplier->update([
                'name' => $request->name,
                'phone' => $request->phone,
                'notes' => $request->notes
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $supplier,
                'message' => 'تم تحديث بيانات المورد بنجاح'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'فشل في تحديث بيانات المورد',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $supplier = Supplier::findOrFail($id);

            if ($supplier->purchaseInvoices()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا يمكن حذف المورد لأنه مرتبط بفواتير مشتريات'
                ], 400);
            }

            $supplier->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تم حذف المورد بنجاح'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'فشل في حذف المورد',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
