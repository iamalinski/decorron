<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Хронология на действията по клиент и възможност.
     *
     * is_automatic отличава събитията, които системата записва сама (и за
     * които има достоверни данни), от ръчно добавените от потребителя.
     */
    public function up(): void
    {
        Schema::create('crm_activity_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->cascadeOnDelete();
            $table->foreignId('opportunity_id')->nullable()->constrained('crm_opportunities')->cascadeOnDelete();

            $table->string('type', 40);
            $table->string('description', 500);
            $table->boolean('is_automatic')->default(true);
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['opportunity_id', 'created_at']);
            $table->index(['customer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_activity_logs');
    }
};
