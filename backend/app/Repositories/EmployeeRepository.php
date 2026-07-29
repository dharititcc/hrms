<?php

namespace App\Repositories;

use App\Models\Employee;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EmployeeRepository
{
    public function paginateForOwner(int $ownerId, array $filters = []): LengthAwarePaginator
    {
        return Employee::query()
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

    public function findForOwnerOrFail(int $ownerId, int $employeeId): Employee
    {
        return Employee::query()->where('owner_id', $ownerId)->findOrFail($employeeId);
    }

    public function create(int $ownerId, array $attributes): Employee
    {
        return Employee::create([...$attributes, 'owner_id' => $ownerId]);
    }

    public function update(Employee $employee, array $attributes): Employee
    {
        $employee->update($attributes);

        return $employee->refresh();
    }

    public function delete(Employee $employee): void
    {
        $employee->delete();
    }
}
