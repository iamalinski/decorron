<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Кошче за CRM записите.
 *
 * Добавя само колона deleted_at — съществуващите редове остават активни
 * (deleted_at = null) и нищо в данните не се променя.
 */
return new class extends Migration
{
    /** @var array<int, string> */
    private array $tables = ['customers', 'crm_opportunities', 'quotes', 'orders', 'crm_tasks'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasColumn($table, 'deleted_at')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->softDeletes()->index();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasColumn($table, 'deleted_at')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropSoftDeletes();
            });
        }
    }
};
