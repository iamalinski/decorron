<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Потвърдена работа за изпълнение.
     *
     * Една оферта може да породи най-много една поръчка — уникалният индекс
     * върху (user_id, quote_id) пази от дублиране при повторно потвърждение.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('crm_opportunity_id')->nullable()->constrained('crm_opportunities')->nullOnDelete();
            $table->foreignId('quote_id')->nullable()->constrained('quotes')->nullOnDelete();
            $table->unsignedInteger('quote_version')->nullable();

            $table->string('order_number', 32);
            $table->string('status', 30)->default('pending');

            $table->string('customer_name_snapshot', 160);
            $table->string('customer_phone_snapshot', 40)->nullable();
            $table->string('customer_email_snapshot', 160)->nullable();

            $table->date('event_date')->nullable();
            $table->string('event_location', 255)->nullable();

            $table->decimal('agreed_total', 14, 2)->default(0);
            $table->decimal('deposit_amount', 14, 2)->nullable();
            $table->char('currency', 3)->default('EUR');

            $table->text('execution_notes')->nullable();
            $table->string('deadline_note', 255)->nullable();

            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'order_number']);
            $table->unique(['user_id', 'quote_id']);
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'event_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
