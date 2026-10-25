<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ред в офертата — клиентско описание и договорена цена.
     *
     * calculation_id е само за проследимост: описанието и цената се копират
     * в реда и не се променят, ако калкулацията бъде изтрита.
     */
    public function up(): void
    {
        Schema::create('quote_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quote_id')->constrained('quotes')->cascadeOnDelete();
            $table->foreignId('calculation_id')->nullable()->constrained('calculations')->nullOnDelete();

            $table->string('description', 255);
            $table->text('details')->nullable();
            $table->decimal('quantity', 18, 6)->default(1);
            $table->decimal('unit_price', 14, 2);
            $table->decimal('line_total', 14, 2);
            $table->unsignedInteger('position')->default(0);

            $table->timestamps();

            $table->index(['quote_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_items');
    }
};
