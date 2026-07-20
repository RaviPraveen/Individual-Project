<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name') }} — {{ __('Welcome') }}</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;700;800&display=swap" rel="stylesheet">
        <link href="{{ asset('css/app.css') }}" rel="stylesheet">
        <style>
            html, body { height: 100%; overflow: hidden; }
            body {
                display: flex; flex-direction: column;
                background: linear-gradient(160deg, var(--pos-brand-dark) 0%, var(--pos-brand) 45%, #0D4E30 100%);
                color: #fff;
            }
            .cd-header {
                display: flex; align-items: center; justify-content: center; gap: 12px;
                padding: 18px 0 10px; flex-shrink: 0;
            }
            .cd-header .mark {
                width: 40px; height: 40px; border-radius: 10px; background: rgba(255,255,255,.15);
                display: flex; align-items: center; justify-content: center; font-size: 1.3rem;
            }
            .cd-header .name { font-size: 1.3rem; font-weight: 700; letter-spacing: .01em; }
            .cd-stage { flex: 1; min-height: 0; display: flex; flex-direction: column; }

            /* idle */
            .cd-idle { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 20px; }
            .cd-idle .mark-lg { font-size: 5rem; margin-bottom: 18px; opacity: .95; }
            .cd-idle h1 { font-size: 2.4rem; font-weight: 700; margin-bottom: 10px; }
            .cd-idle p.tagline { font-size: 1.1rem; opacity: .85; margin-bottom: 40px; }
            .cd-tip {
                background: rgba(255,255,255,.12); border-radius: 999px; padding: 12px 28px;
                font-size: 1rem; display: inline-flex; align-items: center; gap: 10px;
                transition: opacity .4s ease;
            }

            /* active bill */
            .cd-bill { flex: 1; min-height: 0; display: flex; flex-direction: column; background: var(--pos-bg); color: var(--pos-ink); border-radius: 22px 22px 0 0; margin-top: 6px; }
            .cd-bill-header { padding: 20px 34px 10px; display: flex; justify-content: space-between; align-items: center; flex-shrink: 0; }
            .cd-bill-header .customer { font-size: 1.05rem; font-weight: 600; }
            .cd-bill-header .points { font-size: .95rem; color: var(--pos-gold-dark); font-weight: 700; }
            .cd-items { flex: 1; overflow-y: auto; padding: 0 34px; }
            .cd-item-row { display: flex; justify-content: space-between; align-items: baseline; padding: 14px 0; border-bottom: 1px solid var(--pos-border); font-size: 1.15rem; }
            .cd-item-row .name { font-weight: 600; }
            .cd-item-row .meta { color: var(--pos-muted); font-size: .95rem; margin-left: 8px; }
            .cd-item-row .amount { font-weight: 700; font-variant-numeric: tabular-nums; }
            .cd-summary { flex-shrink: 0; padding: 16px 34px 26px; border-top: 1px solid var(--pos-border); }
            .cd-summary .row-line { display: flex; justify-content: space-between; font-size: 1rem; color: var(--pos-muted); padding: 2px 0; }
            .cd-summary .row-total { display: flex; justify-content: space-between; font-size: 2rem; font-weight: 800; color: var(--pos-brand-dark); padding-top: 8px; }

            /* thank you */
            .cd-thanks { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 20px; }
            .cd-thanks .check { width: 96px; height: 96px; border-radius: 50%; background: rgba(255,255,255,.15); display: flex; align-items: center; justify-content: center; font-size: 3rem; margin-bottom: 18px; }
            .cd-thanks h1 { font-size: 2.2rem; font-weight: 700; margin-bottom: 6px; }
            .cd-thanks .invoice { opacity: .8; font-size: .95rem; margin-bottom: 24px; font-family: monospace; }
            .cd-thanks .points-earned {
                background: var(--pos-gold-light); color: var(--pos-gold-dark); border-radius: 18px;
                padding: 18px 40px; font-size: 2.2rem; font-weight: 800;
            }
            .cd-thanks .points-note { margin-top: 10px; font-size: 1rem; opacity: .9; }

            .d-none-cd { display: none !important; }
        </style>
    </head>
    <body>
        <div class="cd-header">
            <span class="mark">🛒</span>
            <span class="name">{{ config('app.name') }}</span>
        </div>

        <div class="cd-stage">
            <div class="cd-idle" id="cd-idle">
                <div class="mark-lg">🛒</div>
                <h1>{{ __('Welcome to :name', ['name' => config('app.name')]) }}</h1>
                <p class="tagline">{{ __('Batticaloa, Sri Lanka') }}</p>
                <div class="cd-tip" id="cd-tip">
                    <i class="bi bi-star-fill"></i>
                    <span id="cd-tip-text"></span>
                </div>
            </div>

            <div class="cd-bill d-none-cd" id="cd-bill">
                <div class="cd-bill-header">
                    <div class="customer" id="cd-customer-name">{{ __('Your Order') }}</div>
                    <div class="points" id="cd-customer-points"></div>
                </div>
                <div class="cd-items" id="cd-items"></div>
                <div class="cd-summary">
                    <div class="row-line"><span>{{ __('Subtotal') }}</span><span id="cd-subtotal">0.00</span></div>
                    <div class="row-line" id="cd-discount-row"><span>{{ __('Discount') }}</span><span id="cd-discount">0.00</span></div>
                    <div class="row-line" id="cd-tax-row"><span>{{ __('Tax') }}</span><span id="cd-tax">0.00</span></div>
                    <div class="row-total"><span>{{ __('Total') }}</span><span id="cd-total">0.00</span></div>
                </div>
            </div>

            <div class="cd-thanks d-none-cd" id="cd-thanks">
                <div class="check"><i class="bi bi-check-lg"></i></div>
                <h1 id="cd-thanks-title">{{ __('Thank you!') }}</h1>
                <div class="invoice" id="cd-thanks-invoice"></div>
                <div class="points-earned d-none-cd" id="cd-thanks-points-wrap">
                    <span id="cd-thanks-points"></span>
                </div>
                <div class="points-note d-none-cd" id="cd-thanks-note">{{ __('star points earned today') }}</div>
            </div>
        </div>

        <script>
            const dataUrl = '{{ route('cashier.display.data') }}';
            const tips = [
                {!! json_encode(__('Earn 1 Star Point for every Rs. 100 you spend.')) !!},
                {!! json_encode(__('Redeem your points anytime — just tell your cashier.')) !!},
                {!! json_encode(__('We accept Cash, Card and other payment methods.')) !!},
                {!! json_encode(__('Ask our cashier to enrol you in Star Points today!')) !!},
            ];
            let tipIndex = 0;
            const tipEl = document.getElementById('cd-tip-text');

            function rotateTip() {
                tipEl.style.opacity = 0;
                setTimeout(() => {
                    tipIndex = (tipIndex + 1) % tips.length;
                    tipEl.textContent = tips[tipIndex];
                    tipEl.style.opacity = 1;
                }, 400);
            }
            tipEl.textContent = tips[0];
            setInterval(rotateTip, 5000);

            const stages = {
                idle: document.getElementById('cd-idle'),
                active: document.getElementById('cd-bill'),
                completed: document.getElementById('cd-thanks'),
            };

            function showStage(name) {
                Object.entries(stages).forEach(([key, el]) => el.classList.toggle('d-none-cd', key !== name));
            }

            function renderActive(state) {
                document.getElementById('cd-customer-name').textContent = state.customer_name || '{{ __('Your Order') }}';

                const pointsEl = document.getElementById('cd-customer-points');
                if (state.points_balance !== null && state.points_balance !== undefined) {
                    let pointsText = '★ ' + state.points_balance + ' {{ __('points') }}';
                    if (state.points_preview) {
                        pointsText += ' · {{ __('will earn') }} +' + state.points_preview;
                    }
                    pointsEl.textContent = pointsText;
                } else {
                    pointsEl.textContent = '';
                }

                const itemsEl = document.getElementById('cd-items');
                itemsEl.innerHTML = (state.items || []).map(item => `
                    <div class="cd-item-row">
                        <div><span class="name">${escapeHtml(item.name)}</span><span class="meta">&times;${item.quantity}</span></div>
                        <div class="amount">${Number(item.line_total).toFixed(2)}</div>
                    </div>
                `).join('');

                document.getElementById('cd-subtotal').textContent = Number(state.subtotal).toFixed(2);
                document.getElementById('cd-discount').textContent = Number(state.discount).toFixed(2);
                document.getElementById('cd-tax').textContent = Number(state.tax).toFixed(2);
                document.getElementById('cd-total').textContent = Number(state.total).toFixed(2);
                document.getElementById('cd-discount-row').classList.toggle('d-none-cd', !(state.discount > 0));
                document.getElementById('cd-tax-row').classList.toggle('d-none-cd', !(state.tax > 0));
            }

            function renderCompleted(state) {
                document.getElementById('cd-thanks-title').textContent = state.customer_name
                    ? `{{ __('Thank you, ') }}${state.customer_name}!`
                    : '{{ __('Thank you for shopping with us!') }}';
                document.getElementById('cd-thanks-invoice').textContent = state.invoice_no + '  ·  Rs. ' + Number(state.total).toFixed(2);

                const pointsWrap = document.getElementById('cd-thanks-points-wrap');
                const note = document.getElementById('cd-thanks-note');
                if (state.points_earned > 0) {
                    document.getElementById('cd-thanks-points').textContent = '+' + state.points_earned + ' ★';
                    pointsWrap.classList.remove('d-none-cd');
                    note.classList.remove('d-none-cd');
                    note.textContent = (state.points_balance !== null && state.points_balance !== undefined)
                        ? '{{ __('star points earned today · balance: ') }}' + state.points_balance
                        : '{{ __('star points earned today') }}';
                } else {
                    pointsWrap.classList.add('d-none-cd');
                    note.classList.add('d-none-cd');
                }
            }

            function escapeHtml(str) {
                const div = document.createElement('div');
                div.textContent = str;
                return div.innerHTML;
            }

            function poll() {
                fetch(dataUrl, { headers: { 'Accept': 'application/json' } })
                    .then(r => r.json())
                    .then(state => {
                        showStage(state.status || 'idle');
                        if (state.status === 'active') renderActive(state);
                        if (state.status === 'completed') renderCompleted(state);
                    })
                    .catch(() => {});
            }

            poll();
            setInterval(poll, 1500);
        </script>
    </body>
</html>
