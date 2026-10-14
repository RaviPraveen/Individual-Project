// Camera scanner. Pass the decoded barcode to the page's onDetected callback.
(function () {
    'use strict';

    let html5QrCode = null;
    let handled = false;
    let currentCameraId = null;
    let cameras = [];
    let cameraIndex = 0;
    let torchOn = false;

    function scannerElements() {
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
        const { status } = scannerElements();
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
                // The camera may already be stopped.
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
            // Some cameras do not support the torch setting.
        }
    }

    function detectTorchSupport() {
        const { torchBtn } = scannerElements();
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
        const { deniedBox, switchBtn } = scannerElements();
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
                    // Ignore frames where no barcode is detected.
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
        const { deniedBox, retryBtn } = scannerElements();
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
        const { modalEl } = scannerElements();
        if (modalEl && window.bootstrap) {
            const instance = window.bootstrap.Modal.getInstance(modalEl);
            if (instance) instance.hide();
        }
    }

    window.openBarcodeScanner = function (onDetected) {
        handled = false;
        const { modalEl, torchBtn, switchBtn } = scannerElements();
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
