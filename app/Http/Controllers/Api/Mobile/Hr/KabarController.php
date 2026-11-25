<?php

namespace App\Http\Controllers\Api\Mobile\Hr;

use App\Models\Announcement;
use App\Models\AnnouncementRead;
use App\Models\Employee;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\PostCommentLike;
use App\Models\PostLike;
use App\Models\PostReport;
use App\Support\ApprovalNotifier;
use App\Support\Mobile\Present;
use App\Support\PrivateFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Tab "Kabar": pengumuman HR (dengan tanda sudah dibaca) dan postingan karyawan
 * (suka, komentar berjenjang satu tingkat, laporkan). Audiens "Divisi" hanya untuk
 * rekan sedivisi. HR memoderasi dari web (menu Moderasi Kabar).
 */
class KabarController extends HrController
{
    /** ?filter=semua|divisi&page=N — pengumuman ikut di halaman pertama saja. */
    public function feed(Request $request): JsonResponse
    {
        $me = $this->employee();
        $division = $request->input('filter') === 'divisi';

        $posts = Post::query()
            ->visibleToEmployee($me)
            ->when($division, fn ($q) => $q->where('audience', Post::AUDIENCE_DIVISION))
            ->when($request->integer('author'), fn ($q, int $author) => $q->where('employee_id', $author))
            ->with(['author.jobPosition', 'author.department', 'department'])
            ->withCount(['likes', 'comments' => fn ($q) => $q->whereNull('hidden_at')])
            ->withExists(['likes as liked' => fn ($q) => $q->where('employee_id', $me->id)])
            ->latest('id')
            ->paginate(20);

        $announcements = $posts->currentPage() === 1
            ? $this->announcementQuery($me)->limit(5)->get()->map(fn (Announcement $a) => $this->announcementSummary($a))->values()
            : collect();

        return response()->json([
            'data' => [
                'announcements' => $announcements,
                'unread_announcements' => $this->announcementQuery($me)->whereDoesntHave('reads', fn ($q) => $q->where('employee_id', $me->id))->count(),
                'posts' => collect($posts->items())->map(fn (Post $p) => $this->post($p, $me))->values(),
            ],
            'meta' => [
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                'total' => $posts->total(),
            ],
        ]);
    }

    // --- Pengumuman HR ---

    public function announcements(): JsonResponse
    {
        $me = $this->employee();
        $page = $this->announcementQuery($me)->paginate(20);

        return $this->paginated($page, fn (Announcement $a) => $this->announcementSummary($a));
    }

    public function announcement(Announcement $announcement): JsonResponse
    {
        $me = $this->employee();
        $this->authorizeAnnouncement($announcement, $me);

        $announcement->loadMissing(['creator.employee.jobPosition', 'department']);
        $readAt = $announcement->reads()->where('employee_id', $me->id)->value('read_at');
        $announcement->setAttribute('is_read', $readAt !== null);

        return $this->ok([
            ...$this->announcementSummary($announcement),
            'body' => $announcement->body,
            'author' => $announcement->creator?->employee?->full_name ?? $announcement->creator?->name ?? 'HR',
            'audience_label' => $announcement->audience_label,
            'attachment' => $announcement->hasAttachment() ? [
                'name' => $announcement->attachment_name,
                'mime' => $announcement->attachment_mime,
                'size' => $announcement->attachment_size,
            ] : null,
            'read_at' => $readAt ? Carbon::parse($readAt)->toIso8601String() : null,
            'read_count' => $announcement->reads()->count(),
            'audience_count' => $announcement->audienceQuery()->count(),
        ]);
    }

    public function markRead(Announcement $announcement): JsonResponse
    {
        $me = $this->employee();
        $this->authorizeAnnouncement($announcement, $me);

        AnnouncementRead::query()->firstOrCreate(
            ['announcement_id' => $announcement->id, 'employee_id' => $me->id],
            ['read_at' => now()],
        );

        return $this->message('Terima kasih, sudah ditandai dibaca.', [
            'read_count' => $announcement->reads()->count(),
            'audience_count' => $announcement->audienceQuery()->count(),
        ]);
    }

    public function announcementAttachment(Announcement $announcement): StreamedResponse
    {
        $this->authorizeAnnouncement($announcement, $this->employee());
        abort_unless($announcement->hasAttachment(), 404);

        return PrivateFile::response(Storage::disk(Announcement::ATTACHMENT_DISK), $announcement->attachment_path, $announcement->attachment_name, $announcement->attachment_mime);
    }

    // --- Postingan ---

