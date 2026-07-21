@once
<div class="modal fade" id="barcode-scan-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold text-dark"><i class="bi bi-camera-fill text-primary me-2"></i>{{ __('Scan Barcode with Camera') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <div class="position-relative mx-auto rounded-3 overflow-hidden bg-dark" style="max-width: 360px;">
                    <div id="barcode-scan-video-region"></div>
                </div>

                <div class="mt-3 fw-semibold text-dark" id="barcode-scan-status">{{ __('Point the camera at a barcode...') }}</div>

                <div class="alert alert-warning mt-3 d-none text-start" id="barcode-scan-denied">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    {{ __('Camera access was denied. Please allow camera permission in your browser and try again.') }}
                    <div class="mt-2">
                        <button type="button" class="btn btn-sm btn-warning fw-bold" id="barcode-scan-retry-btn">
                            <i class="bi bi-arrow-clockwise me-1"></i>{{ __('Retry') }}
                        </button>
                    </div>
                </div>

                <div class="d-flex justify-content-center gap-2 mt-3">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="barcode-scan-torch-btn" style="display:none;" title="{{ __('Toggle flashlight') }}">
                        <i class="bi bi-flashlight"></i> {{ __('Flashlight') }}
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="barcode-scan-switch-btn" style="display:none;" title="{{ __('Switch camera') }}">
                        <i class="bi bi-arrow-repeat"></i> {{ __('Switch Camera') }}
                    </button>
                </div>
            </div>
            <div class="modal-footer border-top bg-light">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Close') }}</button>
            </div>
        </div>
    </div>
</div>
@endonce
