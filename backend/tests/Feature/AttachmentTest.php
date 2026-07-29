<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttachmentTest extends TestCase
{
    use RefreshDatabase;

    private function project(User $owner, string $name = 'Website relaunch'): Project
    {
        return Project::create(['owner_id' => $owner->id, 'name' => $name, 'status' => 'active']);
    }

    public function test_a_file_can_be_uploaded_listed_downloaded_and_deleted(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $project = $this->project($user);
        Sanctum::actingAs($user);

        $upload = $this->postJson('/api/auth/attachments', [
            'attachable_type' => 'project',
            'attachable_id' => $project->id,
            'file' => UploadedFile::fake()->create('brief.pdf', 120, 'application/pdf'),
        ]);

        $upload->assertCreated()->assertJsonPath('data.original_name', 'brief.pdf');

        $attachment = Attachment::firstOrFail();
        Storage::disk('local')->assertExists($attachment->path);

        // The stored path must not reuse the client-supplied filename.
        $this->assertStringNotContainsString('brief.pdf', $attachment->path);

        $this->getJson("/api/auth/attachments?attachable_type=project&attachable_id={$project->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->get("/api/auth/attachments/{$attachment->id}/download")->assertOk();

        $this->deleteJson("/api/auth/attachments/{$attachment->id}")->assertOk();
        Storage::disk('local')->assertMissing($attachment->path);
        $this->assertSoftDeleted('attachments', ['id' => $attachment->id]);
    }

    public function test_files_cannot_be_attached_to_another_workspaces_record(): void
    {
        Storage::fake('local');

        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $project = $this->project($owner);

        Sanctum::actingAs($intruder);

        $this->postJson('/api/auth/attachments', [
            'attachable_type' => 'project',
            'attachable_id' => $project->id,
            'file' => UploadedFile::fake()->create('sneaky.pdf', 10, 'application/pdf'),
        ])->assertNotFound();
    }

    public function test_unmapped_and_non_workspace_types_are_rejected(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        foreach (['App\\Models\\User', 'user', 'nonsense'] as $type) {
            $this->postJson('/api/auth/attachments', [
                'attachable_type' => $type,
                'attachable_id' => $user->id,
                'file' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'),
            ])->assertStatus(422);
        }
    }

    public function test_disallowed_extensions_and_oversized_files_are_rejected(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $project = $this->project($user);
        Sanctum::actingAs($user);

        $this->postJson('/api/auth/attachments', [
            'attachable_type' => 'project',
            'attachable_id' => $project->id,
            'file' => UploadedFile::fake()->create('payload.exe', 10),
        ])->assertStatus(422)->assertJsonValidationErrors('file');

        $this->postJson('/api/auth/attachments', [
            'attachable_type' => 'project',
            'attachable_id' => $project->id,
            'file' => UploadedFile::fake()->create('huge.pdf', config('attachments.max_size_kb') + 1024),
        ])->assertStatus(422)->assertJsonValidationErrors('file');
    }

    public function test_another_workspace_cannot_download_an_attachment(): void
    {
        Storage::fake('local');

        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $project = $this->project($owner);

        Sanctum::actingAs($owner);
        $this->postJson('/api/auth/attachments', [
            'attachable_type' => 'project',
            'attachable_id' => $project->id,
            'file' => UploadedFile::fake()->create('private.pdf', 10, 'application/pdf'),
        ])->assertCreated();

        $attachment = Attachment::firstOrFail();

        Sanctum::actingAs($intruder);
        $this->get("/api/auth/attachments/{$attachment->id}/download")->assertForbidden();
        $this->deleteJson("/api/auth/attachments/{$attachment->id}")->assertForbidden();
    }
}
