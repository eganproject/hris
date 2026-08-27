<?php

use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/** Payload kontrak minimal untuk formulir karyawan; nomornya dibuat unik per pemanggil. */
function contractFormPayload(array $master, string $name, string $number, array $extra = []): array
{
    return array_merge([
        'branch_id' => $master['branch']->id,
        'department_id' => $master['department']->id,
        'job_position_id' => $master['position']->id,
        'machine_pins' => [['device_id' => null, 'machine_user_id' => (string) random_int(1000, 999999)]],
        'full_name' => $name,
        'join_date' => '2026-07-05',
        'employment_status' => 'active',
        'contract_number' => $number,
        'contract_type' => 'PKWT',
        'contract_start_date' => '2026-07-05',
        'contract_end_date' => '2027-07-04',
        'contract_status' => 'active',
    ], $extra);
}

function contractPdf(string $name = 'kontrak.pdf', int $kilobytes = 300): UploadedFile
{
    return UploadedFile::fake()->create($name, $kilobytes, 'application/pdf');
}

test('both the add and edit pages offer the upload, and never demand it', function () {
    Storage::fake('local');

    $user = employeeManager();
    $master = hrMasterData();

    $expectOptionalField = function ($response) {
        $html = $response->assertOk()->getContent();

        // Ada input berkasnya, hanya menerima PDF, dan tidak pernah bertanda wajib.
        expect($html)->toContain('name="contract_document"')
            ->and($html)->toContain('accept=".pdf,application/pdf"');

        // Potong markup di sekitar input itu untuk memastikan tidak ada atribut
        // required yang menempel padanya.
        $field = substr($html, max(0, strpos($html, 'name="contract_document"') - 400), 800);

        expect($field)->not->toContain('required');
    };

    $expectOptionalField($this->actingAs($user)->get('/employees/create'));

    $this->actingAs($user)
        ->post('/employees', contractFormPayload($master, 'Berkontrak', 'CTR-DOC-FORM'))
        ->assertRedirect('/employees');

    $employee = Employee::query()->where('full_name', 'Berkontrak')->firstOrFail();

    $expectOptionalField($this->actingAs($user)->get("/employees/{$employee->id}/edit"));
});

test('every renew surface offers the upload too, not just the employee form', function () {
    Storage::fake('local');

    $user = employeeManager();
    $master = hrMasterData();

    $this->actingAs($user)
        ->post('/employees', contractFormPayload($master, 'Berkontrak', 'CTR-DOC-SURF'))
        ->assertRedirect('/employees');

    $employee = Employee::query()->where('full_name', 'Berkontrak')->firstOrFail();

    // Panel "Perpanjang Kontrak" di halaman detail, dan modal perpanjang di daftar
    // karyawan — keduanya membuat kontrak baru, jadi keduanya harus bisa melampirkan.
    $this->actingAs($user)->get("/employees/{$employee->id}")
        ->assertOk()
        ->assertSee('name="contract_document"', escape: false);

    $this->actingAs($user)->get('/employees')
        ->assertOk()
        ->assertSee('name="contract_document"', escape: false);
});

test('renewing from the employee list attaches the document as well', function () {
    Storage::fake('local');

    $user = employeeManager();
    $master = hrMasterData();

    $this->actingAs($user)
        ->post('/employees', contractFormPayload($master, 'Berkontrak', 'CTR-DOC-LIST'))
        ->assertRedirect('/employees');

    $employee = Employee::query()->where('full_name', 'Berkontrak')->firstOrFail();

    $this->actingAs($user)
        ->post("/employees/{$employee->id}/renew-contract", [
            'from_list' => '1',
            'contract_number' => 'CTR-DOC-LIST-R',
            'contract_type' => 'PKWT',
            'start_date' => '2027-07-05',
            'end_date' => '2028-07-04',
            'contract_document' => contractPdf('dari-daftar.pdf'),
        ])
        ->assertRedirect('/employees');

    $new = EmployeeContract::query()->where('contract_number', 'CTR-DOC-LIST-R')->firstOrFail();

    expect($new->document_name)->toBe('dari-daftar.pdf');

    Storage::disk('local')->assertExists($new->document_path);
});

