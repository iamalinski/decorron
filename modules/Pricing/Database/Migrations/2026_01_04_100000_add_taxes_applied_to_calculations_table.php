<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Калкулация без данъци.
     *
     * Някои услуги се предлагат без да се заделя сума за корпоративен данък
     * и данък дивидент. Тогава ставките в snapshot-а са нули; колоната пази
     * причината за това, за да не се бърка с нулева ставка по закон.
     *
     * Съществуващите калкулации са с данъци — затова default true.
     */
    public function up(): void
    {
        if (Schema::hasColumn('calculations', 'taxes_applied')) {
            return;
        }

        Schema::table('calculations', function (Blueprint $table): void {
            $table->boolean('taxes_applied')->default(true)->after('price_was_rounded');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('calculations', 'taxes_applied')) {
            return;
        }

        Schema::table('calculations', function (Blueprint $table): void {
            $table->dropColumn('taxes_applied');
        });
    }
};
