<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sales_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->date('date');
            $table->decimal('total_amount', 10, 2);
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->string('payment_method')->default('cash');
            $table->string('status')->default('paid');
            $table->text('notes')->nullable();
            $table->string('phone')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('cashier_name')->nullable(); 
            $table->string('user_name')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users');
            $table->index('date');
            $table->foreignId('cashier_id')->constrained('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_invoices');
    }
};
