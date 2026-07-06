<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->text('description')->nullable();
            $table->timestamp('occurred_at');
            $table->foreignId('causer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('properties')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'occurred_at']);
        });

        $this->backfill();
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_events');
    }

    /**
     * Seed a starting timeline from data that already exists, so the history isn't
     * empty for employees created before this feature.
     */
    private function backfill(): void
    {
        $now = now();

        foreach (DB::table('employees')->orderBy('id')->get() as $employee) {
            $rows = [];

            $push = function (string $type, ?string $description, $occurredAt) use (&$rows, $employee, $now) {
                if (! $occurredAt) {
                    return;
                }

                $rows[] = [
                    'employee_id' => $employee->id,
                    'type' => $type,
                    'description' => $description,
                    'occurred_at' => $occurredAt,
                    'causer_id' => null,
                    'properties' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            };

            $push('joined', 'Bergabung sebagai karyawan.', $employee->join_date ?? null);

            $contracts = DB::table('employee_contracts')
                ->where('employee_id', $employee->id)
                ->orderBy('start_date')
                ->get();

            foreach ($contracts as $contract) {
                $push('contract_created', 'Kontrak '.$contract->contract_number.' ('.$contract->contract_type.') dibuat.', $contract->start_date);
            }

            if (($employee->employment_status ?? null) === 'inactive') {
                $push('exited', 'Karyawan keluar'.($employee->exit_reason ? ' ('.$employee->exit_reason.')' : '').'.', $employee->exit_date ?? $employee->join_date);
            }

            if ($rows !== []) {
                DB::table('employee_events')->insert($rows);
            }
        }
    }
};
