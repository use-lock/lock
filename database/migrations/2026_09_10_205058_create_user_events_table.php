<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('realm_id')->nullable()->index();
            $table->string('type')->index();
            $table->string('category', 32)->index();

            // No foreign key: a security trail has to outlive the user, the
            // client and the realm it is about.
            $table->foreignUuid('user_id')->nullable()->index();
            $table->string('client_id')->nullable()->index();
            $table->string('sid')->nullable()->index();
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->boolean('failure')->default(false);
            $table->json('context')->nullable();
            $table->timestamp('occurred_at');

            $table->index(['realm_id', 'occurred_at']);
        });
    }
};
