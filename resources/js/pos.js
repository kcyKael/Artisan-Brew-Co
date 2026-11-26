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
    let cart = [];
    let isSubmitting = false;

    const productMap = new Map(catalog.map((p) => [p.id, p]));

    const searchInput = document.getElementById('pos-search');
    const categoryButtons = document.querySelectorAll('[data-pos-category]');
    const productCards = document.querySelectorAll('[data-pos-product]');
    const emptyCatalog = document.getElementById('pos-empty-catalog');

    const cartLinesEl = document.getElementById('pos-cart-lines');
    const cartEmptyEl = document.getElementById('pos-cart-empty');
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
    const clearBtn = document.getElementById('pos-clear-cart-btn');
    const errorBox = document.getElementById('pos-error-box');
    const errorMsg = document.getElementById('pos-error-msg');
    const orderPanel = document.getElementById('pos-order-panel');
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
    const configQtyInput = document.getElementById('pos-config-qty');
    const configUnitPreview = document.getElementById('pos-config-unit-preview');
    const configAddBtn = document.getElementById('pos-config-add-btn');
    const configCancelBtn = document.getElementById('pos-config-cancel');

    const receiptDialog = document.getElementById('pos-receipt-dialog');
    const receiptSummary = document.getElementById('pos-receipt-summary');
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
                    badge.className = 'text-xs font-medium px-2 py-0.5 rounded bg-[rgba(230,119,125,0.16)] text-[#E6777D]';
                } else if (product && newStock <= product.low_stock_threshold) {
                    badge.textContent = `Low: ${newStock}`;
                    badge.className = 'text-xs font-medium px-2 py-0.5 rounded bg-[rgba(224,164,88,0.16)] text-[#E0A458] tabular-nums';
                } else {
                    badge.textContent = `${newStock} left`;
                    badge.className = 'text-xs font-medium px-2 py-0.5 rounded bg-[rgba(88,185,135,0.14)] text-[#58B987] tabular-nums';
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

    function updatePaymentSummary() {
        const { totalCentavos, totalQty } = computeTotals();
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
                changeEl.className = 'text-base font-semibold tabular-nums text-[#BDB5AA]';
            } else if (changeCentavos < 0) {
                changeEl.textContent = `Short ${formatPhp(Math.abs(changeCentavos))}`;
                changeEl.className = 'text-base font-semibold tabular-nums text-[#E6777D]';
            } else {
                changeEl.textContent = formatPhp(changeCentavos);
                changeEl.className = 'text-base font-semibold tabular-nums text-[#58B987]';
            }
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
            row.className = 'abc-surface p-3 flex flex-col gap-2';
            row.dataset.cartLine = String(idx);

            const header = document.createElement('div');
            header.className = 'flex items-start justify-between gap-2';

            const info = document.createElement('div');
            info.className = 'min-w-0';

            const title = document.createElement('div');
            title.className = 'font-medium text-sm text-[#F5F0E8] leading-snug';
            title.textContent = line.name;
            info.appendChild(title);

            if (line.variant_label) {
                const vLabel = document.createElement('div');
                vLabel.className = 'text-xs text-[#C6A66B] mt-0.5';
                vLabel.textContent = line.variant_label;
                info.appendChild(vLabel);
            }

            if (line.addon_names.length > 0) {
                const aLabel = document.createElement('div');
                aLabel.className = 'text-xs text-[#BDB5AA] mt-0.5';
                aLabel.textContent = '+ ' + line.addon_names.join(', ');
                info.appendChild(aLabel);
            }

            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'text-xs text-[#BDB5AA] hover:text-[#E6777D] px-1.5 py-0.5 rounded';
            removeBtn.setAttribute('aria-label', `Remove ${line.name}`);
            removeBtn.textContent = 'Remove';
            removeBtn.addEventListener('click', () => {
                cart.splice(idx, 1);
                clearError();
                renderCart();
            });

            header.appendChild(info);
            header.appendChild(removeBtn);

            const footer = document.createElement('div');
            footer.className = 'flex items-center justify-between gap-2 pt-1';

            const qtyGroup = document.createElement('div');
            qtyGroup.className = 'inline-flex items-center gap-1.5 bg-[#141311] border border-white/10 rounded-md p-0.5';

            const decBtn = document.createElement('button');
            decBtn.type = 'button';
            decBtn.className = 'w-7 h-7 rounded text-sm font-semibold text-[#F5F0E8] hover:bg-white/10';
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
            qtyText.className = 'w-7 text-center text-sm font-semibold tabular-nums';
            qtyText.textContent = String(line.quantity);

            const incBtn = document.createElement('button');
            incBtn.type = 'button';
            incBtn.className = 'w-7 h-7 rounded text-sm font-semibold text-[#F5F0E8] hover:bg-white/10 disabled:opacity-40';
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
            linePrice.className = 'text-sm font-semibold text-[#F5F0E8] tabular-nums';
            linePrice.textContent = formatPhp(lineTotalCentavos);
            const unitNote = document.createElement('div');
            unitNote.className = 'text-[11px] text-[#BDB5AA] tabular-nums';
            unitNote.textContent = `${formatPhp(line.unit_centavos)} ea`;
            priceWrap.appendChild(linePrice);
            priceWrap.appendChild(unitNote);

            footer.appendChild(qtyGroup);
            footer.appendChild(priceWrap);

            row.appendChild(header);
            row.appendChild(footer);
            cartLinesEl.appendChild(row);
        });

        updatePaymentSummary();
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
                label.className = 'abc-surface p-2.5 flex items-center justify-between gap-2 cursor-pointer hover:border-[#C6A66B]/50';

                const left = document.createElement('span');
                left.className = 'flex items-center gap-2 text-sm';

                const radio = document.createElement('input');
                radio.type = 'radio';
                radio.name = 'cfg_variant';
                radio.value = String(v.id);
                radio.checked = index === 0;
                radio.className = 'accent-[#C6A66B]';
                radio.addEventListener('change', updateConfiguratorPreview);

                const txt = document.createElement('span');
                txt.textContent = v.display_label;
                left.appendChild(radio);
                left.appendChild(txt);

                const adj = document.createElement('span');
                adj.className = 'text-xs font-medium text-[#C6A66B] tabular-nums';
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
                label.className = 'abc-surface p-2.5 flex items-center justify-between gap-2 cursor-pointer hover:border-[#C6A66B]/50';

                const left = document.createElement('span');
                left.className = 'flex items-center gap-2 text-sm';

                const cb = document.createElement('input');
                cb.type = 'checkbox';
                cb.value = String(a.id);
                cb.className = 'accent-[#C6A66B]';
                cb.addEventListener('change', updateConfiguratorPreview);

                const txt = document.createElement('span');
                txt.textContent = a.name;
                left.appendChild(cb);
                left.appendChild(txt);

                const price = document.createElement('span');
                price.className = 'text-xs font-medium text-[#BDB5AA] tabular-nums';
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

    categoryButtons.forEach((btn) => {
        btn.addEventListener('click', () => {
            activeCategory = btn.dataset.posCategory || 'all';
            categoryButtons.forEach((other) => {
                const active = other === btn;
                other.setAttribute('aria-pressed', active ? 'true' : 'false');
                other.classList.toggle('bg-[#C6A66B]', active);
                other.classList.toggle('text-[#111110]', active);
                other.classList.toggle('font-semibold', active);
                other.classList.toggle('bg-[#24211D]', !active);
                other.classList.toggle('text-[#BDB5AA]', !active);
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
        if (cashInput) cashInput.value = '';
        clearError();
        renderCart();
    });

    cashInput?.addEventListener('input', updatePaymentSummary);

    document.querySelectorAll('[data-cash-quick]').forEach((btn) => {
        btn.addEventListener('click', () => {
            if (!cashInput) return;
            const mode = btn.dataset.cashQuick;
            if (mode === 'exact') {
                const { totalCentavos } = computeTotals();
                cashInput.value = (totalCentavos / 100).toFixed(2);
            } else {
                cashInput.value = Number(mode).toFixed(2);
            }
            updatePaymentSummary();
        });
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

    mobileCartToggle?.addEventListener('click', () => {
        orderPanel?.classList.remove('hidden');
        orderPanel?.classList.add('fixed', 'inset-0', 'z-50', 'overflow-y-auto', 'p-4', 'bg-[#111110]');
    });
    mobileCartClose?.addEventListener('click', () => {
        orderPanel?.classList.add('hidden');
        orderPanel?.classList.remove('fixed', 'inset-0', 'z-50', 'overflow-y-auto', 'p-4', 'bg-[#111110]');
    });

    submitBtn?.addEventListener('click', async () => {
        if (isSubmitting || cart.length === 0) return;

        isSubmitting = true;
        clearError();
        submitBtn.textContent = 'Completing sale...';
        updatePaymentSummary();

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
            if (cashInput) cashInput.value = '';
            if (customerSelect) customerSelect.value = '';
            renderCart();

            orderPanel?.classList.add('hidden');
            orderPanel?.classList.remove('fixed', 'inset-0', 'z-50', 'overflow-y-auto', 'p-4', 'bg-[#111110]');

            if (receiptDialog && receiptSummary && data.order) {
                const ord = data.order;
                receiptSummary.innerHTML = '';

                const meta = document.createElement('div');
                meta.className = 'space-y-1 text-sm mb-3';
                meta.innerHTML = `
                    <div class="flex justify-between"><span class="text-[#BDB5AA]">Receipt</span><span class="font-semibold text-[#C6A66B] tabular-nums"></span></div>
                    <div class="flex justify-between"><span class="text-[#BDB5AA]">Customer</span><span></span></div>
                    <div class="flex justify-between"><span class="text-[#BDB5AA]">Cashier</span><span></span></div>
                    <div class="flex justify-between"><span class="text-[#BDB5AA]">Total</span><span class="font-semibold tabular-nums"></span></div>
                    <div class="flex justify-between"><span class="text-[#BDB5AA]">Cash</span><span class="tabular-nums"></span></div>
                    <div class="flex justify-between"><span class="text-[#BDB5AA]">Change</span><span class="font-semibold text-[#58B987] tabular-nums"></span></div>
                `;
                const spans = meta.querySelectorAll('span:last-child');
                spans[0].textContent = ord.order_number;
                spans[1].textContent = ord.customer_name;
                spans[2].textContent = ord.staff_name;
                spans[3].textContent = ord.total_formatted;
                spans[4].textContent = ord.cash_formatted;
                spans[5].textContent = ord.change_formatted;

                receiptSummary.appendChild(meta);
                if (receiptPrintLink) receiptPrintLink.href = ord.receipt_url;
                receiptDialog.showModal();
            }
        } catch {
            showError('Network error while submitting sale. Retry safely — your checkout token prevents duplicate charges.');
        } finally {
            isSubmitting = false;
            submitBtn.textContent = 'Complete Sale';
            updatePaymentSummary();
        }
    });

    receiptNewOrderBtn?.addEventListener('click', () => receiptDialog?.close());

    renderCart();
});
