<?php

namespace App\Models;

use App\Enums\EmployeeRole;
use App\Enums\EmployeeStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A person employed in the workspace.
 *
 * Named Employee throughout the code, the API and the interface, but the
 * table and its foreign keys are still staff and staff_id: renaming those
 * would mean a migration across every table that references an employee, for
 * no behavioural gain. Every relation below therefore names its key
 * explicitly, because Eloquent would otherwise guess employee_id.
 */
#[Fillable(['owner_id', 'name', 'email', 'phone', 'role', 'status'])]
#[Hidden(['owner_id'])]
class Employee extends Model
{
    protected $table = 'staff';

    protected function casts(): array
    {
        return [
            'role' => EmployeeRole::class,
            'status' => EmployeeStatus::class,
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** The login account for this employee, once invited. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Bank and tax details. At most one, enforced by a unique staff_id. */
    public function payrollProfile(): HasOne
    {
        return $this->hasOne(EmployeePayrollProfile::class, 'staff_id');
    }
}
