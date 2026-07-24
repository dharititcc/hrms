<?php

namespace App\Services;

use App\Models\Staff;
use App\Repositories\StaffRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class StaffService
{
    public function __construct(private readonly StaffRepository $repository) {}

    public function list(int $ownerId, array $filters = []): LengthAwarePaginator
    {
        return $this->repository->paginateForOwner($ownerId, $filters);
    }

    public function create(int $ownerId, array $attributes): Staff
    {
        return DB::transaction(fn (): Staff => $this->repository->create($ownerId, $attributes));
    }

    public function update(Staff $staff, array $attributes): Staff
    {
        return DB::transaction(fn (): Staff => $this->repository->update($staff, $attributes));
    }

    public function delete(Staff $staff): void
    {
        DB::transaction(fn () => $this->repository->delete($staff));
    }
}
