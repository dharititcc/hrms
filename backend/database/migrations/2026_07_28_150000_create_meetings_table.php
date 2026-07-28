<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();

            $table->string('title');
            $table->text('agenda')->nullable();
            $table->text('description')->nullable();
            $table->string('type')->default('google_meet');
            $table->string('status')->default('scheduled');

            // Host runs the meeting; organizer scheduled it. Often the same
            // person, but a PA scheduling for a manager is the reason they split.
            $table->foreignId('host_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('organizer_id')->nullable()->constrained('users')->nullOnDelete();

            // Stored UTC. The timezone is kept so the meeting can be displayed
            // and re-edited in the zone it was scheduled in.
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('timezone', 64)->default('UTC');

            $table->string('meeting_link')->nullable();
            $table->string('location')->nullable();

            $table->text('notes')->nullable();
            $table->string('recording_url')->nullable();
            $table->longText('transcript')->nullable();

            // Minutes before the start at which to notify attendees.
            $table->unsignedSmallInteger('reminder_minutes')->nullable();
            $table->timestamp('reminder_sent_at')->nullable();

            $table->string('repeat_frequency', 20)->nullable();
            $table->unsignedSmallInteger('repeat_interval')->default(1);
            $table->date('repeat_until')->nullable();
            $table->foreignId('recurrence_parent_id')->nullable()->constrained('meetings')->nullOnDelete();
            $table->timestamp('last_recurred_at')->nullable();

            // Points at the replacement when a meeting is moved, preserving the
            // original as history rather than overwriting it.
            $table->foreignId('rescheduled_to_id')->nullable()->constrained('meetings')->nullOnDelete();

            // Set once Google Calendar sync is wired up; null until then.
            $table->string('external_calendar_id')->nullable();
            $table->string('external_event_id')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['owner_id', 'starts_at']);
            $table->index(['owner_id', 'status']);
            $table->index('external_event_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meetings');
    }
};