    public function storePost(Request $request): JsonResponse
    {
        $me = $this->employee();

        $data = $request->validate([
            'body' => ['required', 'string', 'max:'.Post::BODY_MAX],
            'audience' => ['required', Rule::in([Post::AUDIENCE_ALL, Post::AUDIENCE_DIVISION])],
            'image' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.(Post::IMAGE_MAX_MB * 1024)],
        ], [], ['body' => 'isi postingan', 'audience' => 'penerima', 'image' => 'foto']);

        if ($data['audience'] === Post::AUDIENCE_DIVISION && ! $me->department_id) {
            return response()->json(['message' => 'Anda belum terdaftar di divisi mana pun.', 'errors' => ['audience' => ['Anda belum terdaftar di divisi mana pun.']]], 422);
        }

        $post = Post::query()->create([
            'employee_id' => $me->id,
            'body' => $data['body'],
            'audience' => $data['audience'],
            'department_id' => $data['audience'] === Post::AUDIENCE_DIVISION ? $me->department_id : null,
            ...($request->hasFile('image') ? [
                'image_path' => $request->file('image')->store("kabar/{$me->id}", Post::IMAGE_DISK),
                'image_mime' => PrivateFile::detectedMime($request->file('image')),
            ] : []),
        ]);

        $post->loadCount(['likes', 'comments'])->load(['author.jobPosition', 'author.department']);

