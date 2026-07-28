<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Folds project_tasks into the richer tasks table and drops it.
 *
 * A project task becomes a task related to its project. Two mappings are lossy
 * and deliberate:
 *
 *  - status: todo -> pending, done -> completed
 *  - assignee: project_tasks pointed at a staff record, while task assignees are
 *    users. Staff who have not been invited have no account, so their assignment
 *    cannot be carried over and is dropped rather than silently reassigned.
 */
return new class extends Migration
{
    private const STATUS_MAP = [
        'todo' => 'pending',
        'in_progress' => 'in_progress',
        'done' => 'completed',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('project_tasks')) {
            return;
        }

        DB::table('project_tasks')->orderBy('id')->chunkById(200, function ($rows): void {
            foreach ($rows as $row) {
                $status = self::STATUS_MAP[$row->status] ?? 'pending';

                $taskId = DB::table('tasks')->insertGetId([
                    'owner_id' => $row->owner_id,
                    'subject' => $row->title,
                    'description' => $row->description,
                    'status' => $status,
                    'priority' => $row->priority,
                    'due_date' => $row->due_date,
                    'completed_at' => $status === 'completed' ? $row->updated_at : null,
                    'related_type' => 'project',
                    'related_id' => $row->project_id,
                    'position' => $row->position,
                    'repeat_interval' => 1,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);

                // Follow attachments and activity across to the new id.
                DB::table('attachments')
                    ->where('attachable_type', 'project_task')
                    ->where('attachable_id', $row->id)
                    ->update(['attachable_type' => 'task', 'attachable_id' => $taskId]);

                DB::table('audit_logs')
                    ->where('entity', 'project_task')
                    ->where('entity_id', $row->id)
                    ->update(['entity' => 'task', 'entity_id' => $taskId]);

                // Only staff with a login account can become an assignee.
                $userId = $row->staff_id === null
                    ? null
                    : DB::table('staff')->where('id', $row->staff_id)->value('user_id');

                if ($userId !== null) {
                    DB::table('task_assignees')->insert([
                        'task_id' => $taskId,
                        'user_id' => $userId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        });

        Schema::dropIfExists('project_tasks');
    }

    /**
     * Recreates the empty table so a rollback leaves a working schema. Rows are
     * not moved back — this migration is not data-reversible.
     */
    public function down(): void
    {
        Schema::create('project_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('todo');
            $table->string('priority')->default('medium');
            $table->date('due_date')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['project_id', 'status', 'position']);
            $table->index(['owner_id', 'status']);
        });
    }
};
