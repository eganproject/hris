<?php

namespace App\Http\Controllers\Api\Mobile\Hr;

use App\Actions\Assets\AcknowledgeAssignment;
use App\Exceptions\AssetCustodyException;
use App\Models\AssetAssignment;
use App\Support\Mobile\Present;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Aset saya" di aplikasi HP: sama dengan menu web Aset Saya. Hanya aset milik
 * penggunanya sendiri, plus konfirmasi penerimaan oleh pemegangnya.
 */
class AssetController extends HrController
{
    public function index(): JsonResponse
    {
        $assignments = $this->employee()->assetAssignments()
            ->with(['asset:id,asset_code,name,brand,model,serial_number,category_id', 'asset.category:id,name', 'assignedBy:id,name'])
            ->latest('assigned_at')
            ->get();

        $open = $assignments->filter(fn (AssetAssignment $a) => $a->isOpen())->values();

        return $this->ok([
            'open' => $open->map(fn (AssetAssignment $a) => $this->row($a))->values(),
            'history' => $assignments->reject(fn (AssetAssignment $a) => $a->isOpen())->map(fn (AssetAssignment $a) => $this->row($a))->values(),
            'counts' => [
                'open' => $open->count(),
                'unacknowledged' => $open->reject(fn (AssetAssignment $a) => $a->isAcknowledged())->count(),
                'overdue' => $open->filter(fn (AssetAssignment $a) => $a->isOverdue())->count(),
            ],
        ]);
    }

    public function show(AssetAssignment $assignment): JsonResponse
    {
        $this->authorizeOwner($assignment);
        $assignment->loadMissing(['asset.category', 'assignedBy:id,name', 'returnedTo:id,name']);

        return $this->ok($this->row($assignment));
    }

    public function acknowledge(Request $request, AssetAssignment $assignment, AcknowledgeAssignment $action): JsonResponse
    {
        // Hanya pemegangnya sendiri — pengakuan dari pihak lain tidak bernilai.
        $this->authorizeOwner($assignment);

        $data = $request->validate(['note' => ['nullable', 'string', 'max:500']], [], ['note' => 'catatan']);

        try {
            $updated = $action->handle($assignment, $request->user(), $data['note'] ?? null);
        } catch (AssetCustodyException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return $this->message('Terima kasih, penerimaan aset sudah dikonfirmasi.', $this->row($updated->fresh(['asset.category', 'assignedBy:id,name'])));
    }

    private function authorizeOwner(AssetAssignment $assignment): void
    {
        abort_unless($assignment->employee_id === $this->employee()->id, 403, 'Aset ini tidak tercatat atas nama Anda.');
    }

    /** @return array<string, mixed> */
    private function row(AssetAssignment $a): array
    {
        $asset = $a->asset;
        $state = match (true) {
            ! $a->isOpen() => 'returned',
            ! $a->isAcknowledged() => 'unacknowledged',
            $a->isOverdue() => 'overdue',
            default => 'held',
        };

        return [
            'id' => $a->id,
            'state' => $state, // unacknowledged | held | overdue | returned
            'asset' => [
                'id' => $asset?->id,
                'code' => $asset?->asset_code,
                'name' => $asset?->name,
                'brand' => $asset?->brand,
                'model' => $asset?->model,
                'serial_number' => $asset?->serial_number,
                'category' => $asset?->category?->name,
            ],
            'assigned_at' => $a->assigned_at?->toIso8601String(),
            'assigned_by' => $a->assignedBy?->name,
            'expected_return_at' => Present::date($a->expected_return_at),
            'condition_out' => $a->condition_out_label,
            'purpose' => $a->purpose,
            'notes' => $a->notes,
            'acknowledged_at' => $a->acknowledged_at?->toIso8601String(),
            'acknowledgement_note' => $a->acknowledgement_note,
            'returned_at' => $a->returned_at?->toIso8601String(),
            'condition_in' => $a->condition_in_label,
        ];
    }
}