        return response()->json(['message' => 'Postingan terkirim.', 'data' => $this->post($post, $me)], 201);
    }

    public function showPost(Post $post): JsonResponse
    {
        $me = $this->employee();
        abort_unless($post->isVisibleTo($me), 404);

        $post->load(['author.jobPosition', 'author.department'])
            ->loadCount(['likes', 'comments' => fn ($q) => $q->whereNull('hidden_at')])
            ->loadExists(['likes as liked' => fn ($q) => $q->where('employee_id', $me->id)]);

        $comments = $post->comments()
            ->whereNull('hidden_at')
            ->with(['author.jobPosition', 'author.department'])
            ->withCount('likes')
            ->withExists(['likes as liked' => fn ($q) => $q->where('employee_id', $me->id)])
            ->oldest('id')
            ->get();

        $byParent = $comments->groupBy(fn (PostComment $c) => $c->parent_id ?? 0);

        return $this->ok([
            ...$this->post($post, $me),
            'comments' => ($byParent[0] ?? collect())->map(fn (PostComment $c) => [
                ...$this->comment($c, $me),
                'replies' => ($byParent[$c->id] ?? collect())->map(fn (PostComment $r) => $this->comment($r, $me))->values(),
            ])->values(),
        ]);
    }

    public function destroyPost(Post $post): JsonResponse
    {
        abort_unless($post->employee_id === $this->employee()->id, 403, 'Hanya penulis yang bisa menghapus postingan ini.');

        $post->delete();

        return $this->message('Postingan dihapus.');
    }

    public function postImage(Post $post): StreamedResponse
    {
        abort_unless($post->isVisibleTo($this->employee()) && $post->image_path, 404);

        return PrivateFile::response(Storage::disk(Post::IMAGE_DISK), $post->image_path, basename($post->image_path), $post->image_mime);
    }

    public function likePost(Post $post): JsonResponse
    {
        $me = $this->employee();
        abort_unless($post->isVisibleTo($me) && ! $post->isHidden(), 404);

        $existing = PostLike::query()->where('post_id', $post->id)->where('employee_id', $me->id)->first();
        $existing ? $existing->delete() : PostLike::query()->create(['post_id' => $post->id, 'employee_id' => $me->id]);

        return $this->ok(['liked' => ! $existing, 'likes_count' => $post->likes()->count()]);
    }

    public function reportPost(Request $request, Post $post): JsonResponse
    {
        $me = $this->employee();
        abort_unless($post->isVisibleTo($me), 404);
        $reason = $request->validate(['reason' => ['nullable', 'string', 'max:500']])['reason'] ?? null;

        PostReport::query()->firstOrCreate(
            ['post_id' => $post->id, 'post_comment_id' => null, 'employee_id' => $me->id, 'resolved_at' => null],
            ['reason' => $reason],
        );

        return $this->message('Terima kasih, laporan diteruskan ke HR.');
    }

    // --- Komentar ---

    public function storeComment(Request $request, Post $post): JsonResponse
    {
        $me = $this->employee();
        abort_unless($post->isVisibleTo($me) && ! $post->isHidden(), 404);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:'.PostComment::BODY_MAX],
            'parent_id' => ['nullable', 'integer', Rule::exists('post_comments', 'id')->where('post_id', $post->id)->whereNull('deleted_at')],
        ], [], ['body' => 'komentar']);

        // Balasan selalu menempel ke komentar teratas (satu tingkat, seperti Threads).
        $parentId = isset($data['parent_id'])
            ? (PostComment::query()->whereKey($data['parent_id'])->value('parent_id') ?? $data['parent_id'])
            : null;

        $comment = PostComment::query()->create([
            'post_id' => $post->id,
            'employee_id' => $me->id,
            'parent_id' => $parentId,
            'body' => $data['body'],
        ]);

        app(ApprovalNotifier::class)->postCommented($comment);

        $comment->load(['author.jobPosition', 'author.department'])->loadCount('likes');

        return response()->json(['message' => 'Komentar terkirim.', 'data' => $this->comment($comment, $me)], 201);
    }

    public function destroyComment(PostComment $comment): JsonResponse
    {
        abort_unless($comment->employee_id === $this->employee()->id, 403, 'Hanya penulis yang bisa menghapus komentar ini.');

        $comment->delete();

        return $this->message('Komentar dihapus.');
    }

    public function likeComment(PostComment $comment): JsonResponse
    {
        $me = $this->employee();
        abort_unless($comment->post && $comment->post->isVisibleTo($me) && ! $comment->hidden_at, 404);

        $existing = PostCommentLike::query()->where('post_comment_id', $comment->id)->where('employee_id', $me->id)->first();
        $existing ? $existing->delete() : PostCommentLike::query()->create(['post_comment_id' => $comment->id, 'employee_id' => $me->id]);

        return $this->ok(['liked' => ! $existing, 'likes_count' => $comment->likes()->count()]);
    }

    public function reportComment(Request $request, PostComment $comment): JsonResponse
    {
        $me = $this->employee();
        abort_unless($comment->post && $comment->post->isVisibleTo($me), 404);
        $reason = $request->validate(['reason' => ['nullable', 'string', 'max:500']])['reason'] ?? null;

        PostReport::query()->firstOrCreate(
            ['post_id' => $comment->post_id, 'post_comment_id' => $comment->id, 'employee_id' => $me->id, 'resolved_at' => null],
            ['reason' => $reason],
        );

        return $this->message('Terima kasih, laporan diteruskan ke HR.');
    }

    // --- Bentuk data ---

    private function announcementQuery(Employee $me)
    {
        return Announcement::query()->published()->visibleToEmployee($me)
            ->withExists(['reads as is_read' => fn ($q) => $q->where('employee_id', $me->id)])
            ->orderByDesc('is_pinned')->latest('published_at');
    }

    private function announcementSummary(Announcement $a): array
    {
        return [
            'id' => $a->id,
            'title' => $a->title,
            'excerpt' => Str::limit(strip_tags($a->body), 140),
            'is_pinned' => $a->is_pinned,
            'is_read' => (bool) ($a->is_read ?? false),
            'has_attachment' => $a->hasAttachment(),
            'published_at' => $a->published_at?->toIso8601String(),
        ];
    }

    private function authorizeAnnouncement(Announcement $announcement, Employee $me): void
    {
        abort_unless(
            $announcement->published_at && $announcement->published_at->lessThanOrEqualTo(now()) && $announcement->isVisibleTo($me),
            404,
        );
    }

    private function post(Post $post, Employee $me): array
    {
        return [
            'id' => $post->id,
            'author' => Present::person($post->author),
            'body' => $post->body,
            'audience' => $post->audience,
            'audience_label' => $post->audience === Post::AUDIENCE_DIVISION ? 'Divisi '.($post->department?->name ?? '') : 'Semua karyawan',
            'has_image' => $post->image_path !== null,
            'likes_count' => (int) ($post->likes_count ?? 0),
            'comments_count' => (int) ($post->comments_count ?? 0),
            'liked' => (bool) ($post->liked ?? false),
            'is_mine' => $post->employee_id === $me->id,
            'is_hidden' => $post->isHidden(),
            'hidden_reason' => $post->employee_id === $me->id ? $post->hidden_reason : null,
            'created_at' => $post->created_at?->toIso8601String(),
        ];
    }

    private function comment(PostComment $comment, Employee $me): array
    {
        return [
            'id' => $comment->id,
            'parent_id' => $comment->parent_id,
            'author' => Present::person($comment->author),
            'body' => $comment->body,
            'likes_count' => (int) ($comment->likes_count ?? 0),
            'liked' => (bool) ($comment->liked ?? false),
            'is_mine' => $comment->employee_id === $me->id,
            'created_at' => $comment->created_at?->toIso8601String(),
        ];
    }
}
