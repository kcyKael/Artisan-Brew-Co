function formatPhp(centavos) {
    const abs = Math.abs(centavos);
    return (centavos < 0 ? '-' : '') + '₱' + (abs / 100).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('record-sale-form');
    if (!root) return;

    const products = JSON.parse(root.dataset.products || '{}');
    const oldVariantId = root.dataset.oldVariant || '';
    const oldAddons = JSON.parse(root.dataset.oldAddons || '[]').map(Number);

    const productSelect = document.getElementById('rs-product-id');
    const variantWrap = document.getElementById('rs-variant-wrap');
    const variantSelect = document.getElementById('rs-variant-id');
    const addonsWrap = document.getElementById('rs-addons-wrap');
    const addonsList = document.getElementById('rs-addons-list');
    const qtyInput = document.getElementById('rs-quantity');
    const cashInput = document.getElementById('rs-cash-received');
    const stockHint = document.getElementById('rs-stock-hint');
    const unitPreview = document.getElementById('rs-unit-preview');
    const totalPreview = document.getElementById('rs-total-preview');

    function updateTotals() {
        const product = products[productSelect?.value];
        if (!product) {
            if (stockHint) stockHint.textContent = 'Select a product to view live stock.';
            if (unitPreview) unitPreview.textContent = '₱0.00';
            if (totalPreview) totalPreview.textContent = '₱0.00';
            return;
        }

        let unit = product.price_centavos;
        const selectedVariant = product.variants.find((v) => String(v.id) === String(variantSelect?.value));
        if (selectedVariant) {
            unit += selectedVariant.adjustment_centavos;
        }

        addonsList?.querySelectorAll('input[type="checkbox"]:checked').forEach((cb) => {
            const addon = product.addons.find((a) => String(a.id) === String(cb.value));
            if (addon) unit += addon.price_centavos;
        });

        const qty = Math.max(0, Number.parseInt(qtyInput?.value || '0', 10) || 0);
        const total = unit * qty;

        if (stockHint) {
            stockHint.textContent = `Available stock: ${product.stock_quantity} serving(s)`;
        }
        if (unitPreview) unitPreview.textContent = formatPhp(unit);
        if (totalPreview) totalPreview.textContent = formatPhp(total);

        if (cashInput && (!cashInput.value || cashInput.dataset.autoFilled === 'true')) {
            if (total > 0) {
                cashInput.value = (total / 100).toFixed(2);
                cashInput.dataset.autoFilled = 'true';
            }
        }
    }

    function renderOptions(useOldValues = false) {
        const product = products[productSelect?.value];

        if (variantSelect && variantWrap) {
            variantSelect.innerHTML = '';
            if (!product || product.variants.length === 0) {
                variantWrap.classList.add('hidden');
            } else {
                variantWrap.classList.remove('hidden');
                product.variants.forEach((v, idx) => {
                    const opt = document.createElement('option');
                    opt.value = String(v.id);
                    const adj = v.adjustment_centavos > 0 ? ` (+${formatPhp(v.adjustment_centavos)})` : ' (Included)';
                    opt.textContent = `${v.label}${adj}`;
                    if (useOldValues && String(v.id) === oldVariantId) {
                        opt.selected = true;
                    } else if (!useOldValues && idx === 0) {
                        opt.selected = true;
                    }
                    variantSelect.appendChild(opt);
                });
            }
        }

        if (addonsList && addonsWrap) {
            addonsList.innerHTML = '';
            if (!product || product.addons.length === 0) {
                addonsWrap.classList.add('hidden');
            } else {
                addonsWrap.classList.remove('hidden');
                product.addons.forEach((a) => {
                    const label = document.createElement('label');
                    label.className = 'abc-surface px-3 py-2 flex items-center justify-between gap-2 cursor-pointer text-sm';

                    const left = document.createElement('span');
                    left.className = 'flex items-center gap-2';

                    const cb = document.createElement('input');
                    cb.type = 'checkbox';
                    cb.name = 'addon_ids[]';
                    cb.value = String(a.id);
                    cb.className = 'accent-[#D2B077]';
                    if (useOldValues && oldAddons.includes(a.id)) {
                        cb.checked = true;
                    }
                    cb.addEventListener('change', updateTotals);

                    const name = document.createElement('span');
                    name.textContent = a.name;

                    left.appendChild(cb);
                    left.appendChild(name);

                    const price = document.createElement('span');
                    price.className = 'text-xs text-[#BFB4A5] tabular-nums';
                    price.textContent = `+${formatPhp(a.price_centavos)}`;

                    label.appendChild(left);
                    label.appendChild(price);
                    addonsList.appendChild(label);
                });
            }
        }

        updateTotals();
    }

    productSelect?.addEventListener('change', () => renderOptions(false));
    variantSelect?.addEventListener('change', updateTotals);
    qtyInput?.addEventListener('input', updateTotals);
    cashInput?.addEventListener('input', () => {
        cashInput.dataset.autoFilled = 'false';
    });

    renderOptions(true);
});
