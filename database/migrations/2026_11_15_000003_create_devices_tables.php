<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->string('name');
            $table->foreignId('site_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('hardware_id', 64)->nullable()->index();
            $table->string('mac', 17)->nullable();
            $table->string('model')->nullable();
            $table->string('firmware_version')->nullable();
            $table->string('variant')->nullable();
            $table->string('status', 16)->default('pending');
            $table->timestamp('last_seen_at')->nullable();
            $table->string('last_ip', 45)->nullable();
            $table->boolean('online')->default(false);
            $table->json('last_stats')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('device_pairing_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('device_id')->constrained()->cascadeOnDelete();
            $table->char('code_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('claimed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('device_id')->constrained()->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('device_commands', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('device_id')->constrained()->cascadeOnDelete();
            $table->string('name', 64);
            $table->json('args')->nullable();
            $table->string('status', 16)->default('queued')->index();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('result_detail')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
        });

        Schema::create('device_connection_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('device_id')->constrained()->cascadeOnDelete();
            $table->string('event', 32);
            $table->string('ip', 45)->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['device_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_connection_logs');
        Schema::dropIfExists('device_commands');
        Schema::dropIfExists('device_tokens');
        Schema::dropIfExists('device_pairing_tokens');
        Schema::dropIfExists('devices');
    }
};
