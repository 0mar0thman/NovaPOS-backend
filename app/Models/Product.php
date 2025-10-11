<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Traits\HasRoles; // أضف هذا الاستيراد

class Product extends Model
{
    use HasFactory, HasRoles;

    protected $fillable = [
        'name',
        'barcode',
        'category_id',
        'purchase_price',
        'sale_price',
        'stock',
        'min_stock',
        'description',
        'standard'
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function purchaseItems()
    {
        return $this->hasMany(PurchaseInvoiceItem::class);
    }

    public function salesItems()
    {
        return $this->hasMany(SalesInvoiceItem::class);
    }
}
