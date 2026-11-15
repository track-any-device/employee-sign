<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->string('name');
            // Unique per tenant. Enforced in validation as well, because a
            // NULL tenant_id does not take part in a unique index.
            $table->string('code', 64)->nullable();
            $table->string('status', 16)->default('active');
            $table->boolean('all_devices')->default(false);
            $table->uuid('merged_into_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('employee_device', function (Blueprint $table) {
            $table->foreignUuid('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('device_id')->constrained()->cascadeOnDelete();
            $table->primary(['employee_id', 'device_id']);
        });

        Schema::create('biometric_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('employee_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32);
            $table->string('model_version', 32);
            $table->longText('data'); // encrypted base64 of the binary template
            $table->float('quality')->nullable();
            $table->foreignUuid('source_device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->char('sha256', 64);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['employee_id', 'sha256']);
        });

        Schema::create('sync_versions', function (Blueprint $table) {
            // The auto-increment id is the cursor: strictly increasing.
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->uuid('employee_id')->index();
            $table->string('op', 16); // upsert | delete | merge (hint; state is resolved on read)
            $table->uuid('merged_into')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('biometric_consents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->foreignUuid('device_id')->nullable()->constrained()->nullOnDelete();
            $table->string('given_by');
            $table->timestamp('given_at');
            $table->json('employee_ids');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biometric_consents');
        Schema::dropIfExists('sync_versions');
        Schema::dropIfExists('biometric_templates');
        Schema::dropIfExists('employee_device');
        Schema::dropIfExists('employees');
    }
};
