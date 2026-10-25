<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Данни на бизнеса за клиентските документи (оферти, поръчки).
     *
     * Все още няма данъчни ставки тук — те остават само в config/pricing.php.
     */
    public function up(): void
    {
        Schema::table('user_settings', function (Blueprint $table): void {
            $table->string('company_name', 160)->nullable()->after('default_unit_id');
            $table->string('company_phone', 40)->nullable()->after('company_name');
            $table->string('company_email', 160)->nullable()->after('company_phone');
            $table->string('company_address', 255)->nullable()->after('company_email');
            $table->string('company_extra', 255)->nullable()->after('company_address');
            $table->text('default_quote_terms')->nullable()->after('company_extra');
            $table->unsignedSmallInteger('default_quote_validity_days')->default(14)->after('default_quote_terms');
        });
    }

    public function down(): void
    {
        Schema::table('user_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'company_name',
                'company_phone',
                'company_email',
                'company_address',
                'company_extra',
                'default_quote_terms',
                'default_quote_validity_days',
            ]);
        });
    }
};
