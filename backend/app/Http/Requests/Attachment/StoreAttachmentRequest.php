<?php

namespace App\Http\Requests\Attachment;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use App\Support\WorkspaceRecords;
use Illuminate\Validation\Rule;

class StoreAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'attachable_type' => ['required', 'string', Rule::in(WorkspaceRecords::aliases())],
            'attachable_id' => ['required', 'integer', 'min:1'],
            'file' => [
                'required',
                'file',
                'max:'.config('attachments.max_size_kb'),
                'extensions:'.implode(',', config('attachments.allowed_extensions')),
            ],
        ];
    }
}
