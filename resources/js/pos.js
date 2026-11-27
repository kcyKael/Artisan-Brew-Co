import { bindThermalPrinterControls, cleanupThermalPrinter, playThermalPrinter } from './thermal-printer';

function formatPhp(centavos) {
    const abs = Math.abs(centavos);
    const amount = (abs / 100).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
    return (centavos < 0 ? '-' : '') + '₱' + amount;
}

function toCentavos(value) {
    const num = Number.parseFloat(String(value || '0').trim());
    if (!Number.isFinite(num) || num < 0) return 0;
    return Math.round(num * 100);
}

function formatPaymentMethodLabel(method) {
    if (method === 'gcash') return 'GCash';
    if (method === 'card') return 'Card';
    return 'Cash';
}

document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('pos-app');
    if (!root) return;

    const catalog = JSON.parse(root.dataset.catalog || '[]');
    const checkoutUrl = root.dataset.checkoutUrl;
    const customerSearchUrl = root.dataset.customerSearchUrl;
    const giftCardLookupUrl = root.dataset.giftCardLookupUrl;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

    let checkoutToken = root.dataset.checkoutToken;
    let activeCategory = 'all';
    let searchTerm = '';
    let orderType = 'dine_in';
    let paymentMethod = 'cash';
    let selectedPayOption = 'exact';
    let discountType = 'none';
    let verifiedGiftCard = null;
    let editingNoteIndex = null;
    let cart = [];
    let isSubmitting = false;

    const productMap = new Map(catalog.map((p) => [p.id, p]));

    const searchInput = document.getElementById('pos-search');
    const categoryButtons = document.querySelectorAll('[data-pos-category]');
    const productCards = document.querySelectorAll('[data-pos-product]');
    const emptyCatalog = document.getElementById('pos-empty-catalog');

    const orderTypeButtons = Array.from(document.querySelectorAll('[data-order-type]'));
    const paymentMethodButtons = Array.from(document.querySelectorAll('[data-payment-method]'));
    const discountTypeButtons = Array.from(document.querySelectorAll('[data-discount-type]'));

    const cartLinesEl = document.getElementById('pos-cart-lines');
    const cartEmptyEl = document.getElementById('pos-cart-empty');
    const cartLoadingEl = document.getElementById('pos-cart-loading');
    const browseMenuBtn = document.getElementById('pos-browse-menu-btn');
    const cartCountBadge = document.getElementById('pos-cart-count');
    const mobileCartCount = document.getElementById('pos-mobile-cart-count');
    const mobileCartTotal = document.getElementById('pos-mobile-cart-total');

    const customerSelect = document.getElementById('pos-customer-id');
    const customerFilterInput = document.getElementById('pos-customer-search');
    const cupNameInput = document.getElementById('pos-cup-name');
    const orderNoteInput = document.getElementById('pos-order-note');

    const discountValueWrap = document.getElementById('pos-discount-value-wrap');
    const discountValueInput = document.getElementById('pos-discount-value');
    const giftCardWrap = document.getElementById('pos-gift-card-wrap');
    const giftCardCodeInput = document.getElementById('pos-gift-card-code');
    const giftCardLookupBtn = document.getElementById('pos-gift-card-lookup-btn');
    const giftCardFeedback = document.getElementById('pos-gift-card-feedback');

    const cashSection = document.getElementById('pos-cash-section');
    const referenceSection = document.getElementById('pos-reference-section');
    const paymentRefInput = document.getElementById('pos-payment-reference');
    const payCards = Array.from(document.querySelectorAll('[data-pay-option]'));
    const cashInput = document.getElementById('pos-cash-received');
    const changeEl = document.getElementById('pos-change');

    const subtotalEl = document.getElementById('pos-subtotal');
    const discountRow = document.getElementById('pos-discount-row');
    const discountAmountEl = document.getElementById('pos-discount-amount');
    const totalEl = document.getElementById('pos-total');

    const submitBtn = document.getElementById('pos-checkout-btn');
    const checkoutLabel = document.getElementById('pos-checkout-label');
    const clearBtn = document.getElementById('pos-clear-cart-btn');
    const errorBox = document.getElementById('pos-error-box');
    const errorMsg = document.getElementById('pos-error-msg');
    const orderPanel = document.getElementById('pos-order-panel');
    const sheetBackdrop = document.getElementById('pos-mobile-sheet-backdrop');
    const mobileCartToggle = document.getElementById('pos-mobile-cart-toggle');
    const mobileCartClose = document.getElementById('pos-mobile-cart-close');

    const configDialog = document.getElementById('pos-config-dialog');
    const configTitle = document.getElementById('pos-config-title');
    const configBasePrice = document.getElementById('pos-config-base');
    const configStockNote = document.getElementById('pos-config-stock');
    const configVariantsWrap = document.getElementById('pos-config-variants-wrap');
    const configVariantsList = document.getElementById('pos-config-variants');
    const configAddonsWrap = document.getElementById('pos-config-addons-wrap');
    const configAddonsList = document.getElementById('pos-config-addons');
    const configQuickNotes = Array.from(document.querySelectorAll('[data-config-quick-note]'));
    const configNoteInput = document.getElementById('pos-config-note');
    const configQtyInput = document.getElementById('pos-config-qty');
    const configUnitPreview = document.getElementById('pos-config-unit-preview');
    const configAddBtn = document.getElementById('pos-config-add-btn');
    const configCancelBtn = document.getElementById('pos-config-cancel');

    const receiptDialog = document.getElementById('pos-receipt-dialog');
    const successOrderNum = document.getElementById('pos-success-order-num');
    const successTotal = document.getElementById('pos-success-total');
    const successMethod = document.getElementById('pos-success-method');
    const successRefRow = document.getElementById('pos-success-ref-row');
    const successRef = document.getElementById('pos-success-ref');
    const successCashRow = document.getElementById('pos-success-cash-row');
    const successCash = document.getElementById('pos-success-cash');
    const successChangeRow = document.getElementById('pos-success-change-row');
    const successChange = document.getElementById('pos-success-change');
    const successDetailLink = document.getElementById('pos-success-detail-link');
    const liveReceiptWrap = document.getElementById('pos-live-receipt-wrap');
    const receiptPrintLink = document.getElementById('pos-receipt-print-link');
    const receiptNewOrderBtn = document.getElementById('pos-receipt-new-order');
    const receiptEmailInput = document.getElementById('pos-receipt-email');
    const marketingOptInInput = document.getElementById('pos-marketing-opt-in');
    const successEmailStatus = document.getElementById('pos-success-email-status');
    const dialogEmailForm = document.getElementById('pos-dialog-email-form');
    const dialogEmailInput = document.getElementById('pos-dialog-receipt-email');
    const dialogMarketingOptIn = document.getElementById('pos-dialog-marketing-opt-in');
    const dialogEmailSubmit = document.getElementById('pos-dialog-email-submit');
    let lastEmailReceiptUrl = null;

    let configuringProduct = null;

    function showError(message) {
        if (!errorBox || !errorMsg) return;
        errorMsg.textContent = message;
        errorBox.classList.remove('hidden');
    }

    function clearError() {
        errorBox?.classList.add('hidden');
    }

    function tickCta() {
        if (!checkoutLabel) return;
        checkoutLabel.classList.remove('abc-cta-tick');
        void checkoutLabel.offsetWidth;
        checkoutLabel.classList.add('abc-cta-tick');
    }

    function setMobileSheet(open) {
        if (!orderPanel) return;
        orderPanel.classList.toggle('hidden', !open);
        orderPanel.classList.toggle('abc-bottom-sheet', open);
        sheetBackdrop?.classList.toggle('hidden', !open);
    }

    function styleSegmentedButtons(buttons, activeValue, dataKey) {
        buttons.forEach((btn) => {
            const active = btn.dataset[dataKey] === activeValue;
            btn.setAttribute('aria-pressed', active ? 'true' : 'false');
            btn.classList.toggle('bg-[#D2B077]', active);
            btn.classList.toggle('text-[#111110]', active);
            btn.classList.toggle('font-semibold', active);
            btn.classList.toggle('text-[#BFB4A5]', !active);
            btn.classList.toggle('font-medium', !active);
        });
    }

    function applyOrderType(type) {
        orderType = type === 'take_out' ? 'take_out' : 'dine_in';
        styleSegmentedButtons(orderTypeButtons, orderType, 'orderType');
    }

    function applyPaymentMethod(method) {
        paymentMethod = ['cash', 'card', 'gcash'].includes(method) ? method : 'cash';
        styleSegmentedButtons(paymentMethodButtons, paymentMethod, 'paymentMethod');
        cashSection?.classList.toggle('hidden', paymentMethod !== 'cash');
        referenceSection?.classList.toggle('hidden', paymentMethod === 'cash');
        clearError();
        updatePaymentSummary(false);
    }

    function applyDiscountType(type) {
        discountType = ['none', 'percent', 'fixed', 'giftcard'].includes(type) ? type : 'none';
        styleSegmentedButtons(discountTypeButtons, discountType, 'discountType');

        const showValue = discountType === 'percent' || discountType === 'fixed';
        discountValueWrap?.classList.toggle('hidden', !showValue);
        if (discountValueInput) {
            discountValueInput.placeholder = discountType === 'percent' ? 'Discount % (1 - 100)' : 'Discount amount (₱)';
            discountValueInput.max = discountType === 'percent' ? '100' : '';
        }

        giftCardWrap?.classList.toggle('hidden', discountType !== 'giftcard');
        if (discountType !== 'giftcard') {
            verifiedGiftCard = null;
            if (giftCardFeedback) {
                giftCardFeedback.classList.add('hidden');
                giftCardFeedback.textContent = '';
            }
        }

        clearError();
        updatePaymentSummary(true);
    }

    function applyPayOption(option, focusCustom = false) {
        selectedPayOption = option;
        payCards.forEach((card) => {
            const active = card.dataset.payOption === option;
            card.setAttribute('aria-checked', active ? 'true' : 'false');
            card.setAttribute('tabindex', active ? '0' : '-1');
            card.classList.toggle('border-[#D2B077]', active);
            card.classList.toggle('bg-[#D2B077]/15', active);
            card.classList.toggle('text-[#F4EEE5]', active);
            card.classList.toggle('font-semibold', active);
            card.classList.toggle('border-white/10', !active);
            card.classList.toggle('bg-[#28241F]', !active);
            card.classList.toggle('text-[#BFB4A5]', !active);
            card.classList.toggle('font-medium', !active);
        });

        const { totalCentavos } = computeTotals();
        if (cashInput) {
            if (option === 'exact') {
                cashInput.value = totalCentavos > 0 ? (totalCentavos / 100).toFixed(2) : '';
            } else if (option === '200' || option === '500') {
                cashInput.value = Number(option).toFixed(2);
            } else if (option === 'custom' && focusCustom) {
                cashInput.focus();
                cashInput.select();
            }
        }

        updatePaymentSummary(true);
    }

    orderTypeButtons.forEach((btn) => {
        btn.addEventListener('click', () => applyOrderType(btn.dataset.orderType));
    });

    paymentMethodButtons.forEach((btn) => {
        btn.addEventListener('click', () => applyPaymentMethod(btn.dataset.paymentMethod));
    });

    discountTypeButtons.forEach((btn) => {
        btn.addEventListener('click', () => applyDiscountType(btn.dataset.discountType));
    });

    discountValueInput?.addEventListener('input', () => updatePaymentSummary(false));

    async function lookupGiftCard() {
        if (!giftCardLookupUrl || !giftCardCodeInput || !giftCardFeedback) return;
        const code = giftCardCodeInput.value.trim().toUpperCase();
        giftCardCodeInput.value = code;
        if (!code) {
            verifiedGiftCard = null;
            giftCardFeedback.classList.remove('hidden');
            giftCardFeedback.className = 'text-xs text-[#E07A7A]';
            giftCardFeedback.textContent = 'Enter a gift card code first.';
            updatePaymentSummary(false);
            return;
        }

        giftCardFeedback.classList.remove('hidden');
        giftCardFeedback.className = 'text-xs text-[#BFB4A5]';
        giftCardFeedback.textContent = 'Checking gift card...';

        try {
            const res = await fetch(`${giftCardLookupUrl}?code=${encodeURIComponent(code)}`, {
                headers: { Accept: 'application/json' },
            });
            const data = await res.json();
            if (res.ok && data.valid) {
                verifiedGiftCard = data;
                giftCardFeedback.className = 'text-xs text-[#9CAF94]';
                giftCardFeedback.textContent = `Valid · Balance: ${data.remaining_formatted}`;
            } else {
                verifiedGiftCard = null;
                giftCardFeedback.className = 'text-xs text-[#E07A7A]';
                giftCardFeedback.textContent = 'Invalid, expired, or zero-balance gift card.';
            }
        } catch {
            verifiedGiftCard = null;
            giftCardFeedback.className = 'text-xs text-[#E07A7A]';
            giftCardFeedback.textContent = 'Unable to verify gift card right now.';
        }

        updatePaymentSummary(true);
    }

    giftCardLookupBtn?.addEventListener('click', lookupGiftCard);
    giftCardCodeInput?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            lookupGiftCard();
        }
    });
    giftCardCodeInput?.addEventListener('input', () => {
        if (verifiedGiftCard && verifiedGiftCard.code !== giftCardCodeInput.value.trim().toUpperCase()) {
            verifiedGiftCard = null;
            updatePaymentSummary(false);
        }
    });

    payCards.forEach((card, index) => {
        card.addEventListener('click', () => {
            applyPayOption(card.dataset.payOption || 'exact', true);
        });

        card.addEventListener('keydown', (e) => {
            let nextIndex = null;
            if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
                nextIndex = (index + 1) % payCards.length;
            } else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
                nextIndex = (index - 1 + payCards.length) % payCards.length;
            }
            if (nextIndex !== null) {
                e.preventDefault();
                const target = payCards[nextIndex];
                target.focus();
                applyPayOption(target.dataset.payOption || 'exact', false);
            }
        });
    });

    function filterCatalog() {
        const q = searchTerm.trim().toLowerCase();
        let visibleCount = 0;

        productCards.forEach((card) => {
            const cat = card.dataset.categoryId || '';
            const name = (card.dataset.name || '').toLowerCase();
            const sku = (card.dataset.sku || '').toLowerCase();

            const matchCategory = activeCategory === 'all' || cat === activeCategory;
            const matchSearch = q === '' || name.includes(q) || sku.includes(q);
            const show = matchCategory && matchSearch;

            card.classList.toggle('hidden', !show);
            if (show) visibleCount += 1;
        });

        emptyCatalog?.classList.toggle('hidden', visibleCount > 0);
    }

    function updateProductStockBadges(stocks) {
        Object.entries(stocks || {}).forEach(([idStr, newStock]) => {
            const pid = Number(idStr);
            const product = productMap.get(pid);
            if (product) {
                product.stock_quantity = Number(newStock);
            }

            const card = document.querySelector(`[data-pos-product][data-product-id="${pid}"]`);
            if (!card) return;
            const badge = card.querySelector('[data-stock-badge]');
            const addBtn = card.querySelector('[data-add-product]');
            if (badge) {
                if (newStock <= 0) {
                    badge.textContent = 'Sold out';
                    badge.className = 'text-xs font-medium px-2 py-0.5 rounded bg-[rgba(224,122,122,0.16)] text-[#E07A7A]';
                } else if (product && newStock <= product.low_stock_threshold) {
                    badge.textContent = `Low stock (${newStock})`;
                    badge.className = 'text-xs font-medium px-2 py-0.5 rounded bg-[rgba(224,168,91,0.16)] text-[#E0A85B] tabular-nums';
                } else {
                    badge.textContent = `Available (${newStock})`;
                    badge.className = 'text-xs font-medium px-2 py-0.5 rounded bg-[rgba(156,175,148,0.16)] text-[#9CAF94] tabular-nums';
                }
            }
            if (addBtn) {
                addBtn.disabled = newStock <= 0;
            }
        });
    }

    function cartTotalForProduct(productId) {
        return cart
            .filter((item) => item.product_id === productId)
            .reduce((sum, item) => sum + item.quantity, 0);
    }

    function computeTotals() {
        let subtotalCentavos = 0;
        let totalQty = 0;

        cart.forEach((line) => {
            subtotalCentavos += line.unit_centavos * line.quantity;
            totalQty += line.quantity;
        });

        let discountCentavos = 0;
        if (subtotalCentavos > 0) {
            if (discountType === 'percent') {
                const pct = Number.parseFloat(discountValueInput?.value || '0');
                if (Number.isFinite(pct) && pct > 0 && pct <= 100) {
                    discountCentavos = Math.min(
                        subtotalCentavos,
                        Math.floor((subtotalCentavos * Math.round(pct * 100)) / 10000)
                    );
                }
            } else if (discountType === 'fixed') {
                const fixedCentavos = toCentavos(discountValueInput?.value);
                if (fixedCentavos > 0) {
                    discountCentavos = Math.min(subtotalCentavos, fixedCentavos);
                }
            } else if (discountType === 'giftcard' && verifiedGiftCard?.valid) {
                const remaining = toCentavos(verifiedGiftCard.remaining_balance);
                if (remaining > 0) {
                    discountCentavos = Math.min(subtotalCentavos, remaining);
                }
            }
        }

        const totalCentavos = Math.max(0, subtotalCentavos - discountCentavos);

        return { subtotalCentavos, discountCentavos, totalCentavos, totalQty };
    }

    function updatePaymentSummary(animateTick = false) {
        const { subtotalCentavos, discountCentavos, totalCentavos, totalQty } = computeTotals();
        if (paymentMethod === 'cash' && selectedPayOption === 'exact' && cashInput) {
            cashInput.value = totalCentavos > 0 ? (totalCentavos / 100).toFixed(2) : '';
        }

        const cashCentavos = toCentavos(cashInput?.value);
        const changeCentavos = cashCentavos - totalCentavos;

        if (subtotalEl) subtotalEl.textContent = formatPhp(subtotalCentavos);
        if (discountRow && discountAmountEl) {
            discountRow.classList.toggle('hidden', discountCentavos <= 0);
            discountAmountEl.textContent = `-${formatPhp(discountCentavos)}`;
        }
        if (totalEl) totalEl.textContent = formatPhp(totalCentavos);
        if (cartCountBadge) cartCountBadge.textContent = String(totalQty);
        if (mobileCartCount) mobileCartCount.textContent = String(totalQty);
        if (mobileCartTotal) mobileCartTotal.textContent = formatPhp(totalCentavos);

        if (changeEl) {
            if (totalCentavos === 0) {
                changeEl.textContent = '₱0.00';
                changeEl.className = 'text-xs tabular-nums font-semibold text-[#BFB4A5]';
            } else if (changeCentavos < 0) {
                changeEl.textContent = `Short ${formatPhp(Math.abs(changeCentavos))}`;
                changeEl.className = 'text-xs tabular-nums font-semibold text-[#E07A7A]';
            } else {
                changeEl.textContent = formatPhp(changeCentavos);
                changeEl.className = 'text-xs tabular-nums font-semibold text-[#22A85B]';
            }
        }

        if (checkoutLabel && !isSubmitting) {
            checkoutLabel.textContent = `Complete Sale · ${formatPhp(totalCentavos)}`;
            if (animateTick) tickCta();
        }

        if (submitBtn) {
            const insufficientCash = paymentMethod === 'cash' && cashCentavos < totalCentavos;
            submitBtn.disabled = isSubmitting || cart.length === 0 || insufficientCash;
        }
    }

    function createPlaceholderSvg() {
        const wrap = document.createElement('div');
        wrap.className = 'h-full w-full flex items-center justify-center bg-gradient-to-br from-[#28241F] to-[#1A1714] text-[#D2B077]/80';
        wrap.setAttribute('aria-hidden', 'true');
        wrap.innerHTML = '<svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 8h1a4 4 0 1 1 0 8h-1"/><path d="M3 8h14v9a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4Z"/><line x1="6" y1="2" x2="6" y2="4"/><line x1="10" y1="2" x2="10" y2="4"/><line x1="14" y1="2" x2="14" y2="4"/></svg>';
        return wrap;
    }

    function renderCart() {
        if (!cartLinesEl || !cartEmptyEl) return;

        cartLinesEl.innerHTML = '';
        cartEmptyEl.classList.toggle('hidden', cart.length > 0);
        clearBtn?.classList.toggle('hidden', cart.length === 0);

        cart.forEach((line, idx) => {
            const product = productMap.get(line.product_id);
            const maxStock = product ? product.stock_quantity : 999;
            const lineTotalCentavos = line.unit_centavos * line.quantity;

            const row = document.createElement('div');
            row.className = 'abc-surface p-3 flex items-start gap-3';
            row.dataset.cartLine = String(idx);

            const thumb = document.createElement('div');
            thumb.className = 'w-12 h-12 rounded-lg bg-[#161412] border border-white/10 overflow-hidden shrink-0 flex items-center justify-center';
            if (line.image_url) {
                const img = document.createElement('img');
                img.src = line.image_url;
                img.alt = line.name;
                img.loading = 'lazy';
                img.className = 'w-full h-full object-cover';
                thumb.appendChild(img);
            } else {
                thumb.appendChild(createPlaceholderSvg());
            }

            const body = document.createElement('div');
            body.className = 'flex-1 min-w-0 space-y-2';

            const topRow = document.createElement('div');
            topRow.className = 'flex items-start justify-between gap-2';

            const info = document.createElement('div');
            info.className = 'min-w-0';

            const title = document.createElement('div');
            title.className = 'font-medium text-[15px] text-[#F4EEE5] leading-snug';
            title.textContent = line.name;
            info.appendChild(title);

            if (line.variant_label) {
                const vLabel = document.createElement('div');
                vLabel.className = 'text-xs text-[#BFB4A5] mt-0.5';
                vLabel.textContent = `• ${line.variant_label}`;
                info.appendChild(vLabel);
            }

            line.addon_names.forEach((addonName) => {
                const aLabel = document.createElement('div');
                aLabel.className = 'text-xs text-[#BFB4A5] mt-0.5';
                aLabel.textContent = `+ ${addonName}`;
                info.appendChild(aLabel);
            });

            if (editingNoteIndex === idx) {
                const noteForm = document.createElement('div');
                noteForm.className = 'flex items-center gap-1.5 mt-1.5';

                const noteInput = document.createElement('input');
                noteInput.type = 'text';
                noteInput.maxLength = 120;
                noteInput.value = line.note || '';
                noteInput.placeholder = 'Item note (e.g., Less Ice)';
                noteInput.className = 'abc-input text-xs py-1 px-2';

                const saveNote = () => {
                    line.note = noteInput.value.trim().slice(0, 120) || null;
                    editingNoteIndex = null;
                    renderCart();
                };

                noteInput.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        saveNote();
                    } else if (e.key === 'Escape') {
                        editingNoteIndex = null;
                        renderCart();
                    }
                });

                const saveBtn = document.createElement('button');
                saveBtn.type = 'button';
                saveBtn.className = 'text-xs font-semibold text-[#D2B077] hover:text-[#DFC08B] px-2 py-1 rounded bg-[#161412] border border-white/10 shrink-0';
                saveBtn.textContent = 'Save';
                saveBtn.addEventListener('click', saveNote);

                noteForm.append(noteInput, saveBtn);
                info.appendChild(noteForm);
                window.setTimeout(() => noteInput.focus(), 0);
            } else {
                const noteWrap = document.createElement('div');
                noteWrap.className = 'flex flex-wrap items-center gap-1.5 mt-1';

                if (line.note) {
                    const noteTag = document.createElement('span');
                    noteTag.className = 'inline-block text-[11px] px-2 py-0.5 rounded bg-[#D2B077]/15 text-[#D2B077]';
                    noteTag.textContent = line.note;
                    noteWrap.appendChild(noteTag);
                }

                const noteBtn = document.createElement('button');
                noteBtn.type = 'button';
                noteBtn.className = 'text-[11px] text-[#BFB4A5] hover:text-[#D2B077] underline-offset-2 hover:underline';
                noteBtn.textContent = line.note ? 'Edit note' : '+ Note';
                noteBtn.addEventListener('click', () => {
                    editingNoteIndex = idx;
                    renderCart();
                });

                noteWrap.appendChild(noteBtn);
                info.appendChild(noteWrap);
            }

            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'text-xs text-[#BFB4A5] hover:text-[#E07A7A] px-1.5 py-0.5 rounded shrink-0';
            removeBtn.setAttribute('aria-label', `Remove ${line.name}`);
            removeBtn.textContent = 'Remove';
            removeBtn.addEventListener('click', () => {
                editingNoteIndex = null;
                cart.splice(idx, 1);
                clearError();
                renderCart();
            });

            topRow.appendChild(info);
            topRow.appendChild(removeBtn);

            const bottomRow = document.createElement('div');
            bottomRow.className = 'flex items-center justify-between gap-2';

            const qtyGroup = document.createElement('div');
            qtyGroup.className = 'inline-flex items-center gap-1 bg-[#161412] border border-white/10 rounded-lg p-0.5';

            const decBtn = document.createElement('button');
            decBtn.type = 'button';
            decBtn.className = 'w-8 h-8 rounded-md text-sm font-semibold text-[#F4EEE5] hover:bg-white/10 active:text-[#D2B077]';
            decBtn.setAttribute('aria-label', `Decrease quantity of ${line.name}`);
            decBtn.textContent = '−';
            decBtn.addEventListener('click', () => {
                if (line.quantity > 1) {
                    line.quantity -= 1;
                } else {
                    editingNoteIndex = null;
                    cart.splice(idx, 1);
                }
                clearError();
                renderCart();
            });

            const qtyText = document.createElement('span');
            qtyText.className = 'w-7 text-center text-sm font-semibold tabular-nums text-[#F4EEE5]';
            qtyText.textContent = String(line.quantity);

            const incBtn = document.createElement('button');
            incBtn.type = 'button';
            incBtn.className = 'w-8 h-8 rounded-md text-sm font-semibold text-[#D2B077] hover:bg-white/10 disabled:opacity-40';
            incBtn.setAttribute('aria-label', `Increase quantity of ${line.name}`);
            incBtn.textContent = '+';
            incBtn.addEventListener('click', () => {
                const currentAcrossCart = cartTotalForProduct(line.product_id);
                if (currentAcrossCart + 1 > maxStock) {
                    showError(`Only ${maxStock} serving(s) of ${line.name} available in stock.`);
                    return;
                }
                line.quantity += 1;
                clearError();
                renderCart();
            });

            qtyGroup.appendChild(decBtn);
            qtyGroup.appendChild(qtyText);
            qtyGroup.appendChild(incBtn);

            const priceWrap = document.createElement('div');
            priceWrap.className = 'text-right';
            const linePrice = document.createElement('div');
            linePrice.className = 'text-sm font-semibold text-[#F4EEE5] tabular-nums';
            linePrice.textContent = formatPhp(lineTotalCentavos);
            const unitNote = document.createElement('div');
            unitNote.className = 'text-[11px] text-[#BFB4A5] tabular-nums';
            unitNote.textContent = `${formatPhp(line.unit_centavos)} ea`;
            priceWrap.appendChild(linePrice);
            priceWrap.appendChild(unitNote);

            bottomRow.appendChild(qtyGroup);
            bottomRow.appendChild(priceWrap);

            body.appendChild(topRow);
            body.appendChild(bottomRow);

            row.appendChild(thumb);
            row.appendChild(body);
            cartLinesEl.appendChild(row);
        });

        updatePaymentSummary(true);
    }

    function addLineToCart(product, variant, selectedAddons, quantity, note = null) {
        const currentInCart = cartTotalForProduct(product.id);
        if (currentInCart + quantity > product.stock_quantity) {
            showError(`Insufficient stock for ${product.name}. Available: ${product.stock_quantity}, already in cart: ${currentInCart}.`);
            return false;
        }

        const normalizedNote = (note || '').trim().slice(0, 120) || null;
        const addonIds = selectedAddons.map((a) => a.id).sort((a, b) => a - b);
        const unitCentavos =
            product.price_centavos +
            (variant ? variant.price_adjustment_centavos : 0) +
            selectedAddons.reduce((sum, a) => sum + a.price_centavos, 0);

        const existing = cart.find(
            (line) =>
                line.product_id === product.id &&
                line.product_variant_id === (variant ? variant.id : null) &&
                JSON.stringify(line.addon_ids) === JSON.stringify(addonIds) &&
                (line.note || null) === normalizedNote
        );

        if (existing) {
            existing.quantity += quantity;
        } else {
            cart.push({
                product_id: product.id,
                name: product.name,
                image_url: product.image_url,
                product_variant_id: variant ? variant.id : null,
                variant_label: variant ? variant.display_label : null,
                addon_ids: addonIds,
                addon_names: selectedAddons.map((a) => a.name),
                unit_centavos: unitCentavos,
                quantity,
                note: normalizedNote,
            });
        }

        clearError();
        renderCart();
        return true;
    }

    function buildLiveReceiptElement(ord) {
        const stage = document.createElement('div');
        stage.className = 'abc-printer-stage';
        stage.dataset.thermalPrinter = '';
        stage.dataset.animate = 'true';
        stage.dataset.state = 'ready';

        const chute = document.createElement('div');
        chute.className = 'abc-printer-chute';

        const paper = document.createElement('article');
        paper.className = 'receipt-paper text-xs leading-relaxed';

        const header = document.createElement('div');
        header.className = 'text-center border-b border-dashed border-neutral-300 pb-3 mb-3 space-y-1';
        const logoImg = document.createElement('img');
        logoImg.src = '/images/abc-logo.png';
        logoImg.alt = 'Artisan Brew Co.';
        logoImg.className = 'w-10 h-10 mx-auto object-contain border-0';
        const brand = document.createElement('div');
        brand.className = 'text-base font-bold tracking-wide uppercase text-[#1A1D21]';
        brand.textContent = 'Artisan Brew Co.';
        const sub = document.createElement('div');
        sub.className = 'text-[11px] text-[#6B7280]';
        sub.textContent = 'Specialty Coffee & Bakery Counter · Manila';
        const num = document.createElement('div');
        num.className = 'text-[11px] font-semibold tabular-nums pt-1 text-[#1A1D21]';
        num.textContent = ord.order_number;
        header.append(logoImg, brand, sub, num);

        const meta = document.createElement('div');
        meta.className = 'receipt-line space-y-1 border-b border-dashed border-neutral-300 pb-3 mb-3 text-[11px]';
        meta.style.setProperty('--line-index', '0');

        const metaRows = [
            ['Date (Manila):', ord.created_at_manila],
            ['Order Type:', ord.order_type === 'take_out' ? 'Take-out' : 'Dine-in'],
            ['Cashier:', ord.staff_name],
            ['Customer:', ord.customer_name],
        ];
        if (ord.cup_name) {
            metaRows.push(['Cup Name:', ord.cup_name]);
        }
        if (ord.order_note) {
            metaRows.push(['Order Note:', ord.order_note]);
        }

        metaRows.forEach(([label, val]) => {
            const row = document.createElement('div');
            row.className = 'flex justify-between gap-2';
            const l = document.createElement('span');
            l.className = 'text-[#6B7280] shrink-0';
            l.textContent = label;
            const v = document.createElement('span');
            v.className = 'font-medium text-right text-[#1A1D21]';
            v.textContent = val;
            row.append(l, v);
            meta.appendChild(row);
        });

        const linesWrap = document.createElement('div');
        linesWrap.className = 'border-b border-dashed border-neutral-300 pb-3 mb-3 space-y-2';
        (ord.lines || []).forEach((line, idx) => {
            const item = document.createElement('div');
            item.className = 'receipt-line';
            item.style.setProperty('--line-index', String(idx + 1));

            const top = document.createElement('div');
            top.className = 'flex justify-between gap-2 font-semibold text-[#1A1D21]';
            const nameEl = document.createElement('span');
            nameEl.textContent = `${line.quantity}× ${line.product_name}`;
            const amtEl = document.createElement('span');
            amtEl.className = 'tabular-nums shrink-0';
            amtEl.textContent = line.total_price_formatted;
            top.append(nameEl, amtEl);
            item.appendChild(top);

            if (line.variant_label) {
                const vEl = document.createElement('div');
                vEl.className = 'text-[11px] text-[#6B7280] pl-3';
                vEl.textContent = line.variant_label;
                item.appendChild(vEl);
            }

            (line.addons || []).forEach((addon) => {
                const aEl = document.createElement('div');
                aEl.className = 'text-[11px] text-[#6B7280] pl-3';
                aEl.textContent = `+ ${addon}`;
                item.appendChild(aEl);
            });

            if (line.note) {
                const nEl = document.createElement('div');
                nEl.className = 'text-[11px] italic text-[#6B7280] pl-3';
                nEl.textContent = `Note: ${line.note}`;
                item.appendChild(nEl);
            }

            linesWrap.appendChild(item);
        });

        const totalsIndex = (ord.lines || []).length + 1;
        const totals = document.createElement('div');
        totals.className = 'receipt-line space-y-1.5 text-xs border-b border-dashed border-neutral-300 pb-3 mb-3';
        totals.style.setProperty('--line-index', String(totalsIndex));

        const totalRows = [];
        if (Number(ord.discount_amount || 0) > 0) {
            totalRows.push(['Subtotal', ord.subtotal_formatted, 'flex justify-between text-[11px] text-[#6B7280]']);
            totalRows.push([
                ord.discount_label ? `Discount (${ord.discount_label})` : 'Discount',
                `-${ord.discount_formatted}`,
                'flex justify-between text-[11px] text-[#6B7280]',
            ]);
        }
        totalRows.push(['TOTAL (PHP)', ord.total_formatted, 'flex justify-between text-sm font-bold text-[#1A1D21]']);
        totalRows.push(['Payment Method', formatPaymentMethodLabel(ord.payment_method), 'flex justify-between text-[11px] text-[#6B7280]']);
        if ((ord.payment_method || 'cash') === 'cash') {
            totalRows.push(['Cash Received', ord.cash_formatted, 'flex justify-between text-[11px] text-[#6B7280]']);
            totalRows.push(['Change Due', ord.change_formatted, 'flex justify-between text-[11px] font-semibold text-[#1A1D21]']);
        } else if (ord.payment_reference) {
            totalRows.push(['Reference #', ord.payment_reference, 'flex justify-between text-[11px] text-[#6B7280]']);
        }

        totalRows.forEach(([l, v, cls]) => {
            const row = document.createElement('div');
            row.className = cls;
            const lSpan = document.createElement('span');
            lSpan.textContent = l;
            const vSpan = document.createElement('span');
            vSpan.className = 'tabular-nums';
            vSpan.textContent = v;
            row.append(lSpan, vSpan);
            totals.appendChild(row);
        });

        const footer = document.createElement('div');
        footer.className = 'text-center text-[11px] text-[#5F584F]';
        footer.textContent = 'Thank you for brewing with Artisan Brew Co.';

        paper.append(header, meta, linesWrap, totals, footer);
        chute.appendChild(paper);

        const body = document.createElement('div');
        body.className = 'abc-printer-body no-print';
        body.setAttribute('aria-hidden', 'true');

        const recess = document.createElement('div');
        recess.className = 'abc-printer-slot-recess';
        const cutterBar = document.createElement('div');
        cutterBar.className = 'abc-printer-cutter-bar';
        recess.appendChild(cutterBar);

        const lip = document.createElement('div');
        lip.className = 'abc-printer-lip';
        const teeth = document.createElement('div');
        teeth.className = 'abc-printer-cutter-teeth';
        lip.appendChild(teeth);

        const deck = document.createElement('div');
        deck.className = 'abc-printer-deck';
        const brandWrap = document.createElement('div');
        brandWrap.className = 'abc-printer-brand';
        const badge = document.createElement('span');
        badge.className = 'abc-printer-badge';
        badge.textContent = 'ABC';
        const model = document.createElement('span');
        model.className = 'abc-printer-model';
        model.textContent = 'THERMAL · 80MM';
        brandWrap.append(badge, model);

        const leds = document.createElement('div');
        leds.className = 'abc-printer-leds';
        const led = document.createElement('span');
        led.className = 'abc-printer-led';
        leds.appendChild(led);
        deck.append(brandWrap, leds);

        const shadow = document.createElement('div');
        shadow.className = 'abc-printer-shadow';

        body.append(recess, lip, deck, shadow);

        const controls = document.createElement('div');
        controls.className = 'abc-printer-controls no-print';

        const statusWrap = document.createElement('div');
        statusWrap.className = 'abc-printer-status-wrap';
        statusWrap.setAttribute('aria-live', 'polite');
        const dot = document.createElement('span');
        dot.className = 'abc-printer-status-dot';
        dot.setAttribute('aria-hidden', 'true');
        const statusText = document.createElement('span');
        statusText.className = 'abc-printer-status-text';
        statusText.dataset.printerStatus = '';
        statusText.textContent = 'Ready';
        statusWrap.append(dot, statusText);

        const replayBtn = document.createElement('button');
        replayBtn.type = 'button';
        replayBtn.dataset.replayPrinter = '';
        replayBtn.className = 'abc-btn-secondary py-1.5 px-3 text-xs';
        replayBtn.textContent = 'Replay Print';

        controls.append(statusWrap, replayBtn);
        stage.append(chute, body, controls);

        return stage;
    }

    function syncQuickNoteChips() {
        const current = (configNoteInput?.value || '')
            .split(',')
            .map((s) => s.trim().toLowerCase())
            .filter(Boolean);
        configQuickNotes.forEach((chip) => {
            const val = (chip.dataset.configQuickNote || '').toLowerCase();
            const active = current.includes(val);
            chip.setAttribute('aria-pressed', active ? 'true' : 'false');
            chip.classList.toggle('bg-[#D2B077]', active);
            chip.classList.toggle('text-[#111110]', active);
            chip.classList.toggle('font-semibold', active);
            chip.classList.toggle('bg-[#28241F]', !active);
            chip.classList.toggle('text-[#BFB4A5]', !active);
        });
    }

    configQuickNotes.forEach((chip) => {
        chip.addEventListener('click', () => {
            if (!configNoteInput) return;
            const label = chip.dataset.configQuickNote || '';
            const parts = configNoteInput.value
                .split(',')
                .map((s) => s.trim())
                .filter(Boolean);
            const existingIdx = parts.findIndex((p) => p.toLowerCase() === label.toLowerCase());
            if (existingIdx >= 0) {
                parts.splice(existingIdx, 1);
            } else {
                parts.push(label);
            }
            configNoteInput.value = parts.join(', ').slice(0, 120);
            syncQuickNoteChips();
        });
    });

    configNoteInput?.addEventListener('input', syncQuickNoteChips);

    function updateConfiguratorPreview() {
        if (!configuringProduct) return;

        let unit = configuringProduct.price_centavos;
        const checkedVariant = configVariantsList?.querySelector('input[name="cfg_variant"]:checked');
        if (checkedVariant) {
            const vId = Number(checkedVariant.value);
            const variant = configuringProduct.variants.find((v) => v.id === vId);
            if (variant) unit += variant.price_adjustment_centavos;
        }

        configAddonsList?.querySelectorAll('input[type="checkbox"]:checked').forEach((cb) => {
            const aId = Number(cb.value);
            const addon = configuringProduct.addons.find((a) => a.id === aId);
            if (addon) unit += addon.price_centavos;
        });

        const qty = Math.max(1, Number.parseInt(configQtyInput?.value || '1', 10) || 1);
        if (configUnitPreview) {
            configUnitPreview.textContent = `${formatPhp(unit)} × ${qty} = ${formatPhp(unit * qty)}`;
        }
    }

    function openConfigurator(product) {
        if (product.variants.length === 0 && product.addons.length === 0) {
            addLineToCart(product, null, [], 1, null);
            return;
        }

        configuringProduct = product;
        if (configTitle) configTitle.textContent = product.name;
        if (configBasePrice) configBasePrice.textContent = `Base ${formatPhp(product.price_centavos)}`;
        if (configStockNote) {
            const remaining = Math.max(0, product.stock_quantity - cartTotalForProduct(product.id));
            configStockNote.textContent = `${remaining} serving(s) available`;
        }
        if (configQtyInput) configQtyInput.value = '1';
        if (configNoteInput) configNoteInput.value = '';
        syncQuickNoteChips();

        if (configVariantsList && configVariantsWrap) {
            configVariantsList.innerHTML = '';
            configVariantsWrap.classList.toggle('hidden', product.variants.length === 0);
            product.variants.forEach((v, index) => {
                const label = document.createElement('label');
                label.className = 'abc-surface p-2.5 flex items-center justify-between gap-2 cursor-pointer hover:border-[#D2B077]/50';

                const left = document.createElement('span');
                left.className = 'flex items-center gap-2 text-sm';

                const radio = document.createElement('input');
                radio.type = 'radio';
                radio.name = 'cfg_variant';
                radio.value = String(v.id);
                radio.checked = index === 0;
                radio.className = 'accent-[#D2B077]';
                radio.addEventListener('change', updateConfiguratorPreview);

                const txt = document.createElement('span');
                txt.textContent = v.display_label;
                left.appendChild(radio);
                left.appendChild(txt);

                const adj = document.createElement('span');
                adj.className = 'text-xs font-medium text-[#D2B077] tabular-nums';
                adj.textContent = v.price_adjustment_centavos > 0 ? `+${formatPhp(v.price_adjustment_centavos)}` : 'Included';

                label.appendChild(left);
                label.appendChild(adj);
                configVariantsList.appendChild(label);
            });
        }

        if (configAddonsList && configAddonsWrap) {
            configAddonsList.innerHTML = '';
            configAddonsWrap.classList.toggle('hidden', product.addons.length === 0);
            product.addons.forEach((a) => {
                const label = document.createElement('label');
                label.className = 'abc-surface p-2.5 flex items-center justify-between gap-2 cursor-pointer hover:border-[#D2B077]/50';

                const left = document.createElement('span');
                left.className = 'flex items-center gap-2 text-sm';

                const cb = document.createElement('input');
                cb.type = 'checkbox';
                cb.value = String(a.id);
                cb.className = 'accent-[#D2B077]';
                cb.addEventListener('change', updateConfiguratorPreview);

                const txt = document.createElement('span');
                txt.textContent = a.name;
                left.appendChild(cb);
                left.appendChild(txt);

                const price = document.createElement('span');
                price.className = 'text-xs font-medium text-[#BFB4A5] tabular-nums';
                price.textContent = `+${formatPhp(a.price_centavos)}`;

                label.appendChild(left);
                label.appendChild(price);
                configAddonsList.appendChild(label);
            });
        }

        updateConfiguratorPreview();
        configDialog?.showModal();
    }

    configQtyInput?.addEventListener('input', updateConfiguratorPreview);
    configCancelBtn?.addEventListener('click', () => configDialog?.close());

    configAddBtn?.addEventListener('click', () => {
        if (!configuringProduct) return;

        let selectedVariant = null;
        if (configuringProduct.variants.length > 0) {
            const checked = configVariantsList?.querySelector('input[name="cfg_variant"]:checked');
            const vId = checked ? Number(checked.value) : null;
            selectedVariant = configuringProduct.variants.find((v) => v.id === vId) || null;
        }

        const selectedAddons = [];
        configAddonsList?.querySelectorAll('input[type="checkbox"]:checked').forEach((cb) => {
            const addon = configuringProduct.addons.find((a) => a.id === Number(cb.value));
            if (addon) selectedAddons.push(addon);
        });

        const qty = Math.max(1, Number.parseInt(configQtyInput?.value || '1', 10) || 1);
        const note = configNoteInput?.value || null;
        if (addLineToCart(configuringProduct, selectedVariant, selectedAddons, qty, note)) {
            configDialog?.close();
        }
    });

    searchInput?.addEventListener('input', (e) => {
        searchTerm = e.target.value || '';
        filterCatalog();
    });

    browseMenuBtn?.addEventListener('click', () => {
        setMobileSheet(false);
        searchInput?.focus();
    });

    categoryButtons.forEach((btn) => {
        btn.addEventListener('click', () => {
            activeCategory = btn.dataset.posCategory || 'all';
            categoryButtons.forEach((other) => {
                const active = other === btn;
                other.setAttribute('aria-pressed', active ? 'true' : 'false');
                other.classList.toggle('bg-[#D2B077]', active);
                other.classList.toggle('text-[#111110]', active);
                other.classList.toggle('font-semibold', active);
                other.classList.toggle('bg-[#28241F]', !active);
                other.classList.toggle('text-[#BFB4A5]', !active);
            });
            filterCatalog();
        });
    });

    document.querySelectorAll('[data-add-product]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const pid = Number(btn.dataset.addProduct);
            const product = productMap.get(pid);
            if (!product || product.stock_quantity <= 0) return;
            openConfigurator(product);
        });
    });

    function resetOrderState() {
        cart = [];
        editingNoteIndex = null;
        if (customerSelect) customerSelect.value = '';
        if (cupNameInput) cupNameInput.value = '';
        if (orderNoteInput) orderNoteInput.value = '';
        if (receiptEmailInput) receiptEmailInput.value = '';
        if (marketingOptInInput) marketingOptInInput.checked = false;
        if (paymentRefInput) paymentRefInput.value = '';
        if (discountValueInput) discountValueInput.value = '';
        if (giftCardCodeInput) giftCardCodeInput.value = '';
        verifiedGiftCard = null;
        applyOrderType('dine_in');
        applyDiscountType('none');
        applyPaymentMethod('cash');
        applyPayOption('exact', false);
    }

    clearBtn?.addEventListener('click', () => {
        resetOrderState();
        clearError();
        renderCart();
    });

    cashInput?.addEventListener('input', () => {
        if (selectedPayOption !== 'custom') {
            applyPayOption('custom', false);
        } else {
            updatePaymentSummary(false);
        }
    });

    let customerSearchTimer = null;
    customerFilterInput?.addEventListener('input', () => {
        if (!customerSearchUrl || !customerSelect) return;
        window.clearTimeout(customerSearchTimer);
        customerSearchTimer = window.setTimeout(async () => {
            try {
                const q = encodeURIComponent(customerFilterInput.value || '');
                const res = await fetch(`${customerSearchUrl}?q=${q}`, {
                    headers: { Accept: 'application/json' },
                });
                if (!res.ok) return;
                const data = await res.json();
                const currentVal = customerSelect.value;
                customerSelect.innerHTML = '<option value="">Walk-in customer</option>';
                (data.customers || []).forEach((c) => {
                    const opt = document.createElement('option');
                    opt.value = String(c.id);
                    opt.textContent = c.label;
                    if (String(c.id) === currentVal) opt.selected = true;
                    customerSelect.appendChild(opt);
                });
            } catch {
            }
        }, 250);
    });

    mobileCartToggle?.addEventListener('click', () => setMobileSheet(true));
    mobileCartClose?.addEventListener('click', () => setMobileSheet(false));
    sheetBackdrop?.addEventListener('click', () => setMobileSheet(false));

    submitBtn?.addEventListener('click', async () => {
        if (isSubmitting || cart.length === 0) return;

        isSubmitting = true;
        clearError();
        cartLoadingEl?.classList.remove('hidden');
        if (checkoutLabel) checkoutLabel.textContent = 'Completing sale...';
        updatePaymentSummary(false);

        const payload = {
            checkout_token: checkoutToken,
            customer_id: customerSelect?.value ? Number(customerSelect.value) : null,
            order_type: orderType,
            payment_method: paymentMethod,
            payment_reference: paymentMethod === 'cash' ? null : (paymentRefInput?.value.trim() || null),
            cup_name: cupNameInput?.value.trim() || null,
            order_note: orderNoteInput?.value.trim() || null,
            receipt_email: receiptEmailInput?.value.trim() || null,
            marketing_opt_in: Boolean(marketingOptInInput?.checked),
            discount_type: discountType,
            discount_value: discountType === 'percent' || discountType === 'fixed' ? (discountValueInput?.value || null) : null,
            gift_card_code: discountType === 'giftcard' ? (giftCardCodeInput?.value.trim().toUpperCase() || null) : null,
            cash_received: paymentMethod === 'cash' ? (cashInput?.value || '0') : null,
            items: cart.map((line) => ({
                product_id: line.product_id,
                product_variant_id: line.product_variant_id,
                addon_ids: line.addon_ids,
                quantity: line.quantity,
                note: line.note || null,
            })),
        };

        try {
            const response = await fetch(checkoutUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify(payload),
            });

            if (response.status === 419) {
                showError('Your session expired. Refreshing page to renew security token...');
                window.setTimeout(() => window.location.reload(), 1200);
                return;
            }

            const data = await response.json().catch(() => ({}));

            if (!response.ok) {
                if (data.errors) {
                    const firstErr = Object.values(data.errors).flat()[0];
                    showError(firstErr || 'Please check your order details.');
                } else {
                    showError(data.message || 'Unable to complete checkout. Your cart was preserved.');
                }
                return;
            }

            if (data.next_checkout_token) {
                checkoutToken = data.next_checkout_token;
            }
            if (data.stocks) {
                updateProductStockBadges(data.stocks);
            }

            resetOrderState();
            renderCart();
            setMobileSheet(false);

            if (receiptDialog && data.order) {
                const ord = data.order;
                if (successOrderNum) successOrderNum.textContent = `Receipt ${ord.order_number}`;
                if (successTotal) successTotal.textContent = ord.total_formatted;
                if (successMethod) successMethod.textContent = formatPaymentMethodLabel(ord.payment_method);

                const isCash = (ord.payment_method || 'cash') === 'cash';
                successCashRow?.classList.toggle('hidden', !isCash);
                successChangeRow?.classList.toggle('hidden', !isCash);
                successRefRow?.classList.toggle('hidden', isCash || !ord.payment_reference);
                if (successRef) successRef.textContent = ord.payment_reference || '';
                if (successCash) successCash.textContent = ord.cash_formatted;
                if (successChange) successChange.textContent = ord.change_formatted;

                if (successDetailLink) successDetailLink.href = ord.detail_url;
                if (receiptPrintLink) receiptPrintLink.href = ord.receipt_url;
                lastEmailReceiptUrl = ord.email_receipt_url || null;
                if (dialogEmailInput) dialogEmailInput.value = ord.customer_email || '';
                if (dialogMarketingOptIn) dialogMarketingOptIn.checked = false;
                if (successEmailStatus) {
                    if (data.receipt_email_status) {
                        successEmailStatus.textContent = `Digital receipt queued (${data.receipt_email_status}).`;
                        successEmailStatus.classList.remove('hidden');
                    } else {
                        successEmailStatus.textContent = '';
                        successEmailStatus.classList.add('hidden');
                    }
                }
                let liveStage = null;
                if (liveReceiptWrap) {
                    const existingStage = liveReceiptWrap.querySelector('[data-thermal-printer]');
                    if (existingStage) cleanupThermalPrinter(existingStage);
                    liveReceiptWrap.innerHTML = '';
                    liveStage = buildLiveReceiptElement(ord);
                    liveReceiptWrap.appendChild(liveStage);
                    bindThermalPrinterControls(liveStage);
                }
                receiptDialog.showModal();
                if (liveStage) {
                    playThermalPrinter(liveStage);
                }
            }
        } catch {
            showError('Network error while submitting sale. Retry safely — your checkout token prevents duplicate charges.');
        } finally {
            isSubmitting = false;
            cartLoadingEl?.classList.add('hidden');
            updatePaymentSummary(false);
        }
    });

    receiptNewOrderBtn?.addEventListener('click', () => receiptDialog?.close());
    receiptDialog?.addEventListener('close', () => {
        const activeStage = liveReceiptWrap?.querySelector('[data-thermal-printer]');
        if (activeStage) {
            cleanupThermalPrinter(activeStage);
        }
    });

    dialogEmailForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!lastEmailReceiptUrl || !dialogEmailInput) return;
        const emailVal = dialogEmailInput.value.trim();
        if (!emailVal) return;

        if (dialogEmailSubmit) dialogEmailSubmit.disabled = true;
        try {
            const res = await fetch(lastEmailReceiptUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({
                    receipt_email: emailVal,
                    confirm_address: true,
                    marketing_opt_in: Boolean(dialogMarketingOptIn?.checked),
                }),
            });
            if (successEmailStatus) {
                successEmailStatus.classList.remove('hidden');
                successEmailStatus.textContent = res.ok
                    ? `Digital receipt queued for ${emailVal}.`
                    : 'Unable to send receipt email. Check the address or try from the receipt page.';
            }
        } catch {
            if (successEmailStatus) {
                successEmailStatus.classList.remove('hidden');
                successEmailStatus.textContent = 'Network error while queuing digital receipt.';
            }
        } finally {
            if (dialogEmailSubmit) dialogEmailSubmit.disabled = false;
        }
    });

    renderCart();
});
