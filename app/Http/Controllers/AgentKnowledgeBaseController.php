<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\AgentKnowledgeDocument;
use App\Services\AgentKnowledgeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Base de conocimiento del agente: cargar documentos (PDF/TXT/MD), convertirlos
 * a Markdown localmente, editarlos y habilitarlos/deshabilitarlos para su
 * inyección en el prompt del agente conversacional.
 */
class AgentKnowledgeBaseController extends Controller
{
    public function __construct(private AgentKnowledgeService $knowledge) {}

    public function index(Agent $agent): JsonResponse
    {
        $this->authorize('view', $agent);

        return response()->json([
            'documents' => $agent->knowledgeDocuments()->orderBy('order')->orderBy('id')->get()
                ->map(fn (AgentKnowledgeDocument $d) => $this->present($d)),
        ]);
    }

    public function store(Request $request, Agent $agent): JsonResponse
    {
        $this->authorize('update', $agent);

        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'file' => 'required|file|max:20480|mimetypes:application/pdf,text/plain,text/markdown,text/x-markdown,application/octet-stream',
        ]);

        $file = $request->file('file');
        $markdown = $this->knowledge->toMarkdown($file);

        $title = trim((string) ($validated['title'] ?? ''));
        if ($title === '') {
            $title = pathinfo((string) $file->getClientOriginalName(), PATHINFO_FILENAME) ?: 'Documento';
        }

        $doc = $agent->knowledgeDocuments()->create([
            'title' => $title,
            'original_filename' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
            'content' => $markdown,
            'enabled' => true,
            'order' => (int) $agent->knowledgeDocuments()->max('order') + 1,
        ]);

        return response()->json([
            'document' => $this->present($doc),
            'message' => $markdown === ''
                ? 'Documento cargado, pero no se pudo extraer texto (¿PDF escaneado como imagen?). Puedes pegar el contenido manualmente.'
                : 'Documento cargado y convertido a Markdown.',
        ], 201);
    }

    public function update(Request $request, Agent $agent, AgentKnowledgeDocument $document): JsonResponse
    {
        $this->authorize('update', $agent);
        abort_unless($document->agent_id === $agent->id, 404);

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'content' => 'sometimes|nullable|string',
            'enabled' => 'sometimes|boolean',
        ]);

        $document->update($validated);

        return response()->json([
            'document' => $this->present($document->refresh()),
            'message' => 'Documento actualizado.',
        ]);
    }

    public function destroy(Agent $agent, AgentKnowledgeDocument $document): JsonResponse
    {
        $this->authorize('update', $agent);
        abort_unless($document->agent_id === $agent->id, 404);

        $document->delete();

        return response()->json(['message' => 'Documento eliminado.']);
    }

    private function present(AgentKnowledgeDocument $d): array
    {
        return [
            'id' => $d->id,
            'title' => $d->title,
            'original_filename' => $d->original_filename,
            'mime' => $d->mime,
            'size' => $d->size,
            'content' => $d->content,
            'enabled' => $d->enabled,
            'order' => $d->order,
            'updated_at' => $d->updated_at?->toIso8601String(),
        ];
    }
}
