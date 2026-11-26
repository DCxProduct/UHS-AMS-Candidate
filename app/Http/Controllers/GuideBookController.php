<?php

namespace App\Http\Controllers;

use App\Models\GuideBook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class GuideBookController extends Controller
{
    public function index()
    {
        return view('guide-books.index', ['guides' => GuideBook::query()
            ->published()->orderBy('id')->paginate(12)]);
    }

    public function open()
    {
        $guide = GuideBook::query()->published()
            ->orderBy('id')->first();

        return $guide
            ? redirect()->route('guide-books.show', $guide)
            : redirect()->route('guide-books.index');
    }

    private function publishedGuide(int $guideBook): GuideBook
    {
        return GuideBook::query()->published()->findOrFail($guideBook);
    }

    public function show(int $guideBook)
    {
        return view('guide-books.show', ['guide' => $this->publishedGuide($guideBook)]);
    }

    public function file(Request $request, int $guideBook)
    {
        $guide = $this->publishedGuide($guideBook);

        return $this->stream($request, $guide);
    }

    public function preview(Request $request, GuideBook $guideBook)
    {
        Gate::authorize('view', $guideBook);

        return $this->stream($request, $guideBook);
    }

    private function stream(Request $request, GuideBook $guide)
    {
        abort_unless(GuideBook::validFilePath($guide->file_path), 404);
        $disk = Storage::disk('guide_books');
        abort_unless($disk->exists($guide->file_path), 404);
        $mime = $disk->mimeType($guide->file_path);
        abort_unless(in_array($mime, GuideBook::MIME_TYPES, true), 404);

        return $disk->response($guide->file_path, basename($guide->file_name ?: $guide->file_path), [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store, private',
            'Content-Security-Policy' => "default-src 'none'; frame-ancestors 'self'",
        ], $request->boolean('download') ? 'attachment' : 'inline');
    }
}
