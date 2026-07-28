<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();

            // Subtasks. Deleting a parent removes its subtasks.
            $table->foreignId('parent_task_id')->nullable()->constrained('tasks')->cascadeOnDelete();

            $table->string('subject');
            $table->text('description')->nullable();
            $table->string('status')->default('pending');
            $table->string('priority')->default('medium');

            $table->boolean('is_public')->default(false);
            $table->boolean('is_billable')->default(false);
            $table->decimal('hourly_rate', 10, 2)->nullable();
            $table->decimal('estimated_hours', 8, 2)->nullable();

            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->timestamp('completed_at')->nullable();

            // Archive is distinct from soft delete: archived tasks are hidden
            // from default views but remain fully readable and restorable.
            $table->timestamp('archived_at')->nullable();

            // "Related Module" / "Related Record" — a morph-map alias, never a
            // class name. See App\Support\WorkspaceRecords.
            $table->string('related_type', 60)->nullable();
            $table->unsignedBigInteger('related_id')->nullable();

            // Recurrence. A template task carries the rule; generated instances
            // point back at it via recurrence_parent_id.
            $table->string('repeat_frequency', 20)->nullable();
            $table->unsignedSmallInteger('repeat_interval')->default(1);
            $table->date('repeat_until')->nullable();
            $table->foreignId('recurrence_parent_id')->nullable()->constrained('tasks')->nullOnDelete();
            $table->timestamp('last_recurred_at')->nullable();

            // Kanban ordering within a status column.
            $table->unsignedInteger('position')->default(0);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['owner_id', 'status', 'position']);
            $table->index(['owner_id', 'due_date']);
            $table->index(['owner_id', 'archived_at']);
            $table->index(['related_type', 'related_id']);
            $table->index('parent_task_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
