<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $userColumn = function (Blueprint $table, string $name): void {
            match (config('device-auth.user_key_type', 'int')) {
                'uuid' => $table->uuid($name)->nullable()->index(),
                'ulid' => $table->ulid($name)->nullable()->index(),
                default => $table->unsignedBigInteger($name)->nullable()->index(),
            };
        };

        Schema::create(config('device-auth.tables.devices', 'device_auth_devices'), function (Blueprint $table) use ($userColumn) {
            $table->id();
            $table->string('name');
            $table->string('status', 20)->default('pending')->index();

            // The key (like an authorized_keys entry)
            $table->string('credential_id', 512)->unique();
            $table->text('public_key');
            $table->unsignedBigInteger('sign_count')->default(0);
            $table->string('aaguid', 36)->nullable();
            $table->boolean('backup_eligible')->default(false);
            $table->boolean('backed_up')->default(false);

            // The request
            $userColumn($table, 'requested_by');
            $table->text('request_note')->nullable();
            $table->string('request_ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('browser')->nullable();
            $table->string('os')->nullable();
            $table->string('os_family')->nullable();
            $table->string('device_type', 30)->nullable();
            $table->string('device_model')->nullable();
            $table->json('client_meta')->nullable();

            // The review
            $userColumn($table, 'reviewed_by');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();

            // Usage
            $table->string('last_ip', 45)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        Schema::create(config('device-auth.tables.logins', 'device_auth_logins'), function (Blueprint $table) use ($userColumn) {
            $table->id();
            $table->foreignId('device_id')
                ->constrained(config('device-auth.tables.devices', 'device_auth_devices'))
                ->cascadeOnDelete();
            $userColumn($table, 'user_id');
            $table->string('ip', 45)->nullable();
            $table->string('purpose', 20)->default('verify'); // verify | confirm
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('device-auth.tables.logins', 'device_auth_logins'));
        Schema::dropIfExists(config('device-auth.tables.devices', 'device_auth_devices'));
    }
};
