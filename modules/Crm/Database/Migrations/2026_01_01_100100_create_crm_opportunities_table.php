<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Възможност = конкретна потенциална продажба (едно събитие).
     *
     * Етапът (stage) и окончателният статус (status) са разделени нарочно:
     * етапът описва докъде е стигнал разговорът, а статусът — дали случаят е
     * още отворен, спечелен или загубен.
     */
    public function up(): void
    {
        Schema::create('crm_opportunities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Клиент с възможности не може да бъде изтрит — само архивиран.
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();

            $table->string('title', 160);
            $table->string('event_type', 60)->nullable();
            $table->date('event_date')->nullable();
            $table->string('event_location', 255)->nullable();
            $table->decimal('expected_budget', 14, 2)->nullable();
            $table->string('source', 30)->nullable();

            $table->string('stage', 30)->default('new');
            $table->string('status', 20)->default('open');
            $table->string('lost_reason', 60)->nullable();
            $table->string('lost_reason_note', 255)->nullable();

            $table->string('next_action', 255)->nullable();
            $table->date('next_action_due')->nullable();

            $table->text('notes')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status', 'stage']);
            $table->index(['user_id', 'event_date']);
            $table->index(['user_id', 'customer_id']);
            $table->index(['user_id', 'next_action_due']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_opportunities');
    }
};
