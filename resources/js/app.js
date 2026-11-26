document.addEventListener('DOMContentLoaded', () => {

    const drawer = document.getElementById('mobile-nav-drawer');
    const openBtn = document.getElementById('mobile-nav-open');
    const closeBtns = document.querySelectorAll('[data-close-drawer]');

    const setDrawer = (open) => {
        if (!drawer) return;
        drawer.classList.toggle('hidden', !open);
        document.body.classList.toggle('overflow-hidden', open);
        if (open) {
            const firstLink = drawer.querySelector('a, button');
            firstLink?.focus();
        } else {
            openBtn?.focus();
        }
    };

    openBtn?.addEventListener('click', () => setDrawer(true));
    closeBtns.forEach((btn) => btn.addEventListener('click', () => setDrawer(false)));
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && drawer && !drawer.classList.contains('hidden')) {
            setDrawer(false);
        }
    });

    const confirmDialog = document.getElementById('abc-confirm-dialog');
    const confirmTitle = document.getElementById('abc-confirm-title');
    const confirmMessage = document.getElementById('abc-confirm-message');
    const confirmAccept = document.getElementById('abc-confirm-accept');
    const confirmCancel = document.getElementById('abc-confirm-cancel');
    let pendingForm = null;

    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (e) => {
            if (form.dataset.confirmed === 'true') {
                return;
            }
            e.preventDefault();
            pendingForm = form;
            if (confirmDialog && confirmTitle && confirmMessage) {
                confirmTitle.textContent = form.dataset.confirmTitle || 'Confirm Action';
                confirmMessage.textContent = form.dataset.confirm || 'Are you sure you want to proceed?';
                confirmDialog.showModal();
                confirmCancel?.focus();
            } else if (window.confirm(form.dataset.confirm)) {
                form.dataset.confirmed = 'true';
                form.submit();
            }
        });
    });

    confirmCancel?.addEventListener('click', () => {
        pendingForm = null;
        confirmDialog?.close();
    });

    confirmAccept?.addEventListener('click', () => {
        if (pendingForm) {
            const target = pendingForm;
            pendingForm = null;
            confirmDialog?.close();
            target.dataset.confirmed = 'true';
            target.submit();
        }
    });

    document.querySelectorAll('[data-print-receipt]').forEach((btn) => {
        btn.addEventListener('click', () => window.print());
    });

    const receiptPaper = document.getElementById('receipt-paper');
    document.querySelectorAll('[data-receipt-layout]').forEach((btn) => {
        btn.addEventListener('click', () => {
            if (!receiptPaper) return;
            const mode = btn.dataset.receiptLayout;
            receiptPaper.classList.toggle('receipt-a4', mode === 'a4');
            document.querySelectorAll('[data-receipt-layout]').forEach((other) => {
                other.setAttribute('aria-pressed', other === btn ? 'true' : 'false');
                other.classList.toggle('abc-btn-primary', other === btn);
                other.classList.toggle('abc-btn-secondary', other !== btn);
            });
        });
    });
});