test('a rejected document reopens the renew modal instead of vanishing silently', function () {
    Storage::fake('local');

    $user = employeeManager();
    $master = hrMasterData();

    $this->actingAs($user)
        ->post('/employees', contractFormPayload($master, 'Berkontrak', 'CTR-DOC-REJ'))
        ->assertRedirect('/employees');

    $employee = Employee::query()->where('full_name', 'Berkontrak')->firstOrFail();

    $this->actingAs($user)
        ->post("/employees/{$employee->id}/renew-contract", [
            'from_list' => '1',
            'contract_number' => 'CTR-DOC-REJ-R',
            'contract_type' => 'PKWT',
            'start_date' => '2027-07-05',
            'end_date' => '2028-07-04',
            'contract_document' => UploadedFile::fake()->image('bukan-pdf.jpg'),
        ])
        ->assertSessionHasErrors('contract_document')
        // Tanpa flash ini modalnya tertutup dan galatnya tak pernah terlihat.
        ->assertSessionHas('renew_employee');

    expect(EmployeeContract::query()->where('contract_number', 'CTR-DOC-REJ-R')->exists())->toBeFalse();
});

test('bulk renew accepts a different document per employee', function () {
    Storage::fake('local');

    $user = employeeManager();
    $master = hrMasterData();

    foreach (['A', 'B'] as $suffix) {
        $this->actingAs($user)
            ->post('/employees', contractFormPayload($master, "Karyawan {$suffix}", "CTR-BULK-{$suffix}"))
            ->assertRedirect('/employees');
    }

    $a = Employee::query()->where('full_name', 'Karyawan A')->firstOrFail();
    $b = Employee::query()->where('full_name', 'Karyawan B')->firstOrFail();

    $this->actingAs($user)
        ->post('/employees/bulk/renew', [
            'entries' => [
                [
                    'employee_id' => $a->id, 'contract_number' => 'CTR-BULK-A-R',
                    'contract_type' => 'PKWT', 'start_date' => '2027-07-05', 'end_date' => '2028-07-04',
                    'contract_document' => contractPdf('punya-a.pdf'),
                ],
                [
                    'employee_id' => $b->id, 'contract_number' => 'CTR-BULK-B-R',
                    'contract_type' => 'PKWT', 'start_date' => '2027-07-05', 'end_date' => '2028-07-04',
                    'contract_document' => contractPdf('punya-b.pdf'),
                ],
            ],
        ])
        ->assertRedirect();

    $newA = EmployeeContract::query()->where('contract_number', 'CTR-BULK-A-R')->firstOrFail();
    $newB = EmployeeContract::query()->where('contract_number', 'CTR-BULK-B-R')->firstOrFail();

    // Tiap karyawan memegang berkasnya sendiri — tidak tertukar dan tidak berbagi.
    expect($newA->document_name)->toBe('punya-a.pdf')
        ->and($newB->document_name)->toBe('punya-b.pdf')
        ->and($newA->document_path)->not->toBe($newB->document_path);

    Storage::disk('local')->assertExists($newA->document_path);
    Storage::disk('local')->assertExists($newB->document_path);
});

test('bulk renew still works for entries that carry no document', function () {
    Storage::fake('local');

    $user = employeeManager();
    $master = hrMasterData();

    foreach (['C', 'D'] as $suffix) {
        $this->actingAs($user)
            ->post('/employees', contractFormPayload($master, "Karyawan {$suffix}", "CTR-BULK-{$suffix}"))
            ->assertRedirect('/employees');
    }

    $c = Employee::query()->where('full_name', 'Karyawan C')->firstOrFail();
    $d = Employee::query()->where('full_name', 'Karyawan D')->firstOrFail();

    // Campuran: satu melampirkan, satu tidak. Keduanya harus tetap diproses.
    $this->actingAs($user)
        ->post('/employees/bulk/renew', [
            'entries' => [
                [
                    'employee_id' => $c->id, 'contract_number' => 'CTR-BULK-C-R',
                    'contract_type' => 'PKWT', 'start_date' => '2027-07-05', 'end_date' => '2028-07-04',
                    'contract_document' => contractPdf('punya-c.pdf'),
                ],
                [
                    'employee_id' => $d->id, 'contract_number' => 'CTR-BULK-D-R',
                    'contract_type' => 'PKWT', 'start_date' => '2027-07-05', 'end_date' => '2028-07-04',
                ],
            ],
        ])
        ->assertRedirect();

    expect(EmployeeContract::query()->where('contract_number', 'CTR-BULK-C-R')->firstOrFail()->hasDocument())->toBeTrue()
        ->and(EmployeeContract::query()->where('contract_number', 'CTR-BULK-D-R')->firstOrFail()->hasDocument())->toBeFalse();
});

