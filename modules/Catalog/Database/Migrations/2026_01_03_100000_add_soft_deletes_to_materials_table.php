<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Кошче за каталога.
     *
     * Материал, който още се използва в калкулация, не се изтрива физически —
     * редовете на калкулациите пазят връзка към него. Вместо това той отива в
     * Кошчето. Неизползваните материали се изтриват окончателно от контролера.
     */
    public function up(): void
    {
        if (Schema::hasColumn('materials', 'deleted_at')) {
            return;
        }

        Schema::table('materials', function (Blueprint $table): void {
            $table->softDeletes()->index();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('materials', 'deleted_at')) {
            return;
        }

        Schema::table('materials', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });
    }
};
