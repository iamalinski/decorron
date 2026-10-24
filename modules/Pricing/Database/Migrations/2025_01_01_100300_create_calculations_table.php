<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Записана калкулация. Всички финансови стойности и данъчни ставки се
     * съхраняват като snapshot, така че по-късни промени в каталога, в
     * настройките или в конфигурацията на данъците да не влияят на историята.
     */
    public function up(): void
    {
        Schema::create('calculations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('calculation_number', 32);

            $table->string('service_name', 160);
            $table->string('client_name', 160)->nullable();
            $table->text('notes')->nullable();
            $table->date('calculated_at');

            $table->decimal('material_cost_total', 14, 2);
            $table->decimal('labor_cost', 14, 2);
            $table->decimal('other_costs', 14, 2);
            $table->decimal('total_cost', 14, 2);

            $table->decimal('target_net_margin', 9, 6);
            $table->decimal('corporate_tax_rate_snapshot', 9, 6);
            $table->decimal('dividend_tax_rate_snapshot', 9, 6);
            $table->decimal('vat_rate_snapshot', 9, 6);

            $table->decimal('profit_before_tax', 14, 2);
            $table->decimal('corporate_tax_amount', 14, 2);
            $table->decimal('profit_after_corporate_tax', 14, 2);
            $table->decimal('dividend_tax_amount', 14, 2);
            $table->decimal('net_profit', 14, 2);
            $table->decimal('final_price', 14, 2);
            $table->decimal('actual_net_margin', 9, 6);

            $table->boolean('price_was_rounded')->default(false);
            $table->char('currency', 3)->default('EUR');
            $table->timestamps();

            $table->unique(['user_id', 'calculation_number']);
            $table->index(['user_id', 'calculated_at']);
            $table->index(['user_id', 'service_name']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calculations');
    }
};
