/**
 * Penjaga berkas sisi klien untuk setiap input unggahan.
 *
 * Dulu tiap layar memeriksa berkasnya sendiri-sendiri dan semuanya bersandar pada
 * `file.type` saja:
 *
 *     if (file.type !== 'application/pdf') tolak();
 *
 * Itu keliru. `file.type` diisi browser dari pemetaan ekstensi milik sistem
 * operasi, bukan dari isi berkasnya — di Windows nilainya kosong bila kunci
 * "Content Type" untuk .pdf tidak ada di registry, dan sejumlah berkas yang
 * datang dari WhatsApp, hasil pindai, atau berbagi jaringan sampai sebagai
 * "application/octet-stream". PDF yang sah pun ditolak sebelum sempat dikirim,
 * dan pesannya justru mengatakan berkasnya bukan PDF.
 *
 * Di sini ekstensi diperiksa lebih dulu, MIME jadi pelengkap, dan keduanya
 * dibaca dari atribut `accept` yang memang sudah ada di input — jadi tidak ada
 * daftar jenis berkas kedua yang harus dijaga tetap sinkron. Penentu akhirnya
 * tetap server: aturan `mimes:` Laravel menebak jenis dari ISI berkas, sehingga
 * melonggarkan pemeriksaan di sini tidak melonggarkan keamanannya.
 */

const humanSize = (bytes) => (bytes >= 1048576
    ? `${(bytes / 1048576).toFixed(1)} MB`
    : `${Math.max(1, Math.round(bytes / 1024))} KB`);

/**
 * Pisahkan atribut accept menjadi daftar ekstensi dan daftar MIME.
 * Contoh: ".pdf,application/pdf" -> { extensions: ['pdf'], mimes: ['application/pdf'] }
 */
const parseAccept = (accept) => {
    const extensions = [];
    const mimes = [];

    (accept || '').split(',').map((part) => part.trim().toLowerCase()).filter(Boolean).forEach((part) => {
        if (part.startsWith('.')) {
            extensions.push(part.slice(1));
        } else if (part.includes('/')) {
            mimes.push(part);
        }
    });

    return { extensions, mimes };
};

/** Nama jenis yang bisa dibaca orang, untuk kalimat "yang diterima: ...". */
const describeAccepted = (extensions, mimes) => {
    if (extensions.length) {
        return extensions.map((extension) => `.${extension}`).join(', ');
    }

    return mimes.join(', ') || 'semua jenis berkas';
};

const extensionOf = (name) => {
    const dot = name.lastIndexOf('.');

    return dot > -1 ? name.slice(dot + 1).toLowerCase() : '';
};

/**
 * Cocok bila ekstensinya diterima ATAU MIME-nya diterima. Pola "image/*" ikut
 * ditangani karena beberapa input memakainya.
 */
const isAccepted = (file, { extensions, mimes }) => {
    if (!extensions.length && !mimes.length) {
        return true;
    }

    if (extensions.includes(extensionOf(file.name))) {
        return true;
    }

    const type = (file.type || '').toLowerCase();

    if (!type) {
        return false; // ekstensinya sudah tidak cocok dan tidak ada petunjuk lain
    }

    return mimes.some((mime) => (mime.endsWith('/*')
        ? type.startsWith(mime.slice(0, -1))
        : mime === type));
};

/**
 * Modal kesalahan berkas: satu untuk seluruh halaman, dibuat saat pertama
 * dibutuhkan agar tidak menambah markup pada halaman yang tak punya unggahan.
 */
