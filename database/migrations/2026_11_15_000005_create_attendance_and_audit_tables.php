<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->foreignUuid('device_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('device_event_id');
            $table->timestamp('occurred_at')->nullable()->index();
            $table->unsignedBigInteger('device_uptime_s')->nullable();
            $table->string('method', 16);
            $table->decimal('similarity', 5, 4)->nullable();
            $table->json('raw');
            $table->timestamp('received_at');
            $table->unique(['device_id', 'device_event_id']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('device_id')->nullable()->index();
            $table->string('action', 64)->index();
            $table->string('subject_type')->nullable();
            $table->string('subject_id', 64)->nullable();
            $table->json('meta')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('attendance_events');
    }
};
