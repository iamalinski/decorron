<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('materials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('sku', 60)->nullable();
            $table->string('category', 80)->nullable();

            // Мерната единица не може да бъде изтрита, докато се използва
            // от материал — вместо това тя се архивира.
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();

            $table->decimal('default_unit_price', 18, 6)->default(0);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'sku']);
            $table->index(['user_id', 'is_active']);
            $table->index(['user_id', 'category']);
            $table->index(['user_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('materials');
    }
};
