<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('realm_social_providers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('realm_id')->constrained()->cascadeOnDelete();
            $table->string('key', 64);
            $table->string('driver', 32);
            $table->boolean('enabled')->default(true);
            $table->text('config');
            $table->timestamps();
            $table->unique(['realm_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('realm_social_providers');
    }
};
