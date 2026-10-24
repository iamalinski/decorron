<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Материален ред в калкулация.
     *
     * Връзките към каталога са само информативни (nullOnDelete). Името,
     * кодът и мерната единица се пазят като текстов snapshot, така че редът
     * остава четим дори материалът или мерната единица да изчезнат.
     */
    public function up(): void
    {
        Schema::create('calculation_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('calculation_id')->constrained('calculations')->cascadeOnDelete();
            $table->foreignId('material_id')->nullable()->constrained('materials')->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();

            $table->string('material_name_snapshot', 160);
            $table->string('sku_snapshot', 60)->nullable();
            $table->string('unit_name_snapshot', 80);
            $table->string('unit_symbol_snapshot', 20);

            $table->decimal('quantity', 18, 6);
            $table->string('input_mode', 10);
            $table->decimal('entered_total_cost', 14, 2)->nullable();
            $table->decimal('unit_price', 18, 6);
            $table->decimal('line_total', 14, 2);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['calculation_id', 'position']);
            $table->index('material_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calculation_items');
    }
};
