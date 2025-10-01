<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Permission\Traits\HasRoles; // أضف هذا الاستيراد

class Customer extends Model
{
    use SoftDeletes, HasRoles;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'address',
        'address',
        'total_purchases',
        'purchases_count',
        'last_purchase_date',
        'notes',
    ];

    protected $casts = [
        'last_purchase_date' => 'date',
        'total_purchases' => 'decimal:2',
    ];

    public function invoices(): HasMany
    {
        return $this->hasMany(SalesInvoice::class, 'customer_id');
    }

    public function scopeActive($query)
    {
        return $query->where('purchases_count', '>', 0);
    }

    public function scopeTop($query, $limit = 10)
    {
        return $query->orderByDesc('total_purchases')->limit($limit);
    }
}