test('bulk renew rejects a non-PDF and saves no contract at all', function () {
    Storage::fake('local');

    $user = employeeManager();
    $master = hrMasterData();

    $this->actingAs($user)
        ->post('/employees', contractFormPayload($master, 'Karyawan E', 'CTR-BULK-E'))
        ->assertRedirect('/employees');

    $e = Employee::query()->where('full_name', 'Karyawan E')->firstOrFail();

    $this->actingAs($user)
        ->post('/employees/bulk/renew', [
            'entries' => [[
                'employee_id' => $e->id, 'contract_number' => 'CTR-BULK-E-R',
                'contract_type' => 'PKWT', 'start_date' => '2027-07-05', 'end_date' => '2028-07-04',
                'contract_document' => UploadedFile::fake()->image('bukan-pdf.jpg'),
            ]],
        ])
        ->assertSessionHas('bulk_error');

    expect(EmployeeContract::query()->where('contract_number', 'CTR-BULK-E-R')->exists())->toBeFalse();
});

test('a contract document can be attached when the employee is created', function () {
    Storage::fake('local');

    $user = employeeManager();
    $master = hrMasterData();

    $this->actingAs($user)
        ->post('/employees', contractFormPayload($master, 'Berkontrak', 'CTR-DOC-1', [
            'contract_document' => contractPdf(),
        ]))
        ->assertRedirect('/employees');

    $contract = EmployeeContract::query()->where('contract_number', 'CTR-DOC-1')->firstOrFail();

    expect($contract->hasDocument())->toBeTrue()
        ->and($contract->document_name)->toBe('kontrak.pdf')
        ->and($contract->document_mime)->toBe('application/pdf');

    Storage::disk('local')->assertExists($contract->document_path);
});

test('a contract without a document stays valid, exactly as before', function () {
    Storage::fake('local');

    $user = employeeManager();
    $master = hrMasterData();

    $this->actingAs($user)
        ->post('/employees', contractFormPayload($master, 'Tanpa Dokumen', 'CTR-DOC-2'))
        ->assertRedirect('/employees');

    $contract = EmployeeContract::query()->where('contract_number', 'CTR-DOC-2')->firstOrFail();

    expect($contract->hasDocument())->toBeFalse()
        ->and($contract->document_path)->toBeNull();
});

test('only PDF is accepted, and only up to the size limit', function () {
    Storage::fake('local');

    $user = employeeManager();
    $master = hrMasterData();

    $this->actingAs($user)
        ->post('/employees', contractFormPayload($master, 'Salah Jenis', 'CTR-DOC-3', [
            'contract_document' => UploadedFile::fake()->image('kontrak.jpg'),
        ]))
        ->assertSessionHasErrors('contract_document');

    $this->actingAs($user)
        ->post('/employees', contractFormPayload($master, 'Terlalu Besar', 'CTR-DOC-4', [
            // Sedikit di atas batas 5 MB.
            'contract_document' => contractPdf('besar.pdf', EmployeeContract::DOCUMENT_MAX_MB * 1024 + 100),
        ]))
        ->assertSessionHasErrors('contract_document');

    expect(EmployeeContract::query()->whereIn('contract_number', ['CTR-DOC-3', 'CTR-DOC-4'])->count())->toBe(0);
});

