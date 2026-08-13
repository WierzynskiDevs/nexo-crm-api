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
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileController extends Controller
{
    private const DISK = 'local';

    #[OA\Get(
        path: '/api/v1/files',
        summary: 'Lista os arquivos do tenant',
        security: [['bearerAuth' => []]],
        tags: ['Arquivos'],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/page'),
            new OA\Parameter(ref: '#/components/parameters/perPage'),
            new OA\Parameter(name: 'fileable_type', description: 'Filtra pelo tipo do recurso anexado', in: 'query', schema: new OA\Schema(type: 'string', enum: ['lead', 'client', 'opportunity', 'task'])),
            new OA\Parameter(name: 'fileable_id', in: 'query', schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Coleção paginada', content: new OA\JsonContent(ref: '#/components/schemas/FileCollection')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
        ],
    )]
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

    #[OA\Post(
        path: '/api/v1/files',
        summary: 'Faz upload de um arquivo',
        description: <<<'MD'
            `multipart/form-data`. O arquivo vai para storage privado, com nome
            gerado (o nome original nunca compõe o caminho) e sob o diretório do
            tenant. O MIME real é validado, não apenas a extensão declarada.

            O arquivo não fica acessível por URL pública: para baixar, peça uma URL
            assinada em `GET /files/{file}/download-url`.
            MD,
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['file'],
                    properties: [
                        new OA\Property(property: 'file', type: 'string', format: 'binary'),
                        new OA\Property(property: 'fileable_type', description: 'Recurso ao qual anexar', type: 'string', enum: ['lead', 'client', 'opportunity', 'task'], nullable: true),
                        new OA\Property(property: 'fileable_id', description: 'Precisa pertencer ao mesmo tenant', type: 'string', format: 'uuid', nullable: true),
                    ],
                    type: 'object',
                ),
            ),
        ),
        tags: ['Arquivos'],
        responses: [
            new OA\Response(response: 201, description: 'Arquivo enviado', content: new OA\JsonContent(ref: '#/components/schemas/FileEnvelope')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, description: 'Arquivo ausente, tipo não permitido, tamanho excedido ou referência de outro tenant', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ],
    )]
    public function store(StoreFileRequest $request): FileResource
    {
        $uploaded = $request->file('file');
        $tenantId = app(TenantContext::class)->id();

        // Extensão e MIME derivados do CONTEÚDO do arquivo, nunca do que o
        // cliente declarou: getClientOriginalExtension()/getClientMimeType()
        // são strings arbitrárias do atacante e acabariam no path em disco e
        // no Content-Type de quem baixa.
        $extension = $uploaded->guessExtension();
        $diskPath = sprintf('%s/%s.%s', $tenantId, (string) Str::uuid7(), $extension ?: 'bin');

        Storage::disk(self::DISK)->put($diskPath, file_get_contents($uploaded->getRealPath()));

        $file = File::create([
            'fileable_type' => $request->input('fileable_type'),
            'fileable_id' => $request->input('fileable_id'),
            'uploaded_by_user_id' => Auth::guard('api')->id(),
            'disk' => self::DISK,
            'path' => $diskPath,
            'original_name' => $uploaded->getClientOriginalName(),
            'mime_type' => $uploaded->getMimeType(),
            'size_bytes' => $uploaded->getSize(),
        ]);

        return new FileResource($file->load('uploadedBy'));
    }

    #[OA\Get(
        path: '/api/v1/files/{file}',
        summary: 'Exibe os metadados de um arquivo',
        description: 'Não devolve o conteúdo — para isso, peça a URL assinada.',
        security: [['bearerAuth' => []]],
        tags: ['Arquivos'],
        parameters: [new OA\Parameter(name: 'file', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/FileEnvelope')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ],
    )]
    public function show(File $file): FileResource
    {
        $this->authorize('view', $file);

        return new FileResource($file->load('uploadedBy'));
    }

    #[OA\Delete(
        path: '/api/v1/files/{file}',
        summary: 'Exclui um arquivo',
        description: 'Remove o registro e também os bytes do storage.',
        security: [['bearerAuth' => []]],
        tags: ['Arquivos'],
        parameters: [new OA\Parameter(name: 'file', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(response: 204, ref: '#/components/responses/NoContent'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ],
    )]
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
    #[OA\Get(
        path: '/api/v1/files/{file}/download-url',
        summary: 'Gera uma URL assinada e temporária para download',
        description: 'A URL vale 5 minutos e não exige Bearer token — a assinatura é a autorização. Serve para o navegador baixar direto, sem expor o token.',
        security: [['bearerAuth' => []]],
        tags: ['Arquivos'],
        parameters: [new OA\Parameter(name: 'file', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(
                response: 200,
                description: 'URL assinada',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'data', properties: [
                        new OA\Property(property: 'url', type: 'string'),
                        new OA\Property(property: 'expires_in', description: 'Validade em segundos', type: 'integer', example: 300),
                    ], type: 'object'),
                ], type: 'object'),
            ),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ],
    )]
    public function downloadUrl(File $file): JsonResponse
    {
        $this->authorize('view', $file);

        $url = URL::temporarySignedRoute('files.stream', now()->addMinutes(5), ['file' => $file->id]);

        return response()->json(['data' => ['url' => $url, 'expires_in' => 300]]);
    }

    #[OA\Get(
        path: '/api/v1/files/{file}/stream',
        summary: 'Baixa o conteúdo do arquivo (URL assinada)',
        description: 'Rota deliberadamente fora do grupo autenticado: não aceita Bearer token, só a assinatura gerada em `/download-url`. Normalmente não é chamada à mão — use a URL pronta.',
        tags: ['Arquivos'],
        parameters: [
            new OA\Parameter(name: 'file', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'expires', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'signature', in: 'query', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Conteúdo do arquivo', content: new OA\MediaType(mediaType: 'application/octet-stream', schema: new OA\Schema(type: 'string', format: 'binary'))),
            new OA\Response(response: 403, description: 'Assinatura inválida ou expirada', content: new OA\JsonContent(ref: '#/components/schemas/ErrorMessage')),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ],
    )]
    public function stream(string $file): StreamedResponse
    {
        $model = File::withoutGlobalScope(TenantScope::class)->findOrFail($file);

        // "attachment" (o default do Laravel aqui é "inline") + nosniff: o
        // arquivo é servido a partir da origem da API, onde vive o cookie de
        // refresh. Renderizar conteúdo do usuário inline nessa origem
        // transformaria um upload em XSS com acesso à sessão da vítima.
        return Storage::disk($model->disk)->response(
            $model->path,
            $model->original_name,
            ['X-Content-Type-Options' => 'nosniff'],
            'attachment',
        );
    }
}
