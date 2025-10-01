<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Traits\HasRoles; // أضف هذا الاستيراد

class SalesInvoiceItem extends Model
{
    use HasFactory, HasRoles;

    protected $fillable = [
        'sales_invoice_id',
        'product_id',
        'quantity',
        'unit_price',
        'total_price',

    ];

    public function invoice()
    {
        return $this->belongsTo(SalesInvoice::class, 'sales_invoice_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
