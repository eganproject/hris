<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\PostComment;
use App\Models\PostReport;
use App\Support\ApprovalNotifier;
use App\Support\PrivateFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Moderasi tab Kabar oleh HR: postingan yang dilaporkan, sembunyikan/tampilkan
 * postingan atau komentar, dan tutup laporan. Postingan tidak dihapus HR, hanya
 * disembunyikan, supaya jejaknya tetap ada bila dipersoalkan.
 */
class KabarModerationController extends Controller
{
    /** ?filter=dilaporkan (bawaan) | disembunyikan | semua */
    public function index(Request $request): View
    {
        $filter = $request->input('filter', 'dilaporkan');

        $posts = Post::query()
            ->with(['author.department', 'department', 'hider'])
            ->withCount(['likes', 'comments', 'reports as open_reports_count' => fn ($q) => $q->whereNull('resolved_at')])
            ->when($filter === 'dilaporkan', fn ($q) => $q->whereHas('reports', fn ($r) => $r->whereNull('resolved_at')))
            ->when($filter === 'disembunyikan', fn ($q) => $q->whereNotNull('hidden_at'))
            ->when($request->input('search'), fn ($q, $s) => $q->where('body', 'like', "%{$s}%"))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('kabar.moderation.index', [
            'posts' => $posts,
            'filter' => $filter,
            'search' => $request->input('search'),
            'openReports' => PostReport::query()->open()->count(),
        ]);
    }

    public function show(Post $post): View
    {
        return view('kabar.moderation.show', [
            'post' => $post->load(['author.department', 'department', 'hider'])->loadCount('likes'),
            'comments' => $post->comments()->with(['author', 'parent'])->oldest('id')->get(),
            'reports' => $post->reports()->with(['reporter', 'comment'])->latest('id')->get(),
        ]);
    }

    public function hidePost(Request $request, Post $post): RedirectResponse
    {
        $reason = $request->validate(['reason' => ['nullable', 'string', 'max:255']])['reason'] ?? null;

        $post->update(['hidden_at' => now(), 'hidden_by' => $request->user()->id, 'hidden_reason' => $reason]);
        $this->resolveReports($post, $request->user()->id);
        app(ApprovalNotifier::class)->postHidden($post);

        return back()->with('status', 'Postingan disembunyikan dari Kabar.');
    }

    public function unhidePost(Post $post): RedirectResponse
    {
        $post->update(['hidden_at' => null, 'hidden_by' => null, 'hidden_reason' => null]);

        return back()->with('status', 'Postingan ditampilkan kembali.');
    }

    public function toggleComment(Request $request, PostComment $comment): RedirectResponse
    {
        $hide = $comment->hidden_at === null;
        $comment->update(['hidden_at' => $hide ? now() : null, 'hidden_by' => $hide ? $request->user()->id : null]);

        if ($hide) {
            PostReport::query()->open()->where('post_comment_id', $comment->id)
                ->update(['resolved_at' => now(), 'resolved_by' => $request->user()->id]);
        }

        return back()->with('status', $hide ? 'Komentar disembunyikan.' : 'Komentar ditampilkan kembali.');
    }

    /** Laporan dinilai tidak melanggar: tutup tanpa menyembunyikan apa pun. */
    public function dismissReports(Request $request, Post $post): RedirectResponse
    {
        $this->resolveReports($post, $request->user()->id);

        return back()->with('status', 'Laporan ditutup.');
    }

    public function image(Post $post): StreamedResponse
    {
        abort_unless($post->image_path, 404);

        return PrivateFile::response(Storage::disk(Post::IMAGE_DISK), $post->image_path, basename($post->image_path), $post->image_mime);
    }

    private function resolveReports(Post $post, int $userId): void
    {
        $post->reports()->whereNull('resolved_at')->update(['resolved_at' => now(), 'resolved_by' => $userId]);
    }
}
