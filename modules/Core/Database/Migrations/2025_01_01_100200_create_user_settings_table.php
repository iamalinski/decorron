<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Потребителски настройки по подразбиране.
     *
     * ВАЖНО: Тук съзнателно НЯМА данъчни ставки. Те се дефинират единствено
     * в config/pricing.php и не могат да бъдат променяни от интерфейса.
     * Промяна на настройка тук влияе само на НОВИ калкулации.
     */
    public function up(): void
    {
        Schema::create('user_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('default_net_margin', 9, 6)->default('0.400000');
            $table->string('default_rounding_strategy', 20)->default('none');
            $table->foreignId('default_unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_settings');
    }
};
