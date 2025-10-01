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
        'purchase_price', // سعر الشراء (مضاف)
        'sale_price',
        'stock',
        'min_stock', // حد التنبيه (مضاف)
        'description' // وصف المنتج (مضاف)
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
