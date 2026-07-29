<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Attendees split in two because they answer differently.
 *
 * Participants have workspace accounts, so they RSVP while signed in and can be
 * notified in-app. Guests are external email addresses with no account, so they
 * RSVP through a signed token link instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('rsvp', 20)->default('pending');
            $table->timestamp('responded_at')->nullable();
            $table->boolean('attended')->default(false);
            $table->timestamps();

            $table->unique(['meeting_id', 'user_id']);
            $table->index(['user_id', 'rsvp']);
        });

        Schema::create('meeting_guests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->string('email');
            $table->string('rsvp', 20)->default('pending');
            $table->timestamp('responded_at')->nullable();
            $table->boolean('attended')->default(false);
            // Unguessable token backing the public RSVP link.
            $table->string('invite_token', 64)->unique();
            $table->timestamps();

            $table->unique(['meeting_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_guests');
        Schema::dropIfExists('meeting_participants');
    }
};
