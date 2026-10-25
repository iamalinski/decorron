<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Задача или напомняне вътре в приложението.
     *
     * Връзките са по избор — задачата може да е към клиент, запитване,
     * оферта или поръчка. Изтриването на свързан запис не трие задачата.
     */
    public function up(): void
    {
        Schema::create('crm_tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('crm_opportunity_id')->nullable()->constrained('crm_opportunities')->nullOnDelete();
            $table->foreignId('quote_id')->nullable()->constrained('quotes')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();

            $table->string('title', 160);
            $table->text('description')->nullable();
            $table->string('type', 30)->default('other');
            $table->date('due_on')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'completed_at', 'due_on']);
            $table->index(['user_id', 'due_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_tasks');
    }
};
