<?php

namespace App\Repositories;

use App\Models\Staff;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class StaffRepository
{
    public function paginateForOwner(int $ownerId, array $filters = []): LengthAwarePaginator
    {
        return Staff::query()
            ->where('owner_id', $ownerId)
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['role'] ?? null, fn ($query, string $role) => $query->where('role', $role))
            ->latest()
            ->paginate((int) ($filters['per_page'] ?? 15))
            ->withQueryString();
    }

    public function findForOwnerOrFail(int $ownerId, int $staffId): Staff
    {
        return Staff::query()->where('owner_id', $ownerId)->findOrFail($staffId);
    }

    public function create(int $ownerId, array $attributes): Staff
    {
        return Staff::create([...$attributes, 'owner_id' => $ownerId]);
    }

    public function update(Staff $staff, array $attributes): Staff
    {
        $staff->update($attributes);

        return $staff->refresh();
    }

    public function delete(Staff $staff): void
    {
        $staff->delete();
    }
}
