<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One departure from what an employee's role grants.
 *
 * Rows exist only where access differs from the role, so the absence of a row
 * means "whatever the role says".
 */
#[Fillable(['owner_id', 'staff_id', 'permission', 'granted', 'granted_by'])]
#[Hidden(['owner_id'])]
class EmployeePermissionOverride extends Model
{
    protected function casts(): array
    {
        return ['granted' => 'boolean'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'staff_id');
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }
}
