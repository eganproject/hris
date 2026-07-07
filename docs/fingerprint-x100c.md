# Integrasi Mesin Fingerprint Solution X100-C (ZKTeco iclock/ADMS)

Sistem menerima data absensi langsung dari mesin lewat **protokol iclock (ADMS push)**.
Mesin dikonfigurasi menembak ke domain HRIS, dan setiap tap sidik jari dikirim ke
endpoint kita, disimpan sebagai *punch* mentah, lalu dirangkum menjadi absensi harian.

## Alur data

```
[X100-C] --HTTPS iclock--> /iclock/cdata --> attendance_punches (mentah, idempoten)
                                                     |
                                          map PIN -> karyawan
                                                     |
                                     AttendanceRollup -> AttendanceResolver
                                                     |
                                               attendances (status harian)
```

## 1. Daftarkan perangkat di HRIS

Menu **Attendance → Perangkat Absensi → Tambah Perangkat**:

- **Serial Number (SN)** — harus sama persis dengan SN mesin (Menu → Sistem/Info).
  SN ini adalah *allowlist*: hanya SN terdaftar & aktif yang dilayani.
- **Nama**, **Lokasi** (opsional), **Zona waktu** (default `Asia/Jakarta`).

## 2. Konfigurasi mesin X100-C

Di mesin: **Menu → Comm (Komunikasi) → Cloud Server / ADMS**:

| Pengaturan          | Nilai                                             |
|---------------------|---------------------------------------------------|
| Server Mode / ADMS  | Aktif (ON)                                         |
| Server Address      | domain HRIS Anda (mis. `hris.perusahaan.com`)     |
| Server Port         | `443` (HTTPS) atau `80` (HTTP)                     |
| Enable Proxy        | Off                                               |
| HTTPS / SSL         | Aktifkan bila firmware mendukung (disarankan)     |

Simpan lalu **restart** mesin. Pastikan mesin punya akses internet.
Cek **Perangkat Absensi → kolom "Terakhir Aktif"**; akan terisi setelah mesin handshake.

## 3. Petakan PIN karyawan

Setiap karyawan yang di-enroll punya **PIN** di mesin. Hubungkan ke data karyawan:

- **Perangkat Absensi → Edit & PIN** → tambah `PIN → Karyawan`, atau
- **Log Punch** → panel "PIN Belum Dipetakan" → pilih karyawan (punch lama otomatis dicocokkan ulang).

## 4. Cara kerja rollup

- Punch pertama hari itu = jam masuk, terakhir = jam pulang.
- Shift lintas malam ditangani otomatis (tap 06:00 dini hari diatribusikan ke hari kerja sebelumnya).
- Absensi dihitung ulang setiap punch masuk; aman diproses berulang (idempoten via `dedup_hash`).
- Punch dengan PIN belum terpetakan tetap disimpan (status *unmatched*) — tidak ada data hilang.

## Keamanan

- **Wajib HTTPS** di produksi — protokol iclock tidak mengirim token auth, jadi
  proteksi dari: (a) allowlist SN, (b) TLS, (c) opsional IP allowlist di reverse proxy.
- Endpoint `/iclock/*` dikecualikan dari CSRF (device tidak bisa kirim token) dan
  digating oleh SN di controller.
- Pertimbangkan membatasi akses `/iclock/*` hanya dari IP kantor bila IP publik statis.

## Endpoint (referensi)

| Method | Path                  | Fungsi                              |
|--------|-----------------------|-------------------------------------|
| GET    | `/iclock/cdata?SN=`   | Handshake — mesin ambil opsi        |
| POST   | `/iclock/cdata?SN=&table=ATTLOG` | Push log absensi (tab-separated) |
| GET    | `/iclock/getrequest?SN=` | Polling perintah (belum dipakai) |
| POST   | `/iclock/devicecmd?SN=`  | Laporan hasil perintah           |

> Catatan: bila firmware X100-C Anda ternyata **tidak** punya menu Cloud Server/ADMS,
> alternatifnya pakai *bridge* (agen di LAN yang menarik data via protokol ZK `:4370`
> dengan `pyzk` lalu POST ke endpoint yang sama). Arsitektur ingestion tidak berubah.
