<?php

namespace App\Models;

use App\Enums\RepeatFrequency;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasTags;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'owner_id', 'parent_task_id', 'subject', 'description', 'status', 'priority',
    'is_public', 'is_billable', 'hourly_rate', 'estimated_hours',
    'start_date', 'due_date', 'completed_at', 'archived_at',
    'related_type', 'related_id',
    'repeat_frequency', 'repeat_interval', 'repeat_until', 'recurrence_parent_id', 'last_recurred_at',
    'position', 'created_by', 'updated_by',
])]
#[Hidden(['owner_id'])]
class Task extends Model
{
    use HasAttachments, HasTags, LogsActivity, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'priority' => TaskPriority::class,
            'repeat_frequency' => RepeatFrequency::class,
            'is_public' => 'boolean',
            'is_billable' => 'boolean',
            'hourly_rate' => 'decimal:2',
            'estimated_hours' => 'decimal:2',
            'start_date' => 'date',
            'due_date' => 'date',
            'repeat_until' => 'date',
            'completed_at' => 'datetime',
            'archived_at' => 'datetime',
            'last_recurred_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** The record this task hangs off, e.g. a project or a employee. */
    public function related(): MorphTo
    {
        return $this->morphTo('related');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_task_id');
    }

    public function subtasks(): HasMany
    {
        return $this->hasMany(self::class, 'parent_task_id');
    }

    /** The template this recurring instance was generated from. */
    public function recurrenceParent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'recurrence_parent_id');
    }

    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_assignees')->withTimestamps();
    }

    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_followers')->withTimestamps();
    }

    public function favoritedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_favorites')->withTimestamps();
    }

    public function checklistItems(): HasMany
    {
        return $this->hasMany(TaskChecklistItem::class)->orderBy('position')->orderBy('id');
    }

    /** Top-level comments only; replies hang off each comment. */
    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class)->whereNull('parent_id')->latest();
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TaskTimeEntry::class)->latest('started_at');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    public function scopeArchived(Builder $query): Builder
    {
        return $query->whereNotNull('archived_at');
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->whereNotNull('due_date')
            ->whereDate('due_date', '<', now()->toDateString())
            ->whereNotIn('status', [TaskStatus::Completed->value, TaskStatus::Cancelled->value]);
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /** True when a recurrence rule is set and has not yet expired. */
    public function repeats(): bool
    {
        if ($this->repeat_frequency === null) {
            return false;
        }

        return $this->repeat_until === null || $this->repeat_until->isFuture();
    }
}
