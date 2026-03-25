<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per-type outreach configuration
        Schema::create('outreach_configs', function (Blueprint $table) {
            $table->id();
            $table->string('contact_type', 50)->unique();
            $table->boolean('auto_send')->default(false);
            $table->boolean('ai_generation_enabled')->default(true);
            $table->unsignedTinyInteger('max_steps')->default(4);
            $table->json('step_delays')->default('[0, 3, 7, 14]'); // days between steps
            $table->unsignedSmallInteger('daily_limit')->default(50);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Individual outreach emails (generated, reviewed, sent, tracked)
        Schema::create('outreach_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('influenceur_id')->constrained()->onDelete('cascade');
            $table->foreignId('template_id')->nullable()->constrained('email_templates')->nullOnDelete();
            $table->unsignedTinyInteger('step')->default(1);
            $table->string('subject', 500);
            $table->text('body_html');
            $table->text('body_text');
            $table->string('from_email');
            $table->string('from_name')->default('Williams');
            // Status flow: pending_generation → generated → pending_review → approved → queued → sending → sent → delivered → opened → clicked → replied → bounced → failed → unsubscribed
            $table->string('status', 30)->default('pending_generation');
            $table->boolean('ai_generated')->default(false);
            $table->string('ai_model', 50)->nullable();
            $table->unsignedInteger('ai_prompt_tokens')->default(0);
            $table->unsignedInteger('ai_completion_tokens')->default(0);
            // Scheduling
            $table->timestamp('send_after')->nullable();
            // Tracking timestamps
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->timestamp('bounced_at')->nullable();
            $table->string('bounce_type', 10)->nullable(); // hard, soft
            $table->string('bounce_reason')->nullable();
            // External IDs
            $table->string('external_id')->nullable(); // PMTA/MailWizz message ID
            $table->uuid('tracking_id')->unique();
            $table->uuid('unsubscribe_token')->unique();
            // Error
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['influenceur_id', 'step', 'status']);
            $table->index(['status', 'send_after']);
        });

        // Sequence state per contact
        Schema::create('outreach_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('influenceur_id')->unique()->constrained()->onDelete('cascade');
            $table->unsignedTinyInteger('current_step')->default(0);
            $table->string('status', 20)->default('active'); // active, paused, completed, stopped
            $table->string('stop_reason')->nullable(); // replied, bounced, unsubscribed, manual
            $table->timestamp('next_send_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'next_send_at']);
        });

        // Warm-up tracking per sending domain
        Schema::create('warmup_states', function (Blueprint $table) {
            $table->id();
            $table->string('from_email')->unique();
            $table->string('domain');
            $table->unsignedSmallInteger('day_count')->default(0);
            $table->unsignedSmallInteger('emails_sent_today')->default(0);
            $table->unsignedSmallInteger('current_daily_limit')->default(5);
            $table->date('started_at');
            $table->timestamp('last_sent_at')->nullable();
            $table->date('last_reset_date')->nullable(); // for daily counter reset
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warmup_states');
        Schema::dropIfExists('outreach_sequences');
        Schema::dropIfExists('outreach_emails');
        Schema::dropIfExists('outreach_configs');
    }
};
