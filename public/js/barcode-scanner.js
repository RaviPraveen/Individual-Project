/**
 * Shared camera barcode scanner, additive to (never a replacement for) the
 * existing USB/Bluetooth/keyboard-wedge scanner support already wired
 * directly into each page's own product-search input. This module only
 * ever hands the caller a decoded string via the onDetected callback —
 * it never touches product search, cart, or form logic itself.
 *
 * Usage: window.openBarcodeScanner(function (decodedText) { ... });
 */
(function () {
    'use strict';

    let html5QrCode = null;
    let handled = false;
    let currentCameraId = null;
    let cameras = [];
    let cameraIndex = 0;
    let torchOn = false;

    function els() {
        return {
            modalEl: document.getElementById('barcode-scan-modal'),
            status: document.getElementById('barcode-scan-status'),
            deniedBox: document.getElementById('barcode-scan-denied'),
            retryBtn: document.getElementById('barcode-scan-retry-btn'),
            torchBtn: document.getElementById('barcode-scan-torch-btn'),
            switchBtn: document.getElementById('barcode-scan-switch-btn'),
            videoRegion: document.getElementById('barcode-scan-video-region'),
        };
    }

    function setStatus(text) {
        const { status } = els();
        if (status) status.textContent = text;
    }

    async function stopScanning() {
        if (html5QrCode) {
            try {
                if (html5QrCode.isScanning) {
                    await html5QrCode.stop();
                }
                html5QrCode.clear();
            } catch (e) {
                // camera may already be stopped — safe to ignore
            }
        }
        html5QrCode = null;
        torchOn = false;
    }

    async function applyTorch(on) {
        if (!html5QrCode) return;
        try {
            await html5QrCode.applyVideoConstraints({ advanced: [{ torch: on }] });
            torchOn = on;
        } catch (e) {
            // torch not supported on this device/browser — ignore
        }
    }

    function detectTorchSupport() {
        const { torchBtn } = els();
        if (!torchBtn || !html5QrCode) return;
        try {
            const capabilities = html5QrCode.getRunningTrackCapabilities();
            if (capabilities && capabilities.torch) {
                torchBtn.style.display = '';
            } else {
                torchBtn.style.display = 'none';
            }
        } catch (e) {
            torchBtn.style.display = 'none';
        }
    }

    function startCamera(cameraId, onDetected) {
        const { deniedBox, switchBtn } = els();
        if (deniedBox) deniedBox.classList.add('d-none');
        setStatus('Point the camera at a barcode...');

        html5QrCode = new Html5Qrcode('barcode-scan-video-region', {
            formatsToSupport: [
                Html5QrcodeSupportedFormats.EAN_13,
                Html5QrcodeSupportedFormats.EAN_8,
                Html5QrcodeSupportedFormats.UPC_A,
                Html5QrcodeSupportedFormats.UPC_E,
                Html5QrcodeSupportedFormats.CODE_128,
                Html5QrcodeSupportedFormats.CODE_39,
                Html5QrcodeSupportedFormats.ITF,
                Html5QrcodeSupportedFormats.QR_CODE,
            ],
            verbose: false,
        });

        html5QrCode
            .start(
                cameraId,
                { fps: 10, qrbox: { width: 260, height: 160 } },
                (decodedText) => {
                    if (handled) return;
                    handled = true;
                    setStatus('Barcode Detected');
                    stopScanning().then(() => {
                        closeModal();
                        onDetected(decodedText);
                    });
                },
                () => {
                    // per-frame decode failure — expected constantly while
                    // searching for a barcode, not an error worth surfacing
                }
            )
            .then(() => {
                setStatus('Scanning...');
                detectTorchSupport();
                if (switchBtn) {
                    switchBtn.style.display = cameras.length > 1 ? '' : 'none';
                }
            })
            .catch(() => {
                showPermissionDenied(onDetected);
            });
    }

    function showPermissionDenied(onDetected) {
        const { deniedBox, retryBtn } = els();
        setStatus('');
        if (deniedBox) deniedBox.classList.remove('d-none');
        if (retryBtn) {
            retryBtn.onclick = () => {
                handled = false;
                startCamera(currentCameraId, onDetected);
            };
        }
    }

    function closeModal() {
        const { modalEl } = els();
        if (modalEl && window.bootstrap) {
            const instance = window.bootstrap.Modal.getInstance(modalEl);
            if (instance) instance.hide();
        }
    }

    window.openBarcodeScanner = function (onDetected) {
        handled = false;
        const { modalEl, torchBtn, switchBtn } = els();
        if (!modalEl) {
            console.error('Barcode scan modal not found on this page.');
            return;
        }

        const modal = window.bootstrap.Modal.getOrCreateInstance(modalEl);

        const onShown = () => {
            Html5Qrcode.getCameras()
                .then((devices) => {
                    cameras = devices || [];
                    cameraIndex = 0;
                    currentCameraId = cameras.length
                        ? (cameras.find((d) => /back|rear/i.test(d.label)) || cameras[0]).id
                        : { facingMode: 'environment' };
                    startCamera(currentCameraId, onDetected);
                })
                .catch(() => {
                    currentCameraId = { facingMode: 'environment' };
                    startCamera(currentCameraId, onDetected);
                });
        };

        modalEl.addEventListener('shown.bs.modal', onShown, { once: true });

        const onHidden = () => {
            stopScanning();
        };
        modalEl.addEventListener('hidden.bs.modal', onHidden, { once: true });

        if (torchBtn) {
            torchBtn.onclick = () => applyTorch(!torchOn);
        }
        if (switchBtn) {
            switchBtn.onclick = () => {
                if (cameras.length < 2) return;
                cameraIndex = (cameraIndex + 1) % cameras.length;
                currentCameraId = cameras[cameraIndex].id;
                handled = false;
                stopScanning().then(() => startCamera(currentCameraId, onDetected));
            };
        }

        modal.show();
    };
})();
