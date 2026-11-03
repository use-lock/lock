<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('realm_theme_assets', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('realm_id')->constrained()->cascadeOnDelete();
            $table->string('name', 128);
            $table->string('path');
            $table->string('mime_type', 64);
            $table->unsignedInteger('size');
            $table->char('sha256', 64);
            $table->timestamps();

            $table->unique(['realm_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('realm_theme_assets');
    }
};
