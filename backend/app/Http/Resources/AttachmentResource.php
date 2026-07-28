<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttachmentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'attachable_type' => $this->attachable_type,
            'attachable_id' => $this->attachable_id,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'uploaded_by' => $this->uploaded_by,
            'uploader_name' => $this->whenLoaded('uploader', fn () => $this->uploader?->name),
            'download_url' => route('attachments.download', $this->id),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
