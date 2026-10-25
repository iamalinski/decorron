<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Връзка между калкулация и CRM.
     *
     * Колоните са nullable, за да останат четими всички калкулации, записани
     * преди CRM модулът да съществува. client_name продължава да е snapshot на
     * името към момента на записване — то не се променя при промяна на клиента.
     */
    public function up(): void
    {
        Schema::table('calculations', function (Blueprint $table): void {
            $table->foreignId('customer_id')->nullable()->after('client_name')->constrained('customers')->nullOnDelete();
            $table->foreignId('crm_opportunity_id')->nullable()->after('customer_id')->constrained('crm_opportunities')->nullOnDelete();

            $table->index(['user_id', 'customer_id']);
            $table->index(['user_id', 'crm_opportunity_id']);
        });
    }

    public function down(): void
    {
        Schema::table('calculations', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'crm_opportunity_id']);
            $table->dropIndex(['user_id', 'customer_id']);
            $table->dropConstrainedForeignId('crm_opportunity_id');
            $table->dropConstrainedForeignId('customer_id');
        });
    }
};
