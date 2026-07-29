<?php

namespace App\Services;

use App\Models\Employee;
use App\Repositories\EmployeeRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class EmployeeService
{
    public function __construct(private readonly EmployeeRepository $repository) {}

    public function list(int $ownerId, array $filters = []): LengthAwarePaginator
    {
        return $this->repository->paginateForOwner($ownerId, $filters);
    }

    public function create(int $ownerId, array $attributes): Employee
    {
        return DB::transaction(fn (): Employee => $this->repository->create($ownerId, $attributes));
    }

    public function update(Employee $employee, array $attributes): Employee
    {
        return DB::transaction(fn (): Employee => $this->repository->update($employee, $attributes));
    }

    public function delete(Employee $employee): void
    {
        DB::transaction(fn () => $this->repository->delete($employee));
    }
}
