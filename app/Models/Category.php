<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Traits\HasRoles; // أضف هذا الاستيراد

class Category extends Model
{
    use HasFactory, HasRoles;
    protected $fillable = ['name', 'description', 'color'];

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
