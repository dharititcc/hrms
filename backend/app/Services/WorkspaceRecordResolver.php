<?php

namespace App\Services;

use App\Models\User;
use App\Support\WorkspaceRecords;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Turns a client-supplied morph alias + id into a model inside the caller's
 * workspace.
 *
 * Shared by every polymorphic feature (attachments, activity, and later task
 * and meeting relations) so the tenancy check lives in exactly one place.
 */
class WorkspaceRecordResolver
{
    public function resolve(string $type, int $id, User $user): Model
    {
        // Checked against the workspace-record list, not the full morph map:
        // User is mapped for notifications but has no owner_id and must never
        // be reachable here.
        if (! WorkspaceRecords::supports($type)) {
            throw new NotFoundHttpException('Unknown record type.');
        }

        $class = WorkspaceRecords::map()[$type];

        $model = $class::query()
            ->where('owner_id', $user->workspaceOwnerId())
            ->find($id);

        if ($model === null) {
            throw new NotFoundHttpException('Record not found.');
        }

        return $model;
    }
}
