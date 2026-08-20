// Hanma Fitness Parking System - main.js

document.addEventListener('DOMContentLoaded', function () {
    // Toggle sidebar (mobile)
    const toggleBtn = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.hanma-sidebar');
    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', function () {
            sidebar.classList.toggle('show');
        });
    }

    // Konfirmasi hapus data
    document.querySelectorAll('.btn-hapus-konfirmasi').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            if (!confirm('Yakin ingin menghapus data ini? Tindakan tidak dapat dibatalkan.')) {
                e.preventDefault();
            }
        });
    });

    // Auto hitung tarif berdasarkan jenis kendaraan pada form transaksi
    const selectJenis = document.getElementById('jenis_kendaraan_select');
    const tarifInfo = document.getElementById('info_tarif');
    if (selectJenis && tarifInfo) {
        selectJenis.addEventListener('change', function () {
            const opt = selectJenis.options[selectJenis.selectedIndex];
            const tarif = opt.getAttribute('data-tarif');
            tarifInfo.innerText = tarif ? ('Tarif: Rp ' + Number(tarif).toLocaleString('id-ID') + ' / jam') : '';
        });
    }

    // Auto dismiss alert setelah 4 detik
    document.querySelectorAll('.alert-auto-dismiss').forEach(function (el) {
        setTimeout(function () {
            el.classList.remove('show');
            el.classList.add('fade');
            setTimeout(() => el.remove(), 400);
        }, 4000);
    });
});

function cetakStruk() {
    window.print();
}

// ============================================================
// FIX: Fallback manual untuk modal Bootstrap
// Jalan hanya jika Bootstrap JS (bootstrap.bundle.min.js) gagal
// menangani modal karena alasan apa pun.
// ============================================================
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-bs-toggle="modal"]').forEach(function (trigger) {
        trigger.addEventListener('click', function () {
            var targetSelector = trigger.getAttribute('data-bs-target');
            var modalEl = document.querySelector(targetSelector);
            if (!modalEl) return;

            // Jika Bootstrap Modal tersedia, biarkan Bootstrap yang menangani
            if (window.bootstrap && window.bootstrap.Modal) {
                return;
            }

            // ---- Fallback manual ----
            modalEl.style.display = 'block';
            modalEl.classList.add('show');
            document.body.classList.add('modal-open');

            var backdrop = document.createElement('div');
            backdrop.className = 'modal-backdrop fade show';
            backdrop.setAttribute('data-manual-backdrop', 'true');
            document.body.appendChild(backdrop);

            function closeModal() {
                modalEl.style.display = 'none';
                modalEl.classList.remove('show');
                document.body.classList.remove('modal-open');
                var bd = document.querySelector('[data-manual-backdrop="true"]');
                if (bd) bd.remove();
            }

            modalEl.querySelectorAll('[data-bs-dismiss="modal"]').forEach(function (btn) {
                btn.addEventListener('click', closeModal, { once: true });
            });
            backdrop.addEventListener('click', closeModal, { once: true });
        });
    });
});