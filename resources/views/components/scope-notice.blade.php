@props(['hasNoScope' => false, 'hasNoTeam' => false])

{{-- Halaman kosong tanpa penjelasan terbaca sebagai aplikasi rusak. Dipakai bersama
     oleh Absensi Harian, Jadwal Kerja, Cuti & seluruh halaman Laporan — semuanya
     dipersempit ke garis atasan oleh saklar yang sama di Kontrol Akses, jadi
     keterangannya pun harus sama. --}}
@if ($hasNoScope)
    <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        Cakupan akses Anda belum diatur, jadi belum ada data yang bisa ditampilkan. Minta admin menetapkan lokasi kerja / divisi Anda di menu <span class="font-medium">Kontrol Akses</span>.
    </div>
@elseif ($hasNoTeam)
    <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        Halaman ini hanya menampilkan karyawan yang menjadi bawahan Anda di struktur organisasi, dan saat ini belum ada seorang pun yang tercatat di bawah Anda. Minta admin mengatur atasan langsung karyawan, atau mengubah <span class="font-medium">&ldquo;Cakupan di Absensi Harian, Jadwal Kerja, Cuti &amp; Laporan&rdquo;</span> untuk akun Anda menjadi <span class="font-medium">&ldquo;Sesuai lokasi &amp; divisi di kartu ini&rdquo;</span> di menu <span class="font-medium">Kontrol Akses</span>.
    </div>
@endif
