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
            body { display: flex; color: #fff; }

            /* ===== LEFT — Customer Bill (40%) ===== */
            .cd-left {
                flex: 0 0 40%; max-width: 40%; height: 100vh; display: flex; flex-direction: column;
                background: linear-gradient(160deg, var(--pos-brand-dark) 0%, var(--pos-brand) 45%, #0D4E30 100%);
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

            .cd-idle { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 20px; }
            .cd-idle .mark-lg { font-size: 4rem; margin-bottom: 16px; opacity: .95; }
            .cd-idle h1 { font-size: 1.7rem; font-weight: 700; margin-bottom: 8px; }
            .cd-idle p.tagline { font-size: 1rem; opacity: .85; margin-bottom: 30px; }
            .cd-tip {
                background: rgba(255,255,255,.12); border-radius: 999px; padding: 10px 22px;
                font-size: .9rem; display: inline-flex; align-items: center; gap: 10px;
                transition: opacity .4s ease;
            }

            .cd-bill { flex: 1; min-height: 0; display: flex; flex-direction: column; background: var(--pos-bg); color: var(--pos-ink); border-radius: 22px 22px 0 0; margin-top: 6px; }
            .cd-bill-header { padding: 18px 26px 8px; display: flex; justify-content: space-between; align-items: center; flex-shrink: 0; }
            .cd-bill-header .customer { font-size: 1rem; font-weight: 600; }
            .cd-bill-header .points { font-size: .88rem; color: var(--pos-gold-dark); font-weight: 700; }
            .cd-items { flex: 1; overflow-y: auto; padding: 0 26px; }
            .cd-item-row { display: flex; justify-content: space-between; align-items: baseline; padding: 12px 0; border-bottom: 1px solid var(--pos-border); font-size: 1.02rem; }
            .cd-item-row .name { font-weight: 600; }
            .cd-item-row .meta { color: var(--pos-muted); font-size: .85rem; margin-left: 8px; }
            .cd-item-row .amount { font-weight: 700; font-variant-numeric: tabular-nums; }
            .cd-summary { flex-shrink: 0; padding: 14px 26px 22px; border-top: 1px solid var(--pos-border); }
            .cd-summary .row-line { display: flex; justify-content: space-between; font-size: .92rem; color: var(--pos-muted); padding: 2px 0; }
            .cd-summary .row-total { display: flex; justify-content: space-between; font-size: 1.7rem; font-weight: 800; color: var(--pos-brand-dark); padding-top: 8px; }

            .cd-thanks { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 20px; }
            .cd-thanks .check { width: 84px; height: 84px; border-radius: 50%; background: rgba(255,255,255,.15); display: flex; align-items: center; justify-content: center; font-size: 2.6rem; margin-bottom: 16px; }
            .cd-thanks h1 { font-size: 1.8rem; font-weight: 700; margin-bottom: 6px; }
            .cd-thanks .invoice { opacity: .8; font-size: .85rem; margin-bottom: 20px; font-family: monospace; }
            .cd-thanks .points-earned {
                background: var(--pos-gold-light); color: var(--pos-gold-dark); border-radius: 18px;
                padding: 14px 32px; font-size: 1.8rem; font-weight: 800;
            }
            .cd-thanks .points-note { margin-top: 10px; font-size: .9rem; opacity: .9; }

            .d-none-cd { display: none !important; }

            /* ===== RIGHT — Promotion Display (60%) ===== */
            .cd-right { flex: 1; height: 100vh; position: relative; background: #0F172A; overflow: hidden; }

            .cd-promo-slide {
                position: absolute; inset: 0; display: flex; flex-direction: column; justify-content: flex-end;
                opacity: 0; transition: opacity 500ms ease;
            }
            .cd-promo-slide.show { opacity: 1; }
            .cd-promo-slide img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
            .cd-promo-slide .cd-promo-scrim {
                position: absolute; inset: 0;
                background: linear-gradient(to top, rgba(15,23,42,.92) 0%, rgba(15,23,42,.35) 55%, rgba(15,23,42,0) 100%);
            }
            .cd-promo-slide .cd-promo-body { position: relative; padding: 56px 64px; }
            .cd-promo-pill {
                display: inline-flex; align-items: center; gap: 8px; background: var(--pos-gold-light, #FBBF24);
                color: #0F172A; font-weight: 700; font-size: .95rem; padding: 8px 18px; border-radius: 999px;
                margin-bottom: 20px; text-transform: uppercase; letter-spacing: .05em;
            }
            .cd-promo-title { font-size: 2.6rem; font-weight: 800; line-height: 1.15; margin-bottom: 18px; max-width: 85%; }
            .cd-promo-desc { font-size: 1.05rem; opacity: .85; margin-bottom: 20px; max-width: 80%; }
            .cd-promo-prices { display: flex; align-items: baseline; gap: 18px; }
            .cd-promo-current { font-size: 1.4rem; text-decoration: line-through; opacity: .6; }
            .cd-promo-offer { font-size: 3rem; font-weight: 800; color: #FBBF24; }
            .cd-promo-discount {
                background: #EF4444; color: #fff; font-weight: 800; font-size: 1.1rem;
                padding: 6px 16px; border-radius: 10px; align-self: center;
            }

            .cd-promo-empty {
                position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center;
                text-align: center; padding: 40px;
                background: linear-gradient(160deg, var(--pos-brand-dark) 0%, var(--pos-brand) 55%, #0D4E30 100%);
            }
            .cd-promo-empty .mark-lg { font-size: 6rem; margin-bottom: 20px; }
            .cd-promo-empty h1 { font-size: 2.6rem; font-weight: 800; margin-bottom: 10px; }
            .cd-promo-empty p.tagline { font-size: 1.2rem; opacity: .85; margin-bottom: 40px; }
            .cd-promo-empty .cd-announce-row { display: flex; gap: 24px; flex-wrap: wrap; justify-content: center; }
            .cd-promo-empty .cd-announce-card {
                background: rgba(255,255,255,.1); border-radius: 16px; padding: 20px 26px; min-width: 220px;
            }
            .cd-promo-empty .cd-announce-card .label { text-transform: uppercase; letter-spacing: .08em; font-size: .75rem; opacity: .75; margin-bottom: 6px; }
            .cd-promo-empty .cd-announce-card .value { font-size: 1.05rem; font-weight: 700; }
            .cd-promo-empty .cd-thankyou { margin-top: 36px; font-size: 1.1rem; opacity: .85; }
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
            <div class="cd-promo-slide" id="cd-promo-slide">
                <img id="cd-promo-image" alt="">
                <div class="cd-promo-scrim"></div>
                <div class="cd-promo-body">
                    <div class="cd-promo-pill"><i class="bi bi-lightning-charge-fill"></i> {{ __('Limited Offer · Today Only') }}</div>
                    <div class="cd-promo-title" id="cd-promo-title"></div>
                    <div class="cd-promo-desc d-none-cd" id="cd-promo-desc"></div>
                    <div class="cd-promo-prices">
                        <span class="cd-promo-current" id="cd-promo-current"></span>
                        <span class="cd-promo-offer" id="cd-promo-offer"></span>
                        <span class="cd-promo-discount" id="cd-promo-discount"></span>
                    </div>
                </div>
            </div>

            <div class="cd-promo-empty" id="cd-promo-empty">
                <div class="mark-lg">🛒</div>
                <h1>{{ __('Welcome to :name', ['name' => config('app.name')]) }}</h1>
                <p class="tagline">{{ __('Your neighbourhood supermarket in Batticaloa, Sri Lanka') }}</p>
                <div class="cd-announce-row">
                    <div class="cd-announce-card">
                        <div class="label">{{ __('New Arrivals') }}</div>
                        <div class="value">{{ __('Fresh produce & bakery, every morning') }}</div>
                    </div>
                    <div class="cd-announce-card">
                        <div class="label">{{ __('Store Hours') }}</div>
                        <div class="value">{{ __('Open Daily · 7:00 AM – 10:00 PM') }}</div>
                    </div>
                    <div class="cd-announce-card">
                        <div class="label">{{ __('Star Points') }}</div>
                        <div class="value">{{ __('Earn 1 point for every Rs. 100 spent') }}</div>
                    </div>
                </div>
                <div class="cd-thankyou">{{ __('Thank you for shopping with us!') }}</div>
            </div>
        </div>

        <script>
            const dataUrl = '{{ route('cashier.display.data') }}';
            const promotionsUrl = '{{ route('cashier.display.promotions') }}';
            const viewedUrlBase = '{{ url('/cashier/display/promotions') }}';
            const csrfToken = '{{ csrf_token() }}';

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

            /* ---------- Promotion rotation (right panel) ---------- */
            const promoSlide = document.getElementById('cd-promo-slide');
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

            function showPromo(promo) {
                promoEmpty.classList.add('d-none-cd');
                promoSlide.classList.remove('show');

                setTimeout(() => {
                    document.getElementById('cd-promo-image').src = promo.poster_url || '';
                    document.getElementById('cd-promo-title').textContent = promo.title;

                    const descEl = document.getElementById('cd-promo-desc');
                    descEl.textContent = promo.description || '';
                    descEl.classList.toggle('d-none-cd', !promo.description);

                    document.getElementById('cd-promo-current').textContent = 'Rs ' + Number(promo.current_price).toFixed(2);
                    document.getElementById('cd-promo-offer').textContent = 'Rs ' + Number(promo.offer_price).toFixed(2);
                    document.getElementById('cd-promo-discount').textContent = '-' + Number(promo.discount_percentage).toFixed(0) + '%';

                    promoSlide.classList.add('show');
                    markViewed(promo);
                }, 400);
            }

            function showEmptyPromoState() {
                promoSlide.classList.remove('show');
                setTimeout(() => promoEmpty.classList.remove('d-none-cd'), 400);
            }

            function scheduleNext() {
                clearTimeout(rotateTimer);

                if (playlist.length === 0) {
                    showEmptyPromoState();
                    return;
                }

                const promo = playlist[playIndex % playlist.length];
                showPromo(promo);
                playIndex++;
                rotateTimer = setTimeout(scheduleNext, Math.max(5, promo.display_duration || 10) * 1000);
            }

            function refreshPromotions() {
                fetch(promotionsUrl, { headers: { 'Accept': 'application/json' } })
                    .then(r => r.json())
                    .then(data => {
                        const wasEmpty = playlist.length === 0;
                        playlist = buildPlaylist(data.promotions || []);

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
