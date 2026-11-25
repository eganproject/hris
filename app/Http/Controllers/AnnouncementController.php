<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Department;
use App\Support\ApprovalNotifier;
use App\Support\PrivateFile;
use App\Support\UploadMessages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Pengumuman HR untuk tab Kabar di aplikasi HP: tulis, terbitkan, dan pantau siapa
 * yang sudah menandai "Saya sudah membaca".
 */
class AnnouncementController extends Controller
{
    public function index(Request $request): View
    {
        $announcements = Announcement::query()
            ->with(['creator', 'department'])
            ->withCount('reads')
            ->when($request->input('search'), fn ($q, $s) => $q->where('title', 'like', "%{$s}%"))
            ->orderByDesc('is_pinned')
            ->latest('published_at')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('kabar.announcements.index', [
            'announcements' => $announcements,
            'filters' => $request->only('search'),
        ]);
    }

    public function create(): View
    {
        return view('kabar.announcements.create', [
            'announcement' => new Announcement(['audience' => Announcement::AUDIENCE_ALL, 'published_at' => now()]),
            'departments' => Department::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $announcement = Announcement::query()->create([
            ...$data,
            'created_by' => $request->user()->id,
            ...$this->attachmentColumns($request),
        ]);

        if ($announcement->published_at && $announcement->published_at->lessThanOrEqualTo(now())) {
            app(ApprovalNotifier::class)->announcementPublished($announcement);
        }

        return redirect()->route('kabar.announcements.show', $announcement)->with('status', 'Pengumuman diterbitkan.');
    }

    /** Detail + daftar yang sudah dan belum membaca. */
    public function show(Announcement $announcement): View
    {
        $reads = $announcement->reads()->with('employee.department')->latest('read_at')->get();
        $readIds = $reads->pluck('employee_id')->all();

        return view('kabar.announcements.show', [
            'announcement' => $announcement->load(['creator', 'department']),
            'reads' => $reads,
            'unread' => $announcement->audienceQuery()->whereNotIn('id', $readIds ?: [0])->with('department')->orderBy('full_name')->get(),
            'audienceCount' => $announcement->audienceQuery()->count(),
        ]);
    }

    public function edit(Announcement $announcement): View
    {
        return view('kabar.announcements.edit', [
            'announcement' => $announcement,
            'departments' => Department::query()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Announcement $announcement): RedirectResponse
    {
        $data = $this->validated($request);
        $columns = $this->attachmentColumns($request);

        if (($columns || $request->boolean('remove_attachment')) && $announcement->hasAttachment()) {
            Storage::disk(Announcement::ATTACHMENT_DISK)->delete($announcement->attachment_path);
            $announcement->fill(['attachment_path' => null, 'attachment_name' => null, 'attachment_mime' => null, 'attachment_size' => null]);
        }

        $wasPublished = $announcement->published_at && $announcement->published_at->lessThanOrEqualTo(now());
        $announcement->update([...$data, ...$columns]);

        if (! $wasPublished && $announcement->published_at && $announcement->published_at->lessThanOrEqualTo(now())) {
            app(ApprovalNotifier::class)->announcementPublished($announcement);
        }

        return redirect()->route('kabar.announcements.show', $announcement)->with('status', 'Pengumuman diperbarui.');
    }

    public function destroy(Announcement $announcement): RedirectResponse
    {
        $announcement->delete();

        return redirect()->route('kabar.announcements.index')->with('status', 'Pengumuman dihapus.');
    }

    public function attachment(Announcement $announcement): StreamedResponse
    {
        abort_unless($announcement->hasAttachment(), 404);

        return PrivateFile::response(Storage::disk(Announcement::ATTACHMENT_DISK), $announcement->attachment_path, $announcement->attachment_name, $announcement->attachment_mime);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:10000'],
            'audience' => ['required', Rule::in([Announcement::AUDIENCE_ALL, Announcement::AUDIENCE_DEPARTMENT])],
            'department_id' => ['nullable', 'required_if:audience,department', 'integer', 'exists:departments,id'],
            'published_at' => ['nullable', 'date'],
            'is_pinned' => ['nullable', 'boolean'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:'.(Announcement::ATTACHMENT_MAX_MB * 1024)],
        ], [
            ...UploadMessages::attachment('attachment', Announcement::ATTACHMENT_MAX_MB),
            'department_id.required_if' => 'Pilih divisi tujuan pengumuman.',
        ], [
            'title' => 'judul', 'body' => 'isi', 'audience' => 'penerima', 'department_id' => 'divisi',
            'published_at' => 'waktu terbit', 'attachment' => 'lampiran',
        ]);

        return [
            'title' => $data['title'],
            'body' => $data['body'],
            'audience' => $data['audience'],
            'department_id' => $data['audience'] === Announcement::AUDIENCE_DEPARTMENT ? $data['department_id'] : null,
            'published_at' => $data['published_at'] ?? now(),
            'is_pinned' => $request->boolean('is_pinned'),
        ];
    }

    /** @return array<string, mixed> */
    private function attachmentColumns(Request $request): array
    {
        $file = $request->file('attachment');

        if (! $file) {
            return [];
        }

        return [
            'attachment_path' => $file->store('announcement-attachments', Announcement::ATTACHMENT_DISK),
            'attachment_name' => $file->getClientOriginalName(),
            'attachment_mime' => PrivateFile::detectedMime($file),
            'attachment_size' => $file->getSize(),
        ];
    }
}
