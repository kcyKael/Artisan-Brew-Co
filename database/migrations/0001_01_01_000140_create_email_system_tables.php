<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_settings', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->string('key', 80)->primary();
            $table->text('value')->nullable();
            $table->integer('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('email_deliveries', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->string('message_key', 160)->unique();
            $table->string('idempotency_key', 36)->unique();
            $table->string('purpose', 50)->index();
            $table->string('queue_name', 40)->default('emails');
            $table->string('recipient_email', 191)->index();
            $table->string('recipient_name', 120)->nullable();
            $table->string('subject', 200);
            $table->string('template_name', 80);
            $table->string('template_version', 20)->default('v1');
            $table->string('related_type', 80)->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->integer('initiated_by')->nullable();
            $table->boolean('is_sensitive')->default(false);
            $table->longText('payload_encrypted')->nullable();
            $table->string('status', 30)->default('queued')->index();
            $table->string('provider_message_id', 191)->nullable()->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedSmallInteger('last_http_status')->nullable();
            $table->string('last_error_code', 80)->nullable();
            $table->string('last_error', 500)->nullable();
            $table->dateTime('last_event_at')->nullable();
            $table->dateTime('dispatched_at')->nullable();
            $table->dateTime('accepted_at')->nullable();
            $table->dateTime('delivered_at')->nullable();
            $table->dateTime('failed_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('payload_pruned_at')->nullable();
            $table->timestamps();

            $table->index(['related_type', 'related_id'], 'email_deliveries_related_idx');
            $table->foreign('initiated_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('email_webhook_events', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->string('event_key', 64)->unique();
            $table->string('scope', 20)->index();
            $table->string('event_type', 50)->index();
            $table->string('provider_message_id', 191)->nullable()->index();
            $table->unsignedBigInteger('provider_campaign_id')->nullable()->index();
            $table->string('email', 191)->nullable()->index();
            $table->dateTime('occurred_at')->nullable();
            $table->unsignedBigInteger('email_delivery_id')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->longText('payload_json');
            $table->dateTime('processed_at')->nullable();
            $table->timestamps();

            $table->foreign('email_delivery_id')->references('id')->on('email_deliveries')->nullOnDelete();
        });

        Schema::create('staff_invitations', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->integer('user_id');
            $table->string('email', 191)->index();
            $table->string('token_hash', 64)->unique();
            $table->string('status', 20)->default('pending')->index();
            $table->integer('invited_by')->nullable();
            $table->dateTime('expires_at');
            $table->dateTime('accepted_at')->nullable();
            $table->dateTime('revoked_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('invited_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('daily_sales_reports', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->string('report_key', 120)->unique();
            $table->date('business_date')->index();
            $table->dateTime('period_start_utc');
            $table->dateTime('period_end_utc');
            $table->longText('summary_json');
            $table->dateTime('sent_at')->nullable();
            $table->timestamps();
        });

        Schema::create('contact_enquiries', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->string('reference_code', 30)->unique();
            $table->string('name', 120);
            $table->string('email', 191)->index();
            $table->string('subject', 160);
            $table->text('message');
            $table->string('status', 20)->default('received')->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('admin_notes')->nullable();
            $table->integer('handled_by')->nullable();
            $table->timestamps();

            $table->foreign('handled_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('marketing_subscribers', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->string('email', 191)->unique();
            $table->string('name', 120)->nullable();
            $table->integer('customer_id')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->string('consent_source', 60);
            $table->string('consent_notice_version', 40);
            $table->dateTime('consented_at')->nullable();
            $table->dateTime('confirmed_at')->nullable();
            $table->dateTime('unsubscribed_at')->nullable();
            $table->dateTime('suppressed_at')->nullable();
            $table->string('suppression_reason', 120)->nullable();
            $table->string('confirm_token_hash', 64)->nullable()->unique();
            $table->dateTime('confirm_expires_at')->nullable();
            $table->string('unsubscribe_token_hash', 64)->nullable()->unique();
            $table->string('provider_contact_id', 64)->nullable();
            $table->unsignedInteger('provider_list_id')->nullable();
            $table->boolean('provider_blacklisted')->default(false);
            $table->timestamps();

            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
        });

        Schema::create('marketing_campaigns', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->string('idempotency_key', 64)->unique();
            $table->string('name', 160);
            $table->string('subject', 200);
            $table->string('preview_text', 200)->nullable();
            $table->longText('body_html');
            $table->string('status', 24)->default('draft')->index();
            $table->unsignedInteger('audience_count')->default(0);
            $table->unsignedBigInteger('provider_campaign_id')->nullable()->index();
            $table->string('provider_status', 40)->nullable();
            $table->longText('stats_json')->nullable();
            $table->integer('created_by')->nullable();
            $table->integer('sent_by')->nullable();
            $table->dateTime('last_tested_at')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('sent_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_campaigns');
        Schema::dropIfExists('marketing_subscribers');
        Schema::dropIfExists('contact_enquiries');
        Schema::dropIfExists('daily_sales_reports');
        Schema::dropIfExists('staff_invitations');
        Schema::dropIfExists('email_webhook_events');
        Schema::dropIfExists('email_deliveries');
        Schema::dropIfExists('email_settings');
    }
};
