<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sales_returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_number')->unique();
            $table->date('date');
            $table->unsignedBigInteger('sales_invoice_id');
            $table->decimal('total_amount', 10, 2);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('user_id'); // المستخدم الذي قام بالاسترجاع
            $table->timestamps();

            $table->foreign('sales_invoice_id')
                ->references('id')
                ->on('sales_invoices')
                ->onDelete('cascade');

            $table->foreign('user_id')
                ->references('id')
                ->on('users');

            $table->index('date');
            $table->index('sales_invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_returns');
    }
};
