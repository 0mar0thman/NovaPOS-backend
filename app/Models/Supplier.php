<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Traits\HasRoles; // أضف هذا الاستيراد

class Supplier extends Model
{
    use HasFactory, HasRoles;
    protected $fillable = [
        'name',
        'phone',
        'notes',
        'created_by'
    ];

        public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function purchaseInvoices()
    {
        return $this->hasMany(PurchaseInvoice::class);
    }
}
