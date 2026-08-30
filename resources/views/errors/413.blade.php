{{--
    Berkas terlalu besar untuk diterima server.

    Halaman ini sengaja berdiri sendiri, tidak memakai <x-layouts.app>. Pemeriksaan
    ukuran dilakukan middleware GLOBAL ValidatePostSize, yang berjalan sebelum sesi
    dimulai — tata letak aplikasi memerlukan sesi dan pengguna yang login, dan akan
    ikut gagal di sini. Karena alasan yang sama, pesannya tidak bisa dititipkan ke
    flash session: ia harus tampil langsung di halaman ini.
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Berkas Terlalu Besar - {{ config('app.name', 'HRIS') }}</title>
    <style>
        :root { color-scheme: light; }
        body {
            margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
            background: #f8fafc; color: #0f172a; padding: 24px;
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
        }
        .card {
            max-width: 30rem; width: 100%; background: #fff; border: 1px solid #e2e8f0;
            border-radius: 12px; padding: 32px; box-shadow: 0 1px 3px rgb(15 23 42 / 8%);
        }
        .badge {
            display: inline-flex; align-items: center; gap: 6px; background: #fef3c7; color: #92400e;
            border-radius: 999px; padding: 4px 12px; font-size: 12px; font-weight: 600;
        }
        h1 { font-size: 20px; margin: 16px 0 8px; }
        p { margin: 0 0 12px; font-size: 14px; line-height: 1.6; color: #475569; }
        ul { margin: 0 0 20px; padding-left: 20px; font-size: 14px; line-height: 1.7; color: #475569; }
        .actions { display: flex; gap: 8px; flex-wrap: wrap; }
        a, button {
            display: inline-block; border-radius: 8px; padding: 10px 18px; font-size: 14px;
            font-weight: 600; text-decoration: none; cursor: pointer; border: 1px solid transparent;
        }
        .primary { background: #1e293b; color: #fff; }
        .secondary { background: #fff; color: #334155; border-color: #cbd5e1; }
    </style>
</head>
<body>
    <div class="card">
        <span class="badge">413 &middot; Berkas terlalu besar</span>
        <h1>Unggahan Anda melebihi batas server</h1>
        <p>
            Berkas yang Anda kirim lebih besar daripada yang bisa diterima server
            (batas saat ini <strong>{{ ini_get('post_max_size') ?: 'tidak diketahui' }}</strong>),
            jadi permintaannya ditolak sebelum aplikasi sempat memeriksanya.
        </p>
        <ul>
            <li>Foto profil &amp; foto karyawan: maksimal <strong>2 MB</strong>.</li>
            <li>Impor Excel (jadwal &amp; karyawan): maksimal <strong>10 MB</strong>.</li>
        </ul>
        <p>Perkecil ukuran berkasnya, lalu coba unggah lagi.</p>
        <div class="actions">
            <button type="button" class="primary" onclick="history.back()">Kembali</button>
            <a class="secondary" href="{{ url('/') }}">Ke Beranda</a>
        </div>
    </div>
</body>
</html>
