<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Замразено копие на офертата в момента на изпращане.
     *
     * Записва се само когато потребителят потвърди, че е изпратил офертата.
     * След това съдържанието тук не се променя никога — последващите промени
     * по офертата създават нова версия.
     */
    public function up(): void
    {
        Schema::create('quote_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quote_id')->constrained('quotes')->cascadeOnDelete();

            $table->unsignedInteger('version');
            $table->decimal('total', 14, 2);
            $table->json('payload');
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->unique(['quote_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_versions');
    }
};
