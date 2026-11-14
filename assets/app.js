/* Koperasi — interaksi UI dasar (tanpa dependensi) */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var sidebar = document.getElementById('sidebar');
        var overlay = document.getElementById('sidebarOverlay');
        var toggle = document.getElementById('menuToggle');

        function closeSidebar() {
            if (!sidebar) {
                return;
            }

            sidebar.classList.remove('open');
            if (overlay) {
                overlay.classList.remove('show');
            }
            if (toggle) {
                toggle.setAttribute('aria-expanded', 'false');
            }
            document.body.classList.remove('nav-open');
        }

        function openSidebar() {
            if (!sidebar) {
                return;
            }

            sidebar.classList.add('open');
            if (overlay) {
                overlay.classList.add('show');
            }
            if (toggle) {
                toggle.setAttribute('aria-expanded', 'true');
            }
            document.body.classList.add('nav-open');
        }

        if (toggle && sidebar) {
            toggle.addEventListener('click', function () {
                if (sidebar.classList.contains('open')) {
                    closeSidebar();
                } else {
                    openSidebar();
                }
            });
        }

        if (overlay) {
            overlay.addEventListener('click', closeSidebar);
        }

        if (sidebar) {
            sidebar.querySelectorAll('.nav-link').forEach(function (link) {
                link.addEventListener('click', function () {
                    if (window.matchMedia('(max-width: 1100px)').matches) {
                        closeSidebar();
                    }
                });
            });
        }

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeSidebar();
            }
        });

        window.addEventListener('resize', function () {
            if (window.innerWidth > 1100) {
                closeSidebar();
            }
        });

        /* Konfirmasi untuk form yang memiliki data-confirm. */
        document.querySelectorAll('form[data-confirm]').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                var message = form.getAttribute('data-confirm') || 'Yakin melanjutkan aksi ini?';
                if (!window.confirm(message)) {
                    event.preventDefault();
                }
            });
        });

        /* Tombol cetak halaman. */
        document.querySelectorAll('[data-print]').forEach(function (button) {
            button.addEventListener('click', function () {
                window.print();
            });
        });

        /* Alert hilang otomatis, kecuali alert yang berisi daftar error. */
        document.querySelectorAll('.alert').forEach(function (alert) {
            if (alert.querySelector('ul')) {
                return;
            }

            window.setTimeout(function () {
                alert.classList.add('is-hiding');
                window.setTimeout(function () {
                    alert.remove();
                }, 400);
            }, 6000);
        });
    });
})();
