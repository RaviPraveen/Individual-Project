(function () {
    'use strict';

    function initializeReturns() {
        const root = document.getElementById('customer-returns');
        if (!root || root.dataset.ready === '1') return;
        root.dataset.ready = '1';

        const input = root.querySelector('#invoice-lookup');
        const lookupButton = root.querySelector('#invoice-lookup-btn');
        const lookupArea = root.querySelector('#invoice-lookup-area');
        const results = root.querySelector('#invoice-results');
        const lookupError = root.querySelector('#lookup-error');
        const formCard = root.querySelector('#return-form-card');
        const form = root.querySelector('#return-form');
        const saleId = root.querySelector('#sale_id_input');
        const itemRows = root.querySelector('#return-items-body');
        const itemInputs = root.querySelector('#items-inputs');
        const estimatedRefund = root.querySelector('#estimated-refund');
        const refundMethod = root.querySelector('#refund_method');
        const formError = root.querySelector('#return-form-error');
        const processButton = root.querySelector('#process-return-btn');
        const lookupButtonHtml = lookupButton.innerHTML;
        const processButtonHtml = processButton.innerHTML;

        let currentSale = null;
        let searchTimer = null;
        let searchRequest = null;
        let lookupRequest = null;
        let version = 0;
        let matches = [];
        let activeMatch = -1;

        function message(name) {
            return root.dataset['message' + name];
        }

        function money(value) {
            return (Number(value) || 0).toFixed(2);
        }

        function normalizeInvoice(value) {
            const normalized = value.trim().toUpperCase()
                .replace(/[\u2010-\u2015\u2212]/g, '-')
                .replace(/\s+/g, '');
            const compact = normalized.replace(/-/g, '');
            return /^INV\d{12,}$/.test(compact)
                ? compact.replace(/^INV(\d{8})(\d{4,})$/, 'INV-$1-$2') : normalized;
        }

        function showError(element, text) {
            element.textContent = text;
            element.classList.toggle('d-none', !text);
        }

        function hideMatches() {
            results.replaceChildren();
            results.classList.add('d-none');
            input.setAttribute('aria-expanded', 'false');
            input.removeAttribute('aria-activedescendant');
            matches = [];
            activeMatch = -1;
        }

        function resetSale() {
            currentSale = null;
            form.reset();
            saleId.value = '';
            itemRows.replaceChildren();
            itemInputs.replaceChildren();
            estimatedRefund.textContent = '0.00';
            formCard.classList.add('d-none');
            processButton.disabled = true;
            processButton.innerHTML = processButtonHtml;
            showError(formError, '');
        }

        function cancelRequests() {
            version++;
            clearTimeout(searchTimer);
            searchRequest?.abort();
            lookupRequest?.abort();
            searchRequest = null;
            lookupRequest = null;
            lookupButton.disabled = false;
            lookupButton.innerHTML = lookupButtonHtml;
            lookupArea.removeAttribute('aria-busy');
        }

        async function getJson(url, signal) {
            const response = await fetch(url, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                cache: 'no-store',
                signal,
            });

            if (response.redirected || response.status === 401 || response.status === 419) {
                throw new Error(message('SessionExpired'));
            }
            if (!(response.headers.get('content-type') || '').includes('application/json')) {
                throw new Error(message('LookupError'));
            }

            const data = await response.json();
            if (!response.ok) {
                throw new Error(response.status === 404 ? message('NotFound') : (data.message || message('LookupError')));
            }
            return data;
        }

        function displayMatches(sales) {
            hideMatches();
            if (document.activeElement !== input) return;

            matches = sales;
            if (!sales.length) {
                const notice = document.createElement('div');
                notice.className = 'list-group-item text-muted small';
                notice.textContent = message('NoMatches');
                results.appendChild(notice);
            }
            sales.forEach((sale, index) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.id = 'invoice-match-' + index;
                button.className = 'list-group-item list-group-item-action text-start';
                button.setAttribute('role', 'option');
                button.setAttribute('aria-selected', 'false');

                const title = document.createElement('div');
                title.className = 'fw-semibold font-monospace';
                title.textContent = sale.invoice_no;
                const details = document.createElement('div');
                details.className = 'small text-muted';
                details.textContent = `${sale.date} | ${sale.customer_name} | Rs ${money(sale.total)}`;
                button.append(title, details);
                button.addEventListener('mousedown', event => event.preventDefault());
                button.addEventListener('click', () => selectMatch(index));
                results.appendChild(button);
            });
            results.classList.remove('d-none');
            input.setAttribute('aria-expanded', 'true');
        }

        function selectMatch(index) {
            const sale = matches[index];
            if (!sale) return;
            input.value = sale.invoice_no;
            performLookup();
        }

        function moveSelection(direction) {
            if (!matches.length) return;
            activeMatch = activeMatch < 0 ? (direction > 0 ? 0 : matches.length - 1)
                : (activeMatch + direction + matches.length) % matches.length;
            results.querySelectorAll('[role="option"]').forEach((button, index) => {
                const active = index === activeMatch;
                button.classList.toggle('active', active);
                button.setAttribute('aria-selected', String(active));
                if (active) {
                    input.setAttribute('aria-activedescendant', button.id);
                    button.scrollIntoView({ block: 'nearest' });
                }
            });
        }

        async function searchInvoices(query, requestVersion) {
            searchRequest = new AbortController();
            try {
                const url = new URL(root.dataset.searchUrl, window.location.href);
                url.searchParams.set('q', query);
                const data = await getJson(url, searchRequest.signal);
                if (version !== requestVersion) return;

                const sales = Array.isArray(data.sales) ? data.sales : [];
                const exact = sales.find(sale => normalizeInvoice(sale.invoice_no) === query);
                if (exact) {
                    input.value = exact.invoice_no;
                    performLookup();
                    return;
                }
                displayMatches(sales);
            } catch (error) {
                if (error.name === 'AbortError' || version !== requestVersion) return;
                hideMatches();
                showError(lookupError, error instanceof TypeError ? message('LookupError') : (error.message || message('LookupError')));
            }
        }

        async function performLookup() {
            const invoice = normalizeInvoice(input.value);
            cancelRequests();
            hideMatches();
            resetSale();
            showError(lookupError, '');
            if (!invoice) {
                showError(lookupError, message('EnterInvoice'));
                input.focus();
                return;
            }

            input.value = invoice;
            const requestVersion = version;
            lookupRequest = new AbortController();
            lookupButton.disabled = true;
            lookupButton.textContent = message('Loading');
            lookupArea.setAttribute('aria-busy', 'true');
            try {
                const url = new URL(root.dataset.lookupUrl, window.location.href);
                url.searchParams.set('invoice_no', invoice);
                const sale = await getJson(url, lookupRequest.signal);
                if (version !== requestVersion) return;
                if (!sale.sale_id || !Array.isArray(sale.items)) throw new Error(message('LookupError'));

                currentSale = sale;
                renderSale();
            } catch (error) {
                if (error.name === 'AbortError' || version !== requestVersion) return;
                resetSale();
                showError(lookupError, error instanceof TypeError ? message('LookupError') : (error.message || message('LookupError')));
            } finally {
                if (version === requestVersion) {
                    lookupButton.disabled = false;
                    lookupButton.innerHTML = lookupButtonHtml;
                    lookupArea.removeAttribute('aria-busy');
                }
            }
        }

        function renderSale() {
            saleId.value = currentSale.sale_id;
            root.querySelector('#sale-invoice-no').textContent = currentSale.invoice_no;
            root.querySelector('#sale-meta').textContent = `Date: ${currentSale.date} | Customer: ${currentSale.customer_name || 'Walk-in'} | Total: Rs ${money(currentSale.total)}`;
            refundMethod.value = ['cash', 'card', 'other'].includes(currentSale.payment_method) ? currentSale.payment_method : 'cash';

            currentSale.items.forEach(item => {
                const row = document.createElement('tr');
                const product = document.createElement('td');
                const name = document.createElement('div');
                name.className = 'fw-bold text-dark';
                name.textContent = item.product_name;
                const price = document.createElement('div');
                price.className = 'small text-muted';
                price.textContent = 'Price: Rs ' + money(item.unit_price);
                product.append(name, price);
                row.appendChild(product);
                [item.quantity, item.already_returned].forEach(value => {
                    const cell = document.createElement('td');
                    cell.className = 'text-end';
                    cell.textContent = value;
                    row.appendChild(cell);
                });
                const quantityCell = document.createElement('td');
                quantityCell.className = 'text-center';
                const quantity = document.createElement('input');
                quantity.type = 'number';
                quantity.className = 'form-control form-control-sm text-center fw-bold return-qty-input';
                quantity.min = '0';
                quantity.max = Math.max(0, Number(item.max_returnable) || 0);
                quantity.step = '1';
                quantity.value = '0';
                quantity.disabled = Number(quantity.max) === 0;
                quantity.dataset.itemId = item.sale_item_id;
                quantity.dataset.unitPrice = item.unit_price;
                quantity.setAttribute('aria-label', 'Quantity to return: ' + item.product_name);
                quantityCell.appendChild(quantity);
                row.appendChild(quantityCell);
                itemRows.appendChild(row);
            });
            formCard.classList.remove('d-none');
            rebuildInputs();
            if (!currentSale.items.some(item => Number(item.max_returnable) > 0)) {
                showError(formError, message('FullyReturned'));
            }
        }

        function rebuildInputs() {
            itemInputs.replaceChildren();
            if (!currentSale) return;
            const roundMoney = value => Math.round((value + Number.EPSILON) * 100) / 100;
            const originalSubtotal = Number(currentSale.subtotal) || 0;
            const discountRatio = originalSubtotal > 0 ? (Number(currentSale.discount) || 0) / originalSubtotal : 0;
            let total = 0;
            let selected = false;

            itemRows.querySelectorAll('.return-qty-input').forEach((quantity, index) => {
                const count = Math.max(0, Math.min(Number(quantity.max) || 0, Math.floor(Number(quantity.value) || 0)));
                if (quantity.value !== '') quantity.value = count;
                if (!count) return;
                selected = true;
                [['sale_item_id', quantity.dataset.itemId], ['quantity', count]].forEach(([name, value]) => {
                    const hidden = document.createElement('input');
                    hidden.type = 'hidden';
                    hidden.name = `items[${index}][${name}]`;
                    hidden.value = value;
                    itemInputs.appendChild(hidden);
                });
                const subtotal = roundMoney(count * (Number(quantity.dataset.unitPrice) || 0));
                const discount = roundMoney(subtotal * discountRatio);
                const tax = roundMoney((subtotal - discount) * (Number(currentSale.refund_tax_percent) || 0) / 100);
                const redemption = originalSubtotal > 0 ? roundMoney((Number(currentSale.redemption_value) || 0) * subtotal / originalSubtotal) : 0;
                total += subtotal - discount + tax - redemption;
            });
            estimatedRefund.textContent = money(Math.max(0, total));
            processButton.disabled = !selected;
            if (selected) showError(formError, '');
        }

        input.addEventListener('input', () => {
            cancelRequests();
            hideMatches();
            resetSale();
            showError(lookupError, '');
            const query = normalizeInvoice(input.value);
            if (query.length < 2) return;
            const requestVersion = version;
            searchTimer = setTimeout(() => searchInvoices(query, requestVersion), 250);
        });
        lookupButton.addEventListener('click', performLookup);
        input.addEventListener('keydown', event => {
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                moveSelection(event.key === 'ArrowDown' ? 1 : -1);
            } else if (event.key === 'Enter') {
                event.preventDefault();
                if (activeMatch >= 0) selectMatch(activeMatch);
                else if (matches.length === 1) selectMatch(0);
                else performLookup();
            } else if (event.key === 'Escape') {
                cancelRequests();
                hideMatches();
            }
        });
        document.addEventListener('click', event => {
            if (!lookupArea.contains(event.target)) hideMatches();
        });
        ['input', 'change'].forEach(type => itemRows.addEventListener(type, event => {
            if (event.target.classList.contains('return-qty-input')) rebuildInputs();
        }));
        root.querySelector('#cancel-return-btn').addEventListener('click', () => {
            cancelRequests();
            hideMatches();
            resetSale();
            showError(lookupError, '');
            input.value = '';
            input.focus();
        });
        form.addEventListener('submit', event => {
            if (!currentSale || !itemInputs.querySelector('input[name$="[quantity]"]')) {
                event.preventDefault();
                showError(formError, message('SelectItems'));
                return;
            }
            processButton.disabled = true;
            processButton.textContent = message('Processing');
        });
        window.addEventListener('pageshow', event => {
            if (event.persisted && input.value.trim()) performLookup();
        });
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initializeReturns);
    else initializeReturns();
})();
