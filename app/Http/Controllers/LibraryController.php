<?php

namespace App\Http\Controllers;

use App\Models\LibraryFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LibraryController extends Controller
{
    public function index(): Response
    {
        $files = LibraryFile::query()
            ->with('user:id,name')
            ->latest()
            ->get()
            ->map(fn (LibraryFile $f) => $this->present($f));

        return Inertia::render('Library/Index', [
            'files' => $files,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => 'required|file|max:51200',
            'name' => 'nullable|string|max:255',
            'is_public' => 'sometimes|boolean',
        ]);

        $upload = $request->file('file');
        $path = $upload->store('library', LibraryFile::DISK);

        $file = LibraryFile::create([
            'user_id' => $request->user()->id,
            'name' => ($validated['name'] ?? null) ?: $upload->getClientOriginalName(),
            'original_filename' => $upload->getClientOriginalName(),
            'path' => $path,
            'mime' => $upload->getMimeType(),
            'size' => $upload->getSize(),
            'is_public' => (bool) ($validated['is_public'] ?? false),
            'token' => Str::random(40),
        ]);

        return response()->json([
            'file' => $this->present($file->load('user:id,name')),
            'message' => 'Archivo cargado.',
        ], 201);
    }

    public function update(Request $request, LibraryFile $file): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'is_public' => 'sometimes|boolean',
        ]);

        $file->update($validated);

        return response()->json([
            'file' => $this->present($file->refresh()->load('user:id,name')),
            'message' => $file->is_public ? 'El archivo ahora es público.' : 'Archivo actualizado.',
        ]);
    }

    public function destroy(Request $request, LibraryFile $file): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || $file->user_id === $user->id, 403);

        Storage::disk(LibraryFile::DISK)->delete($file->path);
        $file->delete();

        return response()->json(['message' => 'Archivo eliminado.']);
    }

    /** Descarga para usuarios autenticados (público o privado). */
    public function download(LibraryFile $file): StreamedResponse
    {
        return $this->serve($file);
    }

    /** Enlace público sin sesión: solo si el archivo está marcado como público. */
    public function publicShow(string $token): StreamedResponse
    {
        $file = LibraryFile::where('token', $token)->where('is_public', true)->firstOrFail();

        return $this->serve($file);
    }

    private function serve(LibraryFile $file): StreamedResponse
    {
        $disk = Storage::disk(LibraryFile::DISK);
        abort_unless($disk->exists($file->path), 404);

        // inline: el navegador muestra PDF/imagen/audio; el resto se descarga.
        // CSP sandbox + nosniff: un HTML/SVG subido no puede ejecutar scripts en nuestro dominio.
        return $disk->response($file->path, $file->original_filename, [
            'Content-Type' => $file->mime ?: 'application/octet-stream',
            'Content-Security-Policy' => 'sandbox',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function present(LibraryFile $f): array
    {
        return [
            'id' => $f->id,
            'name' => $f->name,
            'original_filename' => $f->original_filename,
            'mime' => $f->mime,
            'size' => $f->size,
            'is_public' => $f->is_public,
            'public_url' => route('library.public', $f->token),
            'private_url' => route('library.download', $f->id),
            'uploaded_by' => $f->user?->name,
            'user_id' => $f->user_id,
            'created_at' => $f->created_at?->toISOString(),
        ];
    }
}