test('saving the form again without picking a file keeps the stored document', function () {
    Storage::fake('local');

    $user = employeeManager();
    $master = hrMasterData();

    $this->actingAs($user)
        ->post('/employees', contractFormPayload($master, 'Berkontrak', 'CTR-DOC-5', [
            'contract_document' => contractPdf('asli.pdf'),
        ]))
        ->assertRedirect('/employees');

    $employee = Employee::query()->where('full_name', 'Berkontrak')->firstOrFail();
    $before = $employee->contracts()->firstOrFail();

    // Menyimpan ulang tanpa memilih berkas tidak boleh menghapus yang sudah ada.
    $this->actingAs($user)
        ->put("/employees/{$employee->id}", contractFormPayload($master, 'Berkontrak', 'CTR-DOC-5'))
        ->assertRedirect();

    $after = $employee->contracts()->firstOrFail();

    expect($after->document_path)->toBe($before->document_path)
        ->and($after->document_name)->toBe('asli.pdf');

    Storage::disk('local')->assertExists($after->document_path);
});

test('uploading a replacement removes the file it replaces', function () {
    Storage::fake('local');

    $user = employeeManager();
    $master = hrMasterData();

    $this->actingAs($user)
        ->post('/employees', contractFormPayload($master, 'Berkontrak', 'CTR-DOC-6', [
            'contract_document' => contractPdf('lama.pdf'),
        ]))
        ->assertRedirect('/employees');

    $employee = Employee::query()->where('full_name', 'Berkontrak')->firstOrFail();
    $oldPath = $employee->contracts()->firstOrFail()->document_path;

    $this->actingAs($user)
        ->put("/employees/{$employee->id}", contractFormPayload($master, 'Berkontrak', 'CTR-DOC-6', [
            'contract_document' => contractPdf('baru.pdf'),
        ]))
        ->assertRedirect();

    $contract = $employee->contracts()->firstOrFail();

    expect($contract->document_name)->toBe('baru.pdf');

    Storage::disk('local')->assertExists($contract->document_path);
    Storage::disk('local')->assertMissing($oldPath);
});

test('ticking remove clears the document and its file', function () {
    Storage::fake('local');

    $user = employeeManager();
    $master = hrMasterData();

    $this->actingAs($user)
        ->post('/employees', contractFormPayload($master, 'Berkontrak', 'CTR-DOC-7', [
            'contract_document' => contractPdf(),
        ]))
        ->assertRedirect('/employees');

    $employee = Employee::query()->where('full_name', 'Berkontrak')->firstOrFail();
    $path = $employee->contracts()->firstOrFail()->document_path;

    $this->actingAs($user)
        ->put("/employees/{$employee->id}", contractFormPayload($master, 'Berkontrak', 'CTR-DOC-7', [
            'contract_document_remove' => '1',
        ]))
        ->assertRedirect();

    $contract = $employee->contracts()->firstOrFail();

    expect($contract->hasDocument())->toBeFalse()
        ->and($contract->document_name)->toBeNull();

    Storage::disk('local')->assertMissing($path);
});

test('renewing a contract attaches the document to the new contract, not the old one', function () {
    Storage::fake('local');

    $user = employeeManager();
    $master = hrMasterData();

    $this->actingAs($user)
        ->post('/employees', contractFormPayload($master, 'Berkontrak', 'CTR-DOC-8', [
            'contract_document' => contractPdf('kontrak-lama.pdf'),
        ]))
        ->assertRedirect('/employees');

    $employee = Employee::query()->where('full_name', 'Berkontrak')->firstOrFail();

    $this->actingAs($user)
        ->post("/employees/{$employee->id}/renew-contract", [
            'contract_number' => 'CTR-DOC-8-R',
            'contract_type' => 'PKWT',
            'start_date' => '2027-07-05',
            'end_date' => '2028-07-04',
            'contract_document' => contractPdf('kontrak-baru.pdf'),
        ])
        ->assertRedirect();

    $old = EmployeeContract::query()->where('contract_number', 'CTR-DOC-8')->firstOrFail();
    $new = EmployeeContract::query()->where('contract_number', 'CTR-DOC-8-R')->firstOrFail();

    // Riwayat tetap utuh: tiap kontrak memegang dokumennya sendiri.
    expect($old->document_name)->toBe('kontrak-lama.pdf')
        ->and($new->document_name)->toBe('kontrak-baru.pdf')
        ->and($new->document_path)->not->toBe($old->document_path);
});

