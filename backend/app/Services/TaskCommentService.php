<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use App\Notifications\TaskMentionNotification;
use App\Support\WorkspaceUsers;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class TaskCommentService
{
    /**
     * Mention token embedded in the comment body, e.g. "@[Ada](user:12)".
     * Keeping the id in the body means mentions and text can never drift apart.
     */
    private const MENTION_PATTERN = '/\(user:(\d+)\)/';

    public function list(Task $task): Collection
    {
        return $task->comments()
            ->with(['author', 'mentions', 'replies.author', 'replies.mentions'])
            ->get();
    }

    public function create(Task $task, User $author, array $attributes): TaskComment
    {
        $comment = DB::transaction(function () use ($task, $author, $attributes): TaskComment {
            $comment = TaskComment::create([
                'task_id' => $task->id,
                'user_id' => $author->id,
                'parent_id' => $attributes['parent_id'] ?? null,
                'body' => $attributes['body'],
            ]);

            $comment->mentions()->sync($this->resolveMentions($attributes['body'], $task));

            return $comment;
        });

        $this->notifyMentioned($comment, $task, $author);

        return $comment->load(['author', 'mentions']);
    }

    public function update(TaskComment $comment, array $attributes): TaskComment
    {
        return DB::transaction(function () use ($comment, $attributes): TaskComment {
            $comment->update(['body' => $attributes['body']]);
            // Re-resolve so removing a mention from the text removes the record.
            $comment->mentions()->sync($this->resolveMentions($attributes['body'], $comment->task));

            return $comment->refresh()->load(['author', 'mentions']);
        });
    }

    public function delete(TaskComment $comment): void
    {
        DB::transaction(fn () => $comment->delete());
    }

    /**
     * Extracts mentioned user ids from the body, keeping only users who belong
     * to the task's workspace. A client cannot mention someone it cannot see.
     *
     * @return list<int>
     */
    private function resolveMentions(string $body, Task $task): array
    {
        preg_match_all(self::MENTION_PATTERN, $body, $matches);

        if ($matches[1] === []) {
            return [];
        }

        $mentioned = array_unique(array_map('intval', $matches[1]));
        $allowed = WorkspaceUsers::idsFor($task->owner_id);

        return array_values(array_intersect($mentioned, $allowed));
    }

    private function notifyMentioned(TaskComment $comment, Task $task, User $author): void
    {
        // Never notify someone for mentioning themselves.
        $recipients = $comment->mentions->reject(fn (User $user) => $user->id === $author->id);

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new TaskMentionNotification($task, $comment, $author));
        }
    }
}
