<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_scope', function (Blueprint $table) {
            $table->foreignUuid('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignUuid('resource_scope_id')->constrained('resource_scopes')->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['role_id', 'resource_scope_id']);
            $table->index('resource_scope_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_scope');
    }
};
