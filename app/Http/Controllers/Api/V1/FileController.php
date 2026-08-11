<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Files\StoreFileRequest;
use App\Http\Resources\FileResource;
use App\Models\File;
use App\Models\Scopes\TenantScope;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileController extends Controller
{
    private const DISK = 'local';

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', File::class);

        $files = File::query()
            ->with('uploadedBy')
            ->when($request->filled('fileable_type'), fn ($query) => $query->where('fileable_type', $request->string('fileable_type')))
            ->when($request->filled('fileable_id'), fn ($query) => $query->where('fileable_id', $request->string('fileable_id')))
            ->latest()
            ->paginate($request->integer('per_page', 25));

        return FileResource::collection($files);
    }

    public function store(StoreFileRequest $request): FileResource
    {
        $uploaded = $request->file('file');
        $tenantId = app(TenantContext::class)->id();
        $extension = $uploaded->getClientOriginalExtension();
        $diskPath = sprintf('%s/%s.%s', $tenantId, (string) Str::uuid7(), $extension ?: 'bin');

        Storage::disk(self::DISK)->put($diskPath, file_get_contents($uploaded->getRealPath()));

        $file = File::create([
            'fileable_type' => $request->input('fileable_type'),
            'fileable_id' => $request->input('fileable_id'),
            'uploaded_by_user_id' => Auth::guard('api')->id(),
            'disk' => self::DISK,
            'path' => $diskPath,
            'original_name' => $uploaded->getClientOriginalName(),
            'mime_type' => $uploaded->getClientMimeType(),
            'size_bytes' => $uploaded->getSize(),
        ]);

        return new FileResource($file->load('uploadedBy'));
    }

    public function show(File $file): FileResource
    {
        $this->authorize('view', $file);

        return new FileResource($file->load('uploadedBy'));
    }

    public function destroy(File $file): JsonResponse
    {
        $this->authorize('delete', $file);

        Storage::disk($file->disk)->delete($file->path);
        $file->delete();

        return response()->json(null, 204);
    }

    /**
     * Não retorna os bytes direto: devolve uma URL assinada e temporária
     * (rota "stream", fora do grupo auth:api/tenant) para o navegador baixar
     * sem precisar enviar o Bearer token — a assinatura é a autorização.
     */
    public function downloadUrl(File $file): JsonResponse
    {
        $this->authorize('view', $file);

        $url = URL::temporarySignedRoute('files.stream', now()->addMinutes(5), ['file' => $file->id]);

        return response()->json(['data' => ['url' => $url, 'expires_in' => 300]]);
    }

    public function stream(string $file): StreamedResponse
    {
        $model = File::withoutGlobalScope(TenantScope::class)->findOrFail($file);

        return Storage::disk($model->disk)->response($model->path, $model->original_name);
    }
}
