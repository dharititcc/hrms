<?php

namespace App\Repositories;

use App\Models\Project;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ProjectRepository
{
    public function paginateForOwner(int $ownerId, array $filters = []): LengthAwarePaginator
    {
        return Project::query()
            ->where('owner_id', $ownerId)
            ->with('members')
            ->withCount(['tasks', 'doneTasks'])
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('client', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->latest()
            ->paginate((int) ($filters['per_page'] ?? 15))
            ->withQueryString();
    }

    public function loadDetails(Project $project): Project
    {
        return $project->load('members')->loadCount(['tasks', 'doneTasks']);
    }

    public function create(int $ownerId, array $attributes): Project
    {
        return Project::create([...$attributes, 'owner_id' => $ownerId]);
    }

    public function update(Project $project, array $attributes): Project
    {
        $project->update($attributes);

        return $project->refresh();
    }

    public function syncMembers(Project $project, array $staffIds): void
    {
        $project->members()->sync($staffIds);
    }

    public function delete(Project $project): void
    {
        $project->delete();
    }
}
