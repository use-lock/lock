<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Runs before the users table so `users.realm_id` can be a plain foreign
     * key.
     */
    public function up(): void
    {
        Schema::create('realms', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            // use-lock/server keys every row it stores for the realm by the slug.
            $table->string('slug')->unique();
            // The host the realm is served from. The master realm is served
            // from APP_URL's host, so its row carries none.
            $table->string('domain', 253)->nullable()->unique();
            $table->string('domain_status', 20)->default('pending');
            $table->timestamp('domain_checked_at')->nullable();
            $table->string('domain_check_error', 500)->nullable();
            // Only what the realm moves away from the instance defaults, so a
            // setting nobody changed keeps following the default it came from.
            $table->json('settings')->default('{}');
            // Provisioned rather than configured, which is why it is a column of
            // its own instead of one of the settings above.
            $table->string('first_party_client_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('realms');
    }
};
