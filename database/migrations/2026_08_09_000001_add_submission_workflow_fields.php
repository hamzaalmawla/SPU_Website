<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dynamic_form_submissions', function (Blueprint $table): void {
            $table->string('reference_number', 40)->nullable()->unique()->after('id');
            $table->timestamp('read_at')->nullable()->index();
            $table->foreignId('read_by_user_id')->nullable()->after('read_at')->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to_user_id')->nullable()->after('read_by_user_id')->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->foreignId('assigned_by_user_id')->nullable()->after('assigned_at')->constrained('users')->nullOnDelete();
            $table->text('internal_notes')->nullable();
            $table->timestamp('status_changed_at')->nullable()->index();
            $table->string('email_delivery_status', 32)->default('unknown')->index();
            $table->timestamp('email_queued_at')->nullable();
            $table->timestamp('email_sent_at')->nullable();
            $table->timestamp('email_delivered_at')->nullable();
            $table->timestamp('email_failed_at')->nullable();
            $table->text('email_failure_reason')->nullable();
        });

        Schema::table('contact_messages', function (Blueprint $table): void {
            $table->string('reference_number', 40)->nullable()->unique()->after('id');
            $table->timestamp('read_at')->nullable()->index();
            $table->foreignId('read_by_user_id')->nullable()->after('read_at')->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to_user_id')->nullable()->after('read_by_user_id')->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->foreignId('assigned_by_user_id')->nullable()->after('assigned_at')->constrained('users')->nullOnDelete();
            $table->text('internal_notes')->nullable();
            $table->timestamp('status_changed_at')->nullable()->index();
            $table->string('email_delivery_status', 32)->default('not_applicable')->index();
            $table->timestamp('email_queued_at')->nullable();
            $table->timestamp('email_sent_at')->nullable();
            $table->timestamp('email_delivered_at')->nullable();
            $table->timestamp('email_failed_at')->nullable();
            $table->text('email_failure_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('dynamic_form_submissions', function (Blueprint $table): void {
            $table->dropForeign(['read_by_user_id']);
            $table->dropForeign(['assigned_to_user_id']);
            $table->dropForeign(['assigned_by_user_id']);
            $table->dropColumn([
                'reference_number', 'read_at', 'read_by_user_id', 'assigned_to_user_id', 'assigned_at',
                'assigned_by_user_id', 'internal_notes', 'status_changed_at', 'email_delivery_status',
                'email_queued_at', 'email_sent_at', 'email_delivered_at', 'email_failed_at', 'email_failure_reason',
            ]);
        });

        Schema::table('contact_messages', function (Blueprint $table): void {
            $table->dropForeign(['read_by_user_id']);
            $table->dropForeign(['assigned_to_user_id']);
            $table->dropForeign(['assigned_by_user_id']);
            $table->dropColumn([
                'reference_number', 'read_at', 'read_by_user_id', 'assigned_to_user_id', 'assigned_at',
                'assigned_by_user_id', 'internal_notes', 'status_changed_at', 'email_delivery_status',
                'email_queued_at', 'email_sent_at', 'email_delivered_at', 'email_failed_at', 'email_failure_reason',
            ]);
        });
    }
};
