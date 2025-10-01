<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Traits\HasRoles; // أضف هذا الاستيراد

class PurchaseInvoiceVersion extends Model
{
    use HasFactory, HasRoles;

    protected $fillable = [
        'purchase_invoice_id',
        'invoice_number',
        'date',
        // 'supplier_name',
        'supplier_id',
        'total_amount',
        'amount_paid',
        'notes',
        'user_id',
        'items',
        'updated_by',
        'cashier_id',
        'version_type',
        'is_deleted'
    ];

    protected $casts = [
        'date' => 'date',
        'total_amount' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'items' => 'json', // عدل من array إلى json
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

        public function cashier()
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function invoice()
    {
        return $this->belongsTo(PurchaseInvoice::class, 'purchase_invoice_id');
    }
}
