<?php

namespace App\Services;

use App\Models\Project;
use App\Repositories\ProjectRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ProjectService
{
    public function __construct(private readonly ProjectRepository $repository) {}

    public function list(int $ownerId, array $filters = []): LengthAwarePaginator
    {
        return $this->repository->paginateForOwner($ownerId, $filters);
    }

    public function find(Project $project): Project
    {
        return $this->repository->loadDetails($project);
    }

    public function create(int $ownerId, array $attributes): Project
    {
        return DB::transaction(function () use ($ownerId, $attributes): Project {
            $memberIds = $attributes['member_ids'] ?? [];
            unset($attributes['member_ids']);

            $project = $this->repository->create($ownerId, $attributes);
            $this->repository->syncMembers($project, $memberIds);

            return $this->repository->loadDetails($project);
        });
    }

    public function update(Project $project, array $attributes): Project
    {
        return DB::transaction(function () use ($project, $attributes): Project {
            $memberIds = $attributes['member_ids'] ?? null;
            unset($attributes['member_ids']);

            $project = $this->repository->update($project, $attributes);

            if ($memberIds !== null) {
                $this->repository->syncMembers($project, $memberIds);
            }

            return $this->repository->loadDetails($project);
        });
    }

    public function delete(Project $project): void
    {
        DB::transaction(fn () => $this->repository->delete($project));
    }
}
