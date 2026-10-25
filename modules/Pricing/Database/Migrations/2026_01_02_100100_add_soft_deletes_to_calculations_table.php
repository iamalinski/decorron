<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Кошче и за калкулациите — сгрешена калкулация се изтрива по същия начин
 * като записите в CRM и може да бъде върната.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('calculations', 'deleted_at')) {
            return;
        }

        Schema::table('calculations', function (Blueprint $table): void {
            $table->softDeletes()->index();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('calculations', 'deleted_at')) {
            return;
        }

        Schema::table('calculations', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });
    }
};
