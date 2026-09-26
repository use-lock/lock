<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->string('kind', 32)->default('realm');
            $table->foreignUuid('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->dropUnique(['realm_id', 'name']);
            $table->unique(['realm_id', 'tenant_id', 'name']);
        });

        Schema::create('tenant_member_role', function (Blueprint $table) {
            $table->foreignUuid('tenant_member_id')->constrained('tenant_members')->cascadeOnDelete();
            $table->foreignUuid('role_id')->constrained('roles')->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['tenant_member_id', 'role_id']);
            $table->index('role_id');
        });

        Schema::create('role_resource', function (Blueprint $table) {
            $table->foreignUuid('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignUuid('resource_id')->constrained('resources')->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['role_id', 'resource_id']);
            $table->index('resource_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_resource');
        Schema::dropIfExists('tenant_member_role');

        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique(['realm_id', 'tenant_id', 'name']);
            $table->dropConstrainedForeignId('tenant_id');
            $table->dropColumn('kind');
            $table->unique(['realm_id', 'name']);
        });
    }
};
