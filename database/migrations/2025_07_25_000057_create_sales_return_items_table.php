<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sales_return_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sales_return_id');
            $table->unsignedBigInteger('sales_invoice_item_id');
            $table->unsignedBigInteger('product_id');
            $table->integer('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total_price', 10, 2);
            $table->timestamps();

            $table->foreign('sales_return_id')
                ->references('id')
                ->on('sales_returns')
                ->onDelete('cascade');

            $table->foreign('sales_invoice_item_id')
                ->references('id')
                ->on('sales_invoice_items')
                ->onDelete('cascade');

            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->onDelete('cascade');

            $table->index('sales_return_id');
            $table->index('sales_invoice_item_id');
            $table->index('product_id');
        });

        // إضافة حقل returned_quantity إلى جدول sales_invoice_items
        Schema::table('sales_invoice_items', function (Blueprint $table) {
            $table->integer('returned_quantity')->default(0)->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_return_items');

        // إزالة الحقل عند التراجع
        Schema::table('sales_invoice_items', function (Blueprint $table) {
            $table->dropColumn('returned_quantity');
        });
    }
};