test('the document is served through the authorized route and closed to outsiders', function () {
    Storage::fake('local');

    $user = employeeManager();
    $master = hrMasterData();

    $this->actingAs($user)
        ->post('/employees', contractFormPayload($master, 'Berkontrak', 'CTR-DOC-9', [
            'contract_document' => contractPdf(),
        ]))
        ->assertRedirect('/employees');

    $contract = EmployeeContract::query()->where('contract_number', 'CTR-DOC-9')->firstOrFail();
    $url = "/employees/contracts/{$contract->id}/document";

    $this->actingAs($user)->get($url)->assertOk();

    // Pengguna tanpa hak apa pun dan tanpa kaitan ke karyawannya: ditolak.
    $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
});

test('an unauthenticated visitor is sent to login instead of the file', function () {
    Storage::fake('local');

    // Dibangun langsung tanpa actingAs: sekali sebuah tes login, status itu melekat
    // untuk seluruh permintaan berikutnya di tes yang sama.
    $employee = Employee::query()->create(['full_name' => 'Berkontrak', 'employment_status' => 'active']);
    $contract = $employee->contracts()->create([
        'contract_number' => 'CTR-DOC-GUEST', 'contract_type' => 'PKWT',
        'start_date' => '2026-07-05', 'end_date' => '2027-07-04', 'status' => 'active',
        'document_path' => 'contract-documents/rahasia.pdf',
        'document_name' => 'rahasia.pdf', 'document_mime' => 'application/pdf', 'document_size' => 1024,
    ]);

    Storage::disk('local')->put('contract-documents/rahasia.pdf', 'dummy');

    $this->get("/employees/contracts/{$contract->id}/document")->assertRedirect('/login');
});

test('the employee themself can open their own contract document', function () {
    Storage::fake('local');

    $user = employeeManager();
    $master = hrMasterData();

    $this->actingAs($user)
        ->post('/employees', contractFormPayload($master, 'Berkontrak', 'CTR-DOC-10', [
            'contract_document' => contractPdf(),
        ]))
        ->assertRedirect('/employees');

    $contract = EmployeeContract::query()->where('contract_number', 'CTR-DOC-10')->firstOrFail();

    $own = User::factory()->create();
    $contract->employee->update(['user_id' => $own->id]);

    $this->actingAs($own)
        ->get("/employees/contracts/{$contract->id}/document")
        ->assertOk();
});

test('deleting a contract row also removes its stored file', function () {
    Storage::fake('local');

    $user = employeeManager();
    $master = hrMasterData();

    $this->actingAs($user)
        ->post('/employees', contractFormPayload($master, 'Berkontrak', 'CTR-DOC-11', [
            'contract_document' => contractPdf(),
        ]))
        ->assertRedirect('/employees');

    $employee = Employee::query()->where('full_name', 'Berkontrak')->firstOrFail();

    // Kontrak hanya boleh dihapus bila bukan satu-satunya milik karyawan itu.
    $spare = $employee->contracts()->create([
        'contract_number' => 'CTR-DOC-11-B', 'contract_type' => 'PKWT',
        'start_date' => '2028-07-05', 'end_date' => '2029-07-04', 'status' => 'active',
    ]);

    $target = EmployeeContract::query()->where('contract_number', 'CTR-DOC-11')->firstOrFail();
    $target->forceFill(['status' => 'renewed'])->save();

    $path = $target->document_path;

    $this->actingAs($user)->delete("/employees/contracts/{$target->id}")->assertRedirect();

    expect(EmployeeContract::query()->whereKey($target->id)->exists())->toBeFalse()
        ->and($spare->fresh())->not->toBeNull();

    Storage::disk('local')->assertMissing($path);
});
