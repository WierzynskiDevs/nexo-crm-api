<?php

declare(strict_types=1);

use App\Models\File;
use App\Models\Lead;
use App\Models\Tenant;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    Storage::fake('local');
});

it('uploads a file attached to a lead', function () {
    ['token' => $token, 'tenant' => $tenant] = actingAsTenantUser('sales');
    $lead = Lead::factory()->create(['tenant_id' => $tenant->id]);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->post('/api/v1/files', [
            'file' => UploadedFile::fake()->create('proposta.pdf', 100, 'application/pdf'),
            'fileable_type' => 'lead',
            'fileable_id' => $lead->id,
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.original_name', 'proposta.pdf')
        ->assertJsonPath('data.fileable_id', $lead->id);

    $file = File::first();
    Storage::disk('local')->assertExists($file->path);
});

it('rejects uploading with a fileable reference from another tenant', function () {
    ['token' => $token] = actingAsTenantUser('sales');
    $foreignLead = Lead::factory()->create(['tenant_id' => Tenant::factory()->create()->id]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->withHeader('Accept', 'application/json')
        ->post('/api/v1/files', [
            'file' => UploadedFile::fake()->create('doc.pdf', 10),
            'fileable_type' => 'lead',
            'fileable_id' => $foreignLead->id,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('fileable_id');
});

it('generates a signed download url that successfully streams the file', function () {
    ['token' => $token, 'tenant' => $tenant] = actingAsTenantUser('sales');
    $lead = Lead::factory()->create(['tenant_id' => $tenant->id]);

    $uploadResponse = $this->withHeader('Authorization', "Bearer {$token}")
        ->post('/api/v1/files', [
            'file' => UploadedFile::fake()->create('contrato.pdf', 50),
            'fileable_type' => 'lead',
            'fileable_id' => $lead->id,
        ]);

    $fileId = $uploadResponse->json('data.id');

    $urlResponse = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/files/{$fileId}/download-url")
        ->assertOk();

    $signedPath = str_replace(url('/'), '', $urlResponse->json('data.url'));

    $this->get($signedPath)->assertOk();
});

it('rejects a stream request with an invalid signature', function () {
    ['token' => $token, 'tenant' => $tenant] = actingAsTenantUser('sales');
    $file = File::factory()->create(['tenant_id' => $tenant->id]);

    $this->get("/api/v1/files/{$file->id}/stream")->assertStatus(403);
});

it('deletes a file and removes it from disk', function () {
    ['token' => $token, 'tenant' => $tenant] = actingAsTenantUser('admin');
    $lead = Lead::factory()->create(['tenant_id' => $tenant->id]);

    $uploadResponse = $this->withHeader('Authorization', "Bearer {$token}")
        ->post('/api/v1/files', [
            'file' => UploadedFile::fake()->create('remover.pdf', 10),
            'fileable_type' => 'lead',
            'fileable_id' => $lead->id,
        ]);

    $file = File::find($uploadResponse->json('data.id'));

    $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/v1/files/{$file->id}")
        ->assertStatus(204);

    Storage::disk('local')->assertMissing($file->path);
});

it('returns 404 for a file belonging to another tenant', function () {
    ['token' => $token] = actingAsTenantUser('sales');
    $foreignFile = File::factory()->create(['tenant_id' => Tenant::factory()->create()->id]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/files/{$foreignFile->id}")
        ->assertStatus(404);
});
