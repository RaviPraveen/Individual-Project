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
                display: flex; font-family: 'Manrope', 'Inter', sans-serif; color: var(--pos-ink, #0F172A);
                background: #F8FAFC;
            }

            /* Panel widths change with the mode — Promotion Mode is 40/60,
               Billing Mode (a sale is actively being rung up) is 65/35.
               Only .cd-left's explicit width ever changes; .cd-right is
               always flex-grow:1 (never its own basis/grow override) and
               just fills whatever's left — animating flex-basis on BOTH
               sides while flex-grow also flips between them produces an
               unpredictable mid-transition blend in some browsers. */
            .cd-left { height: 100vh; display: flex; flex-direction: column; flex: 0 0 auto; width: 40%; transition: width 400ms ease; }
            .cd-right { height: 100vh; flex: 1 1 auto; min-width: 0; display: flex; flex-direction: column; }
            body.mode-billing .cd-left { width: 65%; }

            /* ===== LEFT — brand/welcome (Promotion Mode) or live bill (Billing Mode) ===== */
            .cd-left {
                background: linear-gradient(160deg, #FFFFFF 0%, #F8FAFC 55%, #EEF6FF 100%);
                border-right: 1px solid #EEF2F7;
            }
            .cd-header {
                display: none; align-items: center; justify-content: center; gap: 12px;
                padding: 22px 0 6px; flex-shrink: 0;
            }
            /* The small top-bar brand mark is only useful once the centred
               welcome screen (which already shows a large logo) is gone —
               i.e. once Billing/Thank-you mode takes over the left panel. */
            body.mode-billing .cd-header { display: flex; }
            .cd-header .mark {
                width: 40px; height: 40px; border-radius: 12px; background: var(--pos-brand-gradient, linear-gradient(135deg,#3B82F6,#2563EB));
                display: flex; align-items: center; justify-content: center; font-size: 1.25rem;
                box-shadow: 0 6px 16px rgba(59,130,246,.3);
            }
            .cd-header .name { font-size: 1.25rem; font-weight: 800; letter-spacing: .01em; color: #0F172A; }
            .cd-stage { flex: 1; min-height: 0; display: flex; flex-direction: column; }

            /* --- Idle / welcome content (Promotion Mode) — brand only, no
               promo/offer/hours content, all of which already lives on the
               poster in the right panel. --- */
            .cd-idle { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 24px; }
            .cd-idle .mark-lg {
                width: 108px; height: 108px; border-radius: 30px; margin-bottom: 28px; font-size: 3rem;
                display: flex; align-items: center; justify-content: center;
                background: var(--pos-brand-gradient, linear-gradient(135deg,#3B82F6,#2563EB));
                box-shadow: 0 10px 26px rgba(59,130,246,.28);
            }
            .cd-idle h1 { font-size: 2rem; font-weight: 800; margin-bottom: 10px; color: #0F172A; }
            .cd-idle p.cd-idle-subtitle { font-size: 1.05rem; font-weight: 700; color: #2563EB; margin-bottom: 14px; }
            .cd-idle p.tagline { font-size: .95rem; color: #64748B; margin-bottom: 0; }

            /* --- Live bill (Billing Mode) --- */
            .cd-bill { flex: 1; min-height: 0; display: flex; flex-direction: column; }
            .cd-bill-header { padding: 18px 30px 10px; display: flex; justify-content: space-between; align-items: center; flex-shrink: 0; }
            .cd-bill-header .customer { font-size: 1.15rem; font-weight: 800; color: #0F172A; }
            .cd-bill-header .points { font-size: .9rem; color: #B45309; font-weight: 700; }
            .cd-items { flex: 1; overflow-y: auto; padding: 0 30px; }
            .cd-item-row { display: flex; justify-content: space-between; align-items: baseline; padding: 14px 0; border-bottom: 1px solid #EEF2F7; font-size: 1.15rem; }
            .cd-item-row .name { font-weight: 700; color: #0F172A; }
            .cd-item-row .meta { color: #94A3B8; font-size: .88rem; margin-left: 8px; }
            .cd-item-row .amount { font-weight: 800; font-variant-numeric: tabular-nums; color: #0F172A; }
            .cd-summary { flex-shrink: 0; padding: 16px 30px 26px; border-top: 1px solid #EEF2F7; background: #FFFFFF; }
            .cd-summary .row-line { display: flex; justify-content: space-between; font-size: 1rem; color: #64748B; padding: 3px 0; }
            .cd-summary .row-line.savings { color: #059669; font-weight: 700; }
            .cd-summary .row-total {
                display: flex; justify-content: space-between; align-items: baseline; font-size: 2.1rem; font-weight: 800;
                color: #0F172A; padding-top: 10px; margin-top: 6px; border-top: 1px dashed #E2E8F0;
            }

            /* --- Thank you --- */
            .cd-thanks { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 24px; }
            .cd-thanks .check {
                width: 84px; height: 84px; border-radius: 50%; background: #ECFDF5; color: #059669;
                display: flex; align-items: center; justify-content: center; font-size: 2.6rem; margin-bottom: 18px;
            }
            .cd-thanks h1 { font-size: 1.8rem; font-weight: 800; margin-bottom: 6px; color: #0F172A; }
            .cd-thanks .invoice { color: #94A3B8; font-size: .85rem; margin-bottom: 20px; font-family: monospace; }
            .cd-thanks .points-earned {
                background: #FEF3C7; color: #B45309; border-radius: 18px;
                padding: 14px 32px; font-size: 1.8rem; font-weight: 800;
            }
            .cd-thanks .points-note { margin-top: 10px; font-size: .9rem; color: #64748B; }

            .d-none-cd { display: none !important; }

            /* ===== RIGHT — promotion card ===== */
            .cd-right { background: #F1F5F9; padding: 28px; align-items: center; justify-content: center; }
            body.mode-billing .cd-right { padding: 18px; }

            /* The AI-generated poster already contains the product name,
               prices, discount and promotion design — this card exists only
               to frame the image, never to add text on top of it. */
            .cd-promo-card {
                width: 100%; height: 100%; max-height: 100%; background: #FFFFFF; border-radius: 24px;
                box-shadow: 0 12px 32px rgba(15,23,42,.08); overflow: hidden;
                display: flex; align-items: center; justify-content: center; padding: 22px;
                opacity: 0; transform: scale(.985); transition: opacity 450ms ease, transform 450ms ease;
            }
            .cd-promo-card.show { opacity: 1; transform: scale(1); }
            .cd-promo-card img {
                max-width: 100%; max-height: 100%; width: auto; height: auto;
                object-fit: contain; display: block; border-radius: 14px;
            }

            /* Compact padding for Billing Mode's narrower column */
            body.mode-billing .cd-promo-card { padding: 14px; }

            /* --- No active promotion --- */
            .cd-promo-empty {
                width: 100%; height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center;
                text-align: center; padding: 40px; background: #FFFFFF; border-radius: 24px; box-shadow: 0 12px 32px rgba(15,23,42,.06);
            }
            .cd-promo-empty .mark-lg {
                width: 88px; height: 88px; border-radius: 24px; margin-bottom: 18px; font-size: 2.4rem;
                display: flex; align-items: center; justify-content: center;
                background: var(--pos-brand-gradient, linear-gradient(135deg,#3B82F6,#2563EB));
                box-shadow: 0 10px 26px rgba(59,130,246,.28);
            }
            .cd-promo-empty h1 { font-size: 1.5rem; font-weight: 800; margin-bottom: 8px; color: #0F172A; }
            .cd-promo-empty p.tagline { font-size: 1rem; color: #64748B; margin-bottom: 28px; }
            .cd-promo-empty .cd-thankyou { margin-top: 24px; font-size: 1rem; color: #94A3B8; font-weight: 600; }
        </style>
    </head>
    <body>
        <div class="cd-left">
            <div class="cd-header">
                <span class="mark">🛒</span>
                <span class="name">{{ config('app.name') }}</span>
            </div>

            <div class="cd-stage">
                <div class="cd-idle" id="cd-idle">
                    <div class="mark-lg">🛒</div>
                    <h1>{{ config('app.name') }}</h1>
                    <p class="cd-idle-subtitle">{{ __('Commercial SaaS POS') }}</p>
                    <p class="tagline">{{ __('Batticaloa, Sri Lanka') }}</p>
                </div>

                <div class="cd-bill d-none-cd" id="cd-bill">
                    <div class="cd-bill-header">
                        <div class="customer" id="cd-customer-name">{{ __('Your Order') }}</div>
                        <div class="points" id="cd-customer-points"></div>
                    </div>
                    <div class="cd-items" id="cd-items"></div>
                    <div class="cd-summary">
                        <div class="row-line"><span>{{ __('Subtotal') }}</span><span id="cd-subtotal">0.00</span></div>
                        <div class="row-line savings d-none-cd" id="cd-discount-row"><span>{{ __('You Save') }}</span><span id="cd-discount">0.00</span></div>
                        <div class="row-line" id="cd-tax-row"><span>{{ __('Tax') }}</span><span id="cd-tax">0.00</span></div>
                        <div class="row-line" id="cd-bag-fee-row"><span>{{ __('Bag Fee') }}</span><span id="cd-bag-fee">0.00</span></div>
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
        </div>

        <div class="cd-right">
            <div class="cd-promo-card" id="cd-promo-card">
                <img id="cd-promo-image" alt="">
            </div>

            <div class="cd-promo-empty d-none-cd" id="cd-promo-empty">
                <div class="mark-lg">🛒</div>
                <h1>{{ __('Welcome to :name', ['name' => config('app.name')]) }}</h1>
                <p class="tagline">{{ __('Your neighbourhood supermarket in Batticaloa, Sri Lanka') }}</p>
                <div class="cd-thankyou">{{ __('Thank you for shopping with us!') }}</div>
            </div>
        </div>

        <script>
            const dataUrl = '{{ route('cashier.display.data') }}';
            const promotionsUrl = '{{ route('cashier.display.promotions') }}';
            const viewedUrlBase = '{{ url('/cashier/display/promotions') }}';
            const csrfToken = '{{ csrf_token() }}';

            const stages = {
                idle: document.getElementById('cd-idle'),
                active: document.getElementById('cd-bill'),
                completed: document.getElementById('cd-thanks'),
            };

            function showStage(name) {
                Object.entries(stages).forEach(([key, el]) => el.classList.toggle('d-none-cd', key !== name));
            }

            // Billing Mode (wide bill / narrow promo card) is driven purely by
            // whether a sale is actively being rung up — no backend change
            // needed, this just reacts to the status the existing poll already
            // returns.
            function setMode(mode) {
                document.body.classList.toggle('mode-billing', mode === 'billing');
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
                document.getElementById('cd-bag-fee').textContent = Number(state.bag_fee || 0).toFixed(2);
                document.getElementById('cd-total').textContent = Number(state.total).toFixed(2);
                document.getElementById('cd-discount-row').classList.toggle('d-none-cd', !(state.discount > 0));
                document.getElementById('cd-tax-row').classList.toggle('d-none-cd', !(state.tax > 0));
                document.getElementById('cd-bag-fee-row').classList.toggle('d-none-cd', !(state.bag_fee > 0));
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

            // "Thank you" is shown for a fixed 5s regardless of how long the
            // backend keeps the completed-sale cache entry alive (15s, so
            // there's a repeat of the SAME invoice on the next couple of
            // polls) — dedupe by invoice_no so it doesn't restart the timer,
            // then force Promotion Mode locally once the 5s window is up.
            let lastCompletedInvoice = null;
            let thankYouTimer = null;

            function poll() {
                fetch(dataUrl, { headers: { 'Accept': 'application/json' } })
                    .then(r => r.json())
                    .then(state => {
                        const status = state.status || 'idle';

                        if (status === 'completed') {
                            setMode('billing');
                            if (state.invoice_no !== lastCompletedInvoice) {
                                lastCompletedInvoice = state.invoice_no;
                                showStage('completed');
                                renderCompleted(state);
                                clearTimeout(thankYouTimer);
                                thankYouTimer = setTimeout(() => {
                                    showStage('idle');
                                    setMode('promo');
                                }, 5000);
                            }
                            return;
                        }

                        lastCompletedInvoice = null;

                        if (status === 'active') {
                            clearTimeout(thankYouTimer);
                            showStage('active');
                            renderActive(state);
                            setMode('billing');
                            return;
                        }

                        clearTimeout(thankYouTimer);
                        showStage('idle');
                        setMode('promo');
                    })
                    .catch(() => {});
            }

            poll();
            setInterval(poll, 1500);

            /* ---------- Promotion rotation (right panel) ---------- */
            const promoCard = document.getElementById('cd-promo-card');
            const promoEmpty = document.getElementById('cd-promo-empty');
            let playlist = [];
            let playIndex = 0;
            let rotateTimer = null;

            // Featured promotions are simply repeated in the playlist so
            // they come up roughly twice as often — each item still keeps
            // its own display_duration, so there's never one global timer.
            function buildPlaylist(promotions) {
                const list = [];
                promotions.forEach(p => {
                    list.push(p);
                    if (p.is_featured) list.push(p);
                });
                return list;
            }

            function markViewed(promo) {
                fetch(`${viewedUrlBase}/${promo.id}/viewed`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken },
                }).catch(() => {});
            }

            // The AI-generated poster already contains the product name,
            // prices and discount, so the display only ever swaps the image.
            function showPromo(promo) {
                promoEmpty.classList.add('d-none-cd');
                promoCard.classList.remove('show');

                setTimeout(() => {
                    document.getElementById('cd-promo-image').src = promo.poster_url || '';
                    promoCard.classList.add('show');
                    markViewed(promo);
                }, 350);
            }

            function showEmptyPromoState() {
                promoCard.classList.remove('show');
                setTimeout(() => {
                    promoCard.classList.add('d-none-cd');
                    promoEmpty.classList.remove('d-none-cd');
                }, 350);
            }

            function scheduleNext() {
                clearTimeout(rotateTimer);

                if (playlist.length === 0) {
                    showEmptyPromoState();
                    return;
                }

                promoCard.classList.remove('d-none-cd');
                const promo = playlist[playIndex % playlist.length];
                showPromo(promo);
                playIndex++;
                rotateTimer = setTimeout(scheduleNext, Math.max(5, promo.display_duration || 10) * 1000);
            }

            function refreshPromotions() {
                fetch(promotionsUrl, { headers: { 'Accept': 'application/json' } })
                    .then(r => r.json())
                    .then(data => {
                        const promotions = data.promotions || [];
                        const wasEmpty = playlist.length === 0;
                        playlist = buildPlaylist(promotions);

                        if (wasEmpty && playlist.length > 0) {
                            playIndex = 0;
                            scheduleNext();
                        } else if (playlist.length === 0) {
                            clearTimeout(rotateTimer);
                            showEmptyPromoState();
                        }
                    })
                    .catch(() => {});
            }

            refreshPromotions();
            showEmptyPromoState();
            setInterval(refreshPromotions, 30000);
        </script>
    </body>
</html>
