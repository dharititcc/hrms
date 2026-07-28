<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * audit_logs existed but was never written to. Now that it backs the activity
 * timeline it needs indexes for the two access patterns: one record's history,
 * and a workspace-wide feed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index(['owner_id', 'entity', 'entity_id'], 'audit_logs_record_index');
            $table->index(['owner_id', 'created_at'], 'audit_logs_feed_index');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('audit_logs_record_index');
            $table->dropIndex('audit_logs_feed_index');
        });
    }
};
