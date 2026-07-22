<x-admin-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2 class="h4 mb-0"><i class="bi bi-megaphone text-primary me-2"></i>{{ __('Edit Promotion') }}</h2>
                <div class="text-muted small">{{ $promotion->title }}</div>
            </div>
            <a href="{{ route('admin.promotions.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill"><i class="bi bi-arrow-left"></i> {{ __('Back to List') }}</a>
        </div>
    </x-slot>

    <div class="card border-0 shadow-sm mb-4" id="poster-generator" style="border: 1px solid #DBEAFE !important;">
        <div class="card-header bg-white fw-bold text-dark border-bottom py-3 d-flex align-items-center justify-content-between">
            <span><i class="bi bi-stars text-warning me-1.5"></i> {{ __('AI Promotion Poster Generator') }}</span>
            @if ($promotion->poster_path)
                <span class="badge {{ $promotion->poster_source === 'ai' ? 'bg-primary-subtle text-primary' : 'bg-secondary-subtle text-secondary' }}">
                    {{ $promotion->poster_source === 'ai' ? __('Live — AI Generated') : __('Live — Custom Upload') }}
                </span>
            @endif
        </div>
        <div class="card-body">
            <div class="row g-4 align-items-start">
                <div class="col-md-5">
                    <div class="text-muted small mb-2 fw-semibold text-uppercase" style="letter-spacing:0.05em;font-size:0.7rem;">{{ __('Live Poster') }}</div>
                    <div id="live-poster-wrap" class="rounded-3 border bg-light d-flex align-items-center justify-content-center overflow-hidden" style="aspect-ratio:4/3;">
                        @if ($promotion->poster_path)
                            <img id="live-poster-img" src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($promotion->poster_path) }}" class="w-100 h-100" style="object-fit:cover;" alt="{{ __('Live poster') }}">
                        @else
                            <div class="text-muted text-center p-4" id="live-poster-empty"><i class="bi bi-image fs-1 d-block mb-2"></i>{{ __('No poster yet') }}</div>
                        @endif
                    </div>
                    <div class="d-flex gap-2 mt-2">
                        <a href="{{ $promotion->poster_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($promotion->poster_path) : '#' }}" download id="btn-download-live" class="btn btn-outline-secondary btn-sm {{ $promotion->poster_path ? '' : 'd-none' }}">
                            <i class="bi bi-download me-1"></i> {{ __('Download') }}
                        </a>
                        <button type="button" id="btn-delete-live" class="btn btn-outline-danger btn-sm {{ $promotion->poster_path ? '' : 'd-none' }}" data-confirm="{{ __('Delete the live poster? The Customer Display will show no image for this promotion until a new one is generated or uploaded.') }}" data-confirm-icon="warning">
                            <i class="bi bi-trash3 me-1"></i> {{ __('Delete') }}
                        </button>
                    </div>
                </div>
                <div class="col-md-7">
                    <div class="text-muted small mb-2 fw-semibold text-uppercase" style="letter-spacing:0.05em;font-size:0.7rem;">{{ __('Preview — Not Live Until Approved') }}</div>
                    <div id="poster-preview-wrap" class="rounded-3 border border-2 border-primary-subtle bg-light d-flex align-items-center justify-content-center overflow-hidden position-relative" style="aspect-ratio:4/3;">
                        <div id="poster-skeleton" class="w-100 h-100 d-none" style="background: linear-gradient(90deg, #E2E8F0 25%, #F1F5F9 50%, #E2E8F0 75%); background-size: 200% 100%; animation: pos-skeleton 1.2s ease-in-out infinite;"></div>
                        @if ($promotion->pending_poster_path)
                            <img id="poster-preview-img" src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($promotion->pending_poster_path) }}" class="w-100 h-100" style="object-fit:cover;" alt="{{ __('Generated poster preview') }}">
                        @else
                            <div class="text-muted text-center p-4" id="poster-preview-empty"><i class="bi bi-magic fs-1 d-block mb-2"></i>{{ __('Generate a poster to preview it here') }}</div>
                        @endif
                    </div>
                    <div id="poster-preview-note" class="small text-muted mt-2 {{ ($promotion->pending_poster_path && ! $promotion->pending_poster_used_ai) ? '' : 'd-none' }}">
                        {{ __('AI image service was unavailable for the last attempt — this is a placeholder background. Try Generate Again, or Approve it anyway.') }}
                    </div>

                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <button type="button" id="btn-generate" class="btn btn-primary fw-bold px-3">
                            <i class="bi bi-magic me-1"></i> <span id="btn-generate-label">{{ $promotion->pending_poster_path ? __('Generate Again') : __('Generate Poster') }}</span>
                        </button>
                        <button type="button" id="btn-approve" class="btn btn-success fw-bold px-3 {{ $promotion->pending_poster_path ? '' : 'd-none' }}">
                            <i class="bi bi-check-circle me-1"></i> {{ __('Approve') }}
                        </button>
                        <button type="button" id="btn-discard" class="btn btn-outline-danger px-3 {{ $promotion->pending_poster_path ? '' : 'd-none' }}">
                            <i class="bi bi-x-circle me-1"></i> {{ __('Discard') }}
                        </button>
                        <a href="{{ $promotion->pending_poster_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($promotion->pending_poster_path) : '#' }}" download id="btn-download-preview" class="btn btn-outline-secondary px-3 {{ $promotion->pending_poster_path ? '' : 'd-none' }}">
                            <i class="bi bi-download me-1"></i> {{ __('Download') }}
                        </a>
                        <a href="#poster_image" onclick="document.getElementById('poster_image').focus();" class="btn btn-outline-secondary px-3">
                            <i class="bi bi-upload me-1"></i> {{ __('Upload Custom Image Instead') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.promotions.update', $promotion) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        @include('admin.promotions._form')

        <div class="mt-3">
            <button type="submit" class="btn btn-primary px-4"><i class="bi bi-check2 me-1"></i>{{ __('Save Changes') }}</button>
            <a href="{{ route('admin.promotions.index') }}" class="btn btn-outline-secondary px-4">{{ __('Cancel') }}</a>
        </div>
    </form>

    <style>
        @keyframes pos-skeleton {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
    </style>

    <script>
        (function () {
            const generateUrl = '{{ route('admin.promotions.poster.generate', $promotion) }}';
            const approveUrl = '{{ route('admin.promotions.poster.approve', $promotion) }}';
            const discardUrl = '{{ route('admin.promotions.poster.discard', $promotion) }}';
            const destroyLiveUrl = '{{ route('admin.promotions.poster.destroy', $promotion) }}';
            const csrf = document.querySelector('meta[name="csrf-token"]').content;

            const btnGenerate = document.getElementById('btn-generate');
            const btnGenerateLabel = document.getElementById('btn-generate-label');
            const btnApprove = document.getElementById('btn-approve');
            const btnDiscard = document.getElementById('btn-discard');
            const btnDownloadPreview = document.getElementById('btn-download-preview');
            const btnDownloadLive = document.getElementById('btn-download-live');
            const btnDeleteLive = document.getElementById('btn-delete-live');
            const skeleton = document.getElementById('poster-skeleton');
            const previewWrap = document.getElementById('poster-preview-wrap');
            const previewNote = document.getElementById('poster-preview-note');

            function setPreviewImage(url) {
                document.getElementById('poster-preview-empty')?.remove();
                let img = document.getElementById('poster-preview-img');
                if (!img) {
                    img = document.createElement('img');
                    img.id = 'poster-preview-img';
                    img.className = 'w-100 h-100';
                    img.style.objectFit = 'cover';
                    img.alt = '{{ __('Generated poster preview') }}';
                    previewWrap.appendChild(img);
                }
                img.src = url + '?t=' + Date.now();
                btnDownloadPreview.href = url;
                btnDownloadPreview.classList.remove('d-none');
            }

            btnGenerate.addEventListener('click', function () {
                btnGenerate.disabled = true;
                skeleton.classList.remove('d-none');
                document.getElementById('poster-preview-img')?.classList.add('d-none');

                fetch(generateUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                }).then(r => r.json()).then(data => {
                    skeleton.classList.add('d-none');
                    document.getElementById('poster-preview-img')?.classList.remove('d-none');
                    setPreviewImage(data.poster_url);
                    btnGenerateLabel.textContent = '{{ __('Generate Again') }}';
                    btnApprove.classList.remove('d-none');
                    btnDiscard.classList.remove('d-none');
                    previewNote.classList.toggle('d-none', data.used_ai);
                    btnGenerate.disabled = false;
                    window.posToast ? window.posToast(data.message, data.used_ai ? 'success' : 'warning') : null;
                }).catch(() => {
                    skeleton.classList.add('d-none');
                    btnGenerate.disabled = false;
                    window.posToast ? window.posToast('{{ __('Something went wrong generating the poster.') }}', 'danger') : null;
                });
            });

            // These update the DOM directly rather than reloading the page —
            // a reload's fetch() would silently follow the controller's
            // back() redirect and consume the one-shot flash message before
            // the reload's own request ever saw it, swallowing the toast.
            btnApprove.addEventListener('click', function () {
                fetch(approveUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    redirect: 'manual',
                }).then(() => {
                    const pendingSrc = document.getElementById('poster-preview-img')?.src;
                    if (pendingSrc) {
                        let liveImg = document.getElementById('live-poster-img');
                        document.getElementById('live-poster-empty')?.remove();
                        if (!liveImg) {
                            liveImg = document.createElement('img');
                            liveImg.id = 'live-poster-img';
                            liveImg.className = 'w-100 h-100';
                            liveImg.style.objectFit = 'cover';
                            document.getElementById('live-poster-wrap').appendChild(liveImg);
                        }
                        liveImg.src = pendingSrc;
                        btnDownloadLive.href = pendingSrc.split('?')[0];
                        btnDownloadLive.classList.remove('d-none');
                        btnDeleteLive.classList.remove('d-none');
                    }
                    btnApprove.classList.add('d-none');
                    btnDiscard.classList.add('d-none');
                    btnDownloadPreview.classList.add('d-none');
                    window.posToast ? window.posToast('{{ __('Poster approved and is now live.') }}', 'success') : null;
                });
            });

            btnDiscard.addEventListener('click', function () {
                fetch(discardUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    redirect: 'manual',
                }).then(() => {
                    document.getElementById('poster-preview-img')?.remove();
                    previewWrap.insertAdjacentHTML('beforeend', '<div class="text-muted text-center p-4" id="poster-preview-empty"><i class="bi bi-magic fs-1 d-block mb-2"></i>{{ __('Generate a poster to preview it here') }}</div>');
                    btnApprove.classList.add('d-none');
                    btnDiscard.classList.add('d-none');
                    btnDownloadPreview.classList.add('d-none');
                    btnGenerateLabel.textContent = '{{ __('Generate Poster') }}';
                    window.posToast ? window.posToast('{{ __('Generated poster discarded.') }}', 'success') : null;
                });
            });

            // btn-delete-live isn't wrapped in its own <form> (it sits above
            // the promotion edit form, not inside it), so pos.js's global
            // data-confirm handler falls through to its "plain button" path
            // and dispatches this custom event instead of submitting a form.
            // That handler also sets el.dataset.confirmed = '1' permanently
            // after the first confirm and never clears it — invisible for
            // form-based buttons (the page always reloads after submit,
            // resetting the flag for free) but it would silently no-op a
            // second click here since this button never reloads the page.
            // Clear it back once this handler has run so Generate ->
            // Approve -> Delete -> Generate -> Approve -> Delete keeps
            // working across the same page load.
            btnDeleteLive.addEventListener('pos-confirmed', function () {
                fetch(destroyLiveUrl, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    redirect: 'manual',
                }).then(() => {
                    document.getElementById('live-poster-img')?.remove();
                    document.getElementById('live-poster-wrap').insertAdjacentHTML('beforeend', '<div class="text-muted text-center p-4" id="live-poster-empty"><i class="bi bi-image fs-1 d-block mb-2"></i>{{ __('No poster yet') }}</div>');
                    btnDownloadLive.classList.add('d-none');
                    btnDeleteLive.classList.add('d-none');
                    btnDeleteLive.dataset.confirmed = '';
                    window.posToast ? window.posToast('{{ __('Live poster deleted.') }}', 'success') : null;
                });
            });
        })();
    </script>
</x-admin-layout>
