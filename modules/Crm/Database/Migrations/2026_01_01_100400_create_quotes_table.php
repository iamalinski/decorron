<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Оферта към клиента.
     *
     * Данните на клиента и данъчният режим се пазят като snapshot, за да
     * остане изпратената оферта четима точно както е била изпратена.
     * Вътрешната себестойност и маржът НИКОГА не се пазят тук — офертата е
     * клиентски документ.
     */
    public function up(): void
    {
        Schema::create('quotes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('crm_opportunity_id')->nullable()->constrained('crm_opportunities')->nullOnDelete();

            $table->string('quote_number', 32);
            $table->string('status', 20)->default('draft');
            $table->unsignedInteger('version')->default(1);

            $table->date('issued_at');
            $table->date('valid_until')->nullable();

            $table->string('customer_name_snapshot', 160);
            $table->string('customer_contact_snapshot', 160)->nullable();
            $table->string('customer_phone_snapshot', 40)->nullable();
            $table->string('customer_email_snapshot', 160)->nullable();
            $table->string('customer_address_snapshot', 255)->nullable();

            $table->date('event_date')->nullable();
            $table->string('event_location', 255)->nullable();

            $table->decimal('subtotal', 14, 2)->default(0);
            $table->string('discount_type', 10)->nullable();
            $table->decimal('discount_value', 14, 2)->nullable();
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->decimal('deposit_amount', 14, 2)->nullable();

            $table->decimal('vat_rate_snapshot', 9, 6)->default(0);
            $table->char('currency', 3)->default('EUR');

            $table->text('terms')->nullable();
            $table->text('internal_notes')->nullable();

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'quote_number']);
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'customer_id']);
            $table->index(['user_id', 'valid_until']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotes');
    }
};
