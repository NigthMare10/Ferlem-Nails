<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('expense_number')->nullable()->unique();
            $table->uuid('checkout_token')->unique();
            $table->char('request_hash', 64);
            $table->foreignId('category_id')->constrained('expense_categories')->restrictOnDelete();
            $table->string('category_name_snapshot');
            $table->date('expense_date');
            $table->string('description', 500);
            $table->decimal('amount', 12, 2);
            $table->enum('payment_method', ['cash', 'card', 'transfer']);
            $table->string('vendor')->nullable();
            $table->foreignId('employee_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->enum('status', ['recorded', 'canceled'])->default('recorded');
            $table->text('notes')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_original_name')->nullable();
            $table->string('attachment_mime', 100)->nullable();
            $table->unsignedBigInteger('attachment_size')->nullable();
            $table->foreignId('attachment_uploaded_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('attachment_uploaded_at')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('canceled_at')->nullable();
            $table->foreignId('canceled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            $table->index(['status', 'expense_date']);
            $table->index(['category_id', 'expense_date']);
            $table->index(['payment_method', 'expense_date']);
            $table->index(['employee_id', 'expense_date']);
            $table->index(['recorded_by', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
