<?php

namespace App\Models;

use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['owner_id', 'name', 'client', 'description', 'status', 'start_date', 'end_date', 'budget'])]
#[Hidden(['owner_id'])]
class Project extends Model
{
    use HasAttachments, LogsActivity;

    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'budget' => 'decimal:2',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'project_staff', 'project_id', 'staff_id');
    }

    /** Tasks are related polymorphically, so a project is one of many possible targets. */
    public function tasks(): MorphMany
    {
        return $this->morphMany(Task::class, 'related');
    }

    /** Completed tasks only — used for progress counts via withCount(). */
    public function doneTasks(): MorphMany
    {
        return $this->morphMany(Task::class, 'related')->where('status', TaskStatus::Completed);
    }
}
