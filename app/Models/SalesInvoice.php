<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Traits\HasRoles; // أضف هذا الاستيراد

class SalesInvoice extends Model
{
    use HasFactory, HasRoles;

    protected $fillable = [
        'invoice_number',
        'date',
        'customer_id',
        'customer_name',
        'phone',
        'total_amount',
        'paid_amount',
        'user_id',
        'cashier_id',
        'notes',
        'payment_method',
        'user_name',
        'cashier_name'
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'date' => 'date',
        'created_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'id');
    }

    public function items()
    {
        return $this->hasMany(SalesInvoiceItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function cashier()
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }
}
