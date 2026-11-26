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

document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('pos-app');
    if (!root) return;

    const catalog = JSON.parse(root.dataset.catalog || '[]');
    const checkoutUrl = root.dataset.checkoutUrl;
    const customerSearchUrl = root.dataset.customerSearchUrl;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

    let checkoutToken = root.dataset.checkoutToken;
    let activeCategory = 'all';
    let searchTerm = '';
    let selectedPayOption = 'exact';
    let cart = [];
    let isSubmitting = false;

    const productMap = new Map(catalog.map((p) => [p.id, p]));

    const searchInput = document.getElementById('pos-search');
    const categoryButtons = document.querySelectorAll('[data-pos-category]');
    const productCards = document.querySelectorAll('[data-pos-product]');
    const emptyCatalog = document.getElementById('pos-empty-catalog');

    const cartLinesEl = document.getElementById('pos-cart-lines');
    const cartEmptyEl = document.getElementById('pos-cart-empty');
    const cartLoadingEl = document.getElementById('pos-cart-loading');
    const browseMenuBtn = document.getElementById('pos-browse-menu-btn');
    const cartCountBadge = document.getElementById('pos-cart-count');
    const mobileCartCount = document.getElementById('pos-mobile-cart-count');
    const mobileCartTotal = document.getElementById('pos-mobile-cart-total');
    const subtotalEl = document.getElementById('pos-subtotal');
    const totalEl = document.getElementById('pos-total');
    const cashInput = document.getElementById('pos-cash-received');
    const changeEl = document.getElementById('pos-change');
    const customerSelect = document.getElementById('pos-customer-id');
    const customerFilterInput = document.getElementById('pos-customer-search');
    const submitBtn = document.getElementById('pos-checkout-btn');
    const checkoutLabel = document.getElementById('pos-checkout-label');
    const clearBtn = document.getElementById('pos-clear-cart-btn');
    const errorBox = document.getElementById('pos-error-box');
    const errorMsg = document.getElementById('pos-error-msg');
    const orderPanel = document.getElementById('pos-order-panel');
    const sheetBackdrop = document.getElementById('pos-mobile-sheet-backdrop');
    const mobileCartToggle = document.getElementById('pos-mobile-cart-toggle');
    const mobileCartClose = document.getElementById('pos-mobile-cart-close');
    const payCards = Array.from(document.querySelectorAll('[data-pay-option]'));

    const configDialog = document.getElementById('pos-config-dialog');
    const configTitle = document.getElementById('pos-config-title');
    const configBasePrice = document.getElementById('pos-config-base');
    const configStockNote = document.getElementById('pos-config-stock');
    const configVariantsWrap = document.getElementById('pos-config-variants-wrap');
    const configVariantsList = document.getElementById('pos-config-variants');
    const configAddonsWrap = document.getElementById('pos-config-addons-wrap');
    const configAddonsList = document.getElementById('pos-config-addons');
    const configQtyInput = document.getElementById('pos-config-qty');
    const configUnitPreview = document.getElementById('pos-config-unit-preview');
    const configAddBtn = document.getElementById('pos-config-add-btn');
    const configCancelBtn = document.getElementById('pos-config-cancel');

    const receiptDialog = document.getElementById('pos-receipt-dialog');
    const successOrderNum = document.getElementById('pos-success-order-num');
    const successTotal = document.getElementById('pos-success-total');
    const successCash = document.getElementById('pos-success-cash');
    const successChange = document.getElementById('pos-success-change');
    const successDetailLink = document.getElementById('pos-success-detail-link');
    const liveReceiptWrap = document.getElementById('pos-live-receipt-wrap');
    const receiptPrintLink = document.getElementById('pos-receipt-print-link');
    const receiptNewOrderBtn = document.getElementById('pos-receipt-new-order');

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

    function applyPayOption(option, focusCustom = false) {
        selectedPayOption = option;
        payCards.forEach((card) => {
            const active = card.dataset.payOption === option;
            card.setAttribute('aria-checked', active ? 'true' : 'false');
            card.setAttribute('tabindex', active ? '0' : '-1');
            const badge = card.querySelector('[data-pay-check]');
            if (badge) {
                badge.classList.toggle('hidden', !active);
                badge.classList.toggle('flex', active);
            }
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
        let totalCentavos = 0;
        let totalQty = 0;

        cart.forEach((line) => {
            totalCentavos += line.unit_centavos * line.quantity;
            totalQty += line.quantity;
        });

        return { totalCentavos, totalQty };
    }

    function updatePaymentSummary(animateTick = false) {
        const { totalCentavos, totalQty } = computeTotals();
        if (selectedPayOption === 'exact' && cashInput) {
            cashInput.value = totalCentavos > 0 ? (totalCentavos / 100).toFixed(2) : '';
        }

        const cashCentavos = toCentavos(cashInput?.value);
        const changeCentavos = cashCentavos - totalCentavos;

        if (subtotalEl) subtotalEl.textContent = formatPhp(totalCentavos);
        if (totalEl) totalEl.textContent = formatPhp(totalCentavos);
        if (cartCountBadge) cartCountBadge.textContent = String(totalQty);
        if (mobileCartCount) mobileCartCount.textContent = String(totalQty);
        if (mobileCartTotal) mobileCartTotal.textContent = formatPhp(totalCentavos);

        if (changeEl) {
            if (totalCentavos === 0) {
                changeEl.textContent = '₱0.00';
                changeEl.className = 'tabular-nums font-semibold text-[#BFB4A5]';
            } else if (changeCentavos < 0) {
                changeEl.textContent = `Short ${formatPhp(Math.abs(changeCentavos))}`;
                changeEl.className = 'tabular-nums font-semibold text-[#E07A7A]';
            } else {
                changeEl.textContent = formatPhp(changeCentavos);
                changeEl.className = 'tabular-nums font-semibold text-[#22A85B]';
            }
        }

        if (checkoutLabel && !isSubmitting) {
            checkoutLabel.textContent = `Complete Sale · ${formatPhp(totalCentavos)}`;
            if (animateTick) tickCta();
        }

        if (submitBtn) {
            submitBtn.disabled = isSubmitting || cart.length === 0 || cashCentavos < totalCentavos;
        }
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
                thumb.innerHTML = '<svg class="w-5 h-5 text-[#D2B077]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M17 8h1a3 3 0 010 6h-1M4 8h13v6a5 5 0 01-5 5H9a5 5 0 01-5-5V8z"/></svg>';
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

            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'text-xs text-[#BFB4A5] hover:text-[#E07A7A] px-1.5 py-0.5 rounded shrink-0';
            removeBtn.setAttribute('aria-label', `Remove ${line.name}`);
            removeBtn.textContent = 'Remove';
            removeBtn.addEventListener('click', () => {
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
            decBtn.className = 'w-9 h-9 rounded-md text-sm font-semibold text-[#F4EEE5] hover:bg-white/10 active:text-[#D2B077]';
            decBtn.setAttribute('aria-label', `Decrease quantity of ${line.name}`);
            decBtn.textContent = '−';
            decBtn.addEventListener('click', () => {
                if (line.quantity > 1) {
                    line.quantity -= 1;
                } else {
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
            incBtn.className = 'w-9 h-9 rounded-md text-sm font-semibold text-[#D2B077] hover:bg-white/10 disabled:opacity-40';
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

    function addLineToCart(product, variant, selectedAddons, quantity) {
        const currentInCart = cartTotalForProduct(product.id);
        if (currentInCart + quantity > product.stock_quantity) {
            showError(`Insufficient stock for ${product.name}. Available: ${product.stock_quantity}, already in cart: ${currentInCart}.`);
            return false;
        }

        const addonIds = selectedAddons.map((a) => a.id).sort((a, b) => a - b);
        const unitCentavos =
            product.price_centavos +
            (variant ? variant.price_adjustment_centavos : 0) +
            selectedAddons.reduce((sum, a) => sum + a.price_centavos, 0);

        const existing = cart.find(
            (line) =>
                line.product_id === product.id &&
                line.product_variant_id === (variant ? variant.id : null) &&
                JSON.stringify(line.addon_ids) === JSON.stringify(addonIds)
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
            });
        }

        clearError();
        renderCart();
        return true;
    }

    function buildLiveReceiptElement(ord) {
        const paper = document.createElement('article');
        paper.className = 'receipt-paper receipt-unroll text-xs leading-relaxed';

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
        [
            ['Date (Manila):', ord.created_at_manila],
            ['Cashier:', ord.staff_name],
            ['Customer:', ord.customer_name],
        ].forEach(([label, val]) => {
            const row = document.createElement('div');
            row.className = 'flex justify-between gap-2';
            const l = document.createElement('span');
            l.className = 'text-[#6B7280]';
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

            linesWrap.appendChild(item);
        });

        const totalsIndex = (ord.lines || []).length + 1;
        const totals = document.createElement('div');
        totals.className = 'receipt-line space-y-1.5 text-xs border-b border-dashed border-neutral-300 pb-3 mb-3';
        totals.style.setProperty('--line-index', String(totalsIndex));
        [
            ['TOTAL (PHP)', ord.total_formatted, 'flex justify-between text-sm font-bold text-[#1A1D21]'],
            ['Cash Received', ord.cash_formatted, 'flex justify-between text-[11px] text-[#6B7280]'],
            ['Change Due', ord.change_formatted, 'flex justify-between text-[11px] font-semibold text-[#1A1D21]'],
        ].forEach(([l, v, cls]) => {
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
        footer.className = 'receipt-line text-center text-[11px] text-[#6B7280]';
        footer.style.setProperty('--line-index', String(totalsIndex + 1));
        footer.textContent = 'Thank you for brewing with Artisan Brew Co.';

        paper.append(header, meta, linesWrap, totals, footer);
        return paper;
    }

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
            addLineToCart(product, null, [], 1);
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
        if (addLineToCart(configuringProduct, selectedVariant, selectedAddons, qty)) {
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

    clearBtn?.addEventListener('click', () => {
        cart = [];
        applyPayOption('exact', false);
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
            cash_received: cashInput?.value || '0',
            items: cart.map((line) => ({
                product_id: line.product_id,
                product_variant_id: line.product_variant_id,
                addon_ids: line.addon_ids,
                quantity: line.quantity,
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

            cart = [];
            if (customerSelect) customerSelect.value = '';
            applyPayOption('exact', false);
            renderCart();
            setMobileSheet(false);

            if (receiptDialog && data.order) {
                const ord = data.order;
                if (successOrderNum) successOrderNum.textContent = `Receipt ${ord.order_number}`;
                if (successTotal) successTotal.textContent = ord.total_formatted;
                if (successCash) successCash.textContent = ord.cash_formatted;
                if (successChange) successChange.textContent = ord.change_formatted;
                if (successDetailLink) successDetailLink.href = ord.detail_url;
                if (receiptPrintLink) receiptPrintLink.href = ord.receipt_url;
                if (liveReceiptWrap) {
                    liveReceiptWrap.innerHTML = '';
                    liveReceiptWrap.appendChild(buildLiveReceiptElement(ord));
                }
                receiptDialog.showModal();
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

    renderCart();
});
