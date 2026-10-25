<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Клиенти в CRM. Клиентите не се изтриват, когато има свързани записи —
     * вместо това се архивират, за да не се къса историята.
     */
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('type', 20)->default('individual');
            $table->string('name', 160);
            $table->string('contact_person', 160)->nullable();

            $table->string('phone', 40)->nullable();
            // Само цифрите от телефона — за надеждно откриване на дубликати.
            $table->string('phone_normalized', 20)->nullable();
            $table->string('email', 160)->nullable();

            $table->string('city', 120)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('source', 30)->nullable();
            $table->text('notes')->nullable();

            $table->boolean('is_archived')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'name']);
            $table->index(['user_id', 'is_archived']);
            $table->index(['user_id', 'phone_normalized']);
            $table->index(['user_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