const errorDialog = (() => {
    let root = null;
    let titleEl = null;
    let messageEl = null;
    let detailEl = null;
    let lastFocused = null;

    const close = () => {
        if (!root) return;
        root.hidden = true;
        lastFocused?.focus?.();
    };

    const build = () => {
        root = document.createElement('div');
        root.className = 'fixed inset-0 z-50 flex items-center justify-center bg-gray-950/50 p-4';
        root.setAttribute('data-file-error-modal', '');
        root.hidden = true;
        root.innerHTML = `
            <div class="w-full max-w-md overflow-hidden rounded-lg border border-gray-200 bg-white shadow-xl" role="alertdialog" aria-modal="true" aria-labelledby="file-error-title">
                <div class="flex items-start gap-3 border-b border-gray-100 p-5">
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-red-50 text-lg font-semibold text-red-600" aria-hidden="true">!</span>
                    <div class="min-w-0 flex-1">
                        <h2 id="file-error-title" class="text-base font-semibold text-gray-950" data-file-error-title>Berkas tidak bisa dipakai</h2>
                        <p class="mt-1 text-sm text-gray-600" data-file-error-message></p>
                    </div>
                    <button type="button" data-file-error-close class="-m-1 rounded-md p-1 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600" aria-label="Tutup">&times;</button>
                </div>
                <dl class="grid grid-cols-3 gap-x-3 gap-y-1.5 bg-gray-50 px-5 py-4 text-sm" data-file-error-detail></dl>
                <div class="flex justify-end p-5 pt-4">
                    <button type="button" data-file-error-close class="rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-xs transition hover:bg-primary-hover">Mengerti</button>
                </div>
            </div>`;

        document.body.append(root);

        titleEl = root.querySelector('[data-file-error-title]');
        messageEl = root.querySelector('[data-file-error-message]');
        detailEl = root.querySelector('[data-file-error-detail]');

        root.querySelectorAll('[data-file-error-close]').forEach((button) => button.addEventListener('click', close));
        root.addEventListener('click', (event) => { if (event.target === root) close(); });
        document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && !root.hidden) close(); });
    };

    return {
        open({ title, message, details = [] }) {
            if (!root) build();

            lastFocused = document.activeElement;
            titleEl.textContent = title;
            messageEl.textContent = message;
            detailEl.innerHTML = '';
            detailEl.hidden = details.length === 0;

            details.forEach(([label, value]) => {
                const term = document.createElement('dt');
                term.className = 'col-span-1 text-gray-500';
                term.textContent = label;

                const description = document.createElement('dd');
                description.className = 'col-span-2 break-words font-medium text-gray-900';
                description.textContent = value;

                detailEl.append(term, description);
            });

            root.hidden = false;
            root.querySelector('[data-file-error-close]')?.focus();
        },
    };
})();

/**
 * Periksa satu berkas terhadap aturan yang menempel pada input-nya.
 *
 * @returns {null|{title: string, message: string, details: Array}} null bila lolos
 */
const inspect = (input, file) => {
    const label = input.dataset.fileLabel || 'Berkas';
    const accepted = parseAccept(input.getAttribute('accept'));
    const maxMb = Number(input.dataset.maxMb || 0);
    const detected = file.type || 'tidak terdeteksi';

    const details = [
        ['Nama berkas', file.name],
        ['Ukuran', humanSize(file.size)],
        ['Jenis terbaca', detected],
    ];

    if (!isAccepted(file, accepted)) {
        return {
            title: `${label} tidak sesuai`,
            message: `Jenis berkas ini tidak diterima. Pilih berkas dengan format ${describeAccepted(accepted.extensions, accepted.mimes)}.`,
            details,
        };
    }

    if (maxMb > 0 && file.size > maxMb * 1024 * 1024) {
        return {
            title: `${label} terlalu besar`,
            message: `Ukuran maksimal ${maxMb} MB, sedangkan berkas ini ${humanSize(file.size)}. Perkecil dulu berkasnya, lalu coba lagi.`,
            details,
        };
    }

    return null;
};

/**
 * Pasang penjaga pada seluruh input unggahan di halaman. Pemasangannya lewat
 * delegasi di document, jadi input yang baru muncul (mis. di dalam modal yang
 * dirender belakangan) ikut terjaga tanpa pemasangan ulang.
 */
export default function initFileGuard() {
    document.addEventListener('change', (event) => {
        const input = event.target;

        if (!(input instanceof HTMLInputElement) || input.type !== 'file' || !('fileGuard' in input.dataset)) {
            return;
        }

        const inline = document.querySelector(`[data-upload-error-for="${input.name}"]`);
        inline?.classList.add('hidden');

        const file = input.files?.[0];

        if (!file) {
            return;
        }

        const problem = inspect(input, file);

        if (!problem) {
            return;
        }

        // Berkasnya dibuang agar formulir tidak bisa terkirim membawa berkas yang
        // sudah pasti ditolak server.
        input.value = '';
        input.dispatchEvent(new CustomEvent('file-guard:rejected', { bubbles: true, detail: problem }));
        errorDialog.open(problem);
    });

    // Penolakan dari server (berkas sudah terkirim lalu ditolak) ditampilkan di
    // modal yang sama, supaya pengguna tidak perlu mencari teks merah kecil di
    // tengah formulir panjang.
    const serverErrors = [...document.querySelectorAll('[data-upload-error]')]
        .map((element) => element.textContent.trim())
        .filter(Boolean);

    if (serverErrors.length) {
        errorDialog.open({
            title: 'Unggahan ditolak',
            message: serverErrors.join(' '),
            details: [],
        });
    }
}

export { errorDialog };
