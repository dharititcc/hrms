<?php

namespace App\Services;

use App\Models\Attachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AttachmentService
{
    public function __construct(private readonly WorkspaceRecordResolver $resolver) {}

    /** Resolves a morph alias + id to a model inside the caller's workspace. */
    public function resolveAttachable(string $type, int $id, User $user): Model
    {
        return $this->resolver->resolve($type, $id, $user);
    }

    public function store(UploadedFile $file, Model $attachable, User $uploader): Attachment
    {
        $disk = config('attachments.disk');
        $ownerId = $uploader->workspaceOwnerId();

        // Random stored name: never trust the client filename on disk. The
        // original is kept as metadata for display and download.
        $path = $file->storeAs(
            "attachments/{$ownerId}",
            Str::ulid().'.'.$file->getClientOriginalExtension(),
            ['disk' => $disk],
        );

        return DB::transaction(fn (): Attachment => Attachment::create([
            'owner_id' => $ownerId,
            'uploaded_by' => $uploader->id,
            'attachable_type' => $attachable->getMorphClass(),
            'attachable_id' => $attachable->getKey(),
            'disk' => $disk,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
        ]));
    }

    /** Soft deletes the record and removes the underlying file. */
    public function delete(Attachment $attachment): void
    {
        DB::transaction(function () use ($attachment): void {
            $disk = $attachment->disk;
            $path = $attachment->path;

            $attachment->delete();

            if (Storage::disk($disk)->exists($path)) {
                Storage::disk($disk)->delete($path);
            }
        });
    }
}
