/* Customer/check-in identity rows, camera capture, crop and compression. */
(function () {
    'use strict';

    var MAX_ROWS = 20;
    var MAX_RAW_IMAGE_BYTES = 20 * 1024 * 1024;
    var MAX_UPLOAD_BYTES = 4 * 1024 * 1024;
    var MAX_OUTPUT_DIMENSION = 1600;
    var MAX_SOURCE_PIXELS = 40000000;
    var JPEG_QUALITY = 0.82;

    var container = document.getElementById('identityRows');
    var tpl = document.getElementById('identityRowTpl');
    var addBtn = document.getElementById('idnAddMore');
    var secondImageMedia = window.matchMedia('(max-width: 520px)');
    if (!container || !tpl) { return; }

    function extension(file) {
        var parts = String(file && file.name || '').toLowerCase().split('.');
        return parts.length > 1 ? parts.pop() : '';
    }

    function isPdf(file) {
        return extension(file) === 'pdf' || file.type === 'application/pdf';
    }

    function isImage(file) {
        return ['jpg', 'jpeg', 'png'].indexOf(extension(file)) !== -1
            && (!file.type || file.type === 'image/jpeg' || file.type === 'image/png');
    }

    function friendlyBytes(bytes) {
        if (bytes < 1024 * 1024) { return Math.max(1, Math.round(bytes / 1024)) + ' KB'; }
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }

    function safeJpegName(name) {
        var base = String(name || 'document').replace(/\.[^.]+$/, '')
            .replace(/[^a-z0-9_-]+/gi, '-')
            .replace(/^-+|-+$/g, '')
            .slice(0, 60);
        return (base || 'document') + '.jpg';
    }

    function setMessage(widget, text, isError) {
        var node = widget.querySelector('[data-idn-message]');
        if (!node) { return; }
        node.textContent = text || '';
        node.hidden = !text;
        node.classList.toggle('is-error', Boolean(isError));
        node.classList.toggle('is-success', Boolean(text) && !isError);
    }

    function loadImage(file) {
        return new Promise(function (resolve, reject) {
            var url = URL.createObjectURL(file);
            var img = new Image();
            img.onload = function () {
                URL.revokeObjectURL(url);
                resolve(img);
            };
            img.onerror = function () {
                URL.revokeObjectURL(url);
                reject(new Error('This image could not be read. Please take the photo again.'));
            };
            img.src = url;
        });
    }

    function canvasBlob(canvas, quality) {
        return new Promise(function (resolve, reject) {
            canvas.toBlob(function (blob) {
                if (blob) { resolve(blob); }
                else { reject(new Error('The image could not be compressed on this device.')); }
            }, 'image/jpeg', quality);
        });
    }

    function imageFileFromCanvas(canvas, originalName) {
        return canvasBlob(canvas, JPEG_QUALITY).then(function (blob) {
            if (blob.size > MAX_UPLOAD_BYTES) {
                return canvasBlob(canvas, 0.65);
            }
            return blob;
        }).then(function (blob) {
            if (blob.size > MAX_UPLOAD_BYTES) {
                throw new Error('The compressed image is still over 4 MB. Crop it more tightly.');
            }
            return new File([blob], safeJpegName(originalName), {
                type: 'image/jpeg',
                lastModified: Date.now()
            });
        });
    }

    function prepareImage(file) {
        if (file.size <= 0 || file.size > MAX_RAW_IMAGE_BYTES) {
            return Promise.reject(new Error('Choose an image between 1 byte and 20 MB.'));
        }

        return loadImage(file).then(function (img) {
            if (!img.naturalWidth || !img.naturalHeight
                || img.naturalWidth * img.naturalHeight > MAX_SOURCE_PIXELS) {
                throw new Error('This image is too large to process safely. Use the crop/resize option in your camera first.');
            }

            var scale = Math.min(1, MAX_OUTPUT_DIMENSION / Math.max(img.naturalWidth, img.naturalHeight));
            var width = Math.max(1, Math.round(img.naturalWidth * scale));
            var height = Math.max(1, Math.round(img.naturalHeight * scale));
            var canvas = document.createElement('canvas');
            canvas.width = width;
            canvas.height = height;
            var context = canvas.getContext('2d', { alpha: false });
            context.fillStyle = '#fff';
            context.fillRect(0, 0, width, height);
            context.drawImage(img, 0, 0, width, height);
            return imageFileFromCanvas(canvas, file.name);
        });
    }

    function prepareFile(file, slotNumber) {
        if (!file || !file.name) {
            return Promise.reject(new Error('No file was selected.'));
        }
        if (isPdf(file)) {
            if (slotNumber !== 1) {
                return Promise.reject(new Error('A PDF can only be used as Image 1. Image 2 must be JPG or PNG.'));
            }
            if (file.size <= 0 || file.size > MAX_UPLOAD_BYTES) {
                return Promise.reject(new Error('PDF documents must be 4 MB or smaller.'));
            }
            return Promise.resolve(file);
        }
        if (!isImage(file)) {
            return Promise.reject(new Error('Only JPG, PNG or PDF files are allowed.'));
        }
        return prepareImage(file);
    }

    function slotState(widget, number) {
        return widget._idnSlots[number - 1];
    }

    function hasSavedFile(state) {
        return state.hasExisting && !state.removeExisting && !state.suppressedByPdf;
    }

    function frontUsesPdf(widget) {
        var front = slotState(widget, 1);
        return Boolean(front && (
            (front.file && isPdf(front.file))
            || (!front.file && hasSavedFile(front) && front.existingKind === 'pdf')
        ));
    }

    function syncSecondImageControl(widget) {
        var field = widget.closest('.idn-document-field');
        var button = field && field.querySelector('[data-idn-add-second]');
        var second = slotState(widget, 2);
        if (!field || !button || !second) { return; }

        var hasSecond = Boolean(second.file || hasSavedFile(second));
        var unavailable = frontUsesPdf(widget);
        if (hasSecond) { widget._idnSecondRevealed = true; }
        if (unavailable && !hasSecond) { widget._idnSecondRevealed = false; }
        var collapsed = secondImageMedia.matches
            && !hasSecond
            && !widget._idnSecondRevealed;

        field.classList.toggle('is-second-collapsed', collapsed);
        button.hidden = !secondImageMedia.matches || !collapsed || unavailable;
        button.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
    }

    function revealSecondImage(widget) {
        widget._idnSecondRevealed = true;
        syncSecondImageControl(widget);
        var second = slotState(widget, 2);
        var choose = second && second.element.querySelector('[data-idn-action="choose-slot"]');
        if (choose) { choose.focus(); }
    }

    function setInputFile(input, file) {
        if (typeof DataTransfer !== 'function') {
            throw new Error('This browser cannot prepare camera files for upload. Please update the browser.');
        }
        var transfer = new DataTransfer();
        transfer.items.add(file);
        input.files = transfer.files;
    }

    function renderSlot(state) {
        var hasNew = Boolean(state.file);
        var effectiveExisting = hasSavedFile(state);
        var hasSavedImage = effectiveExisting && state.existingKind === 'image' && state.existingUrl;
        var isNewPdf = hasNew && isPdf(state.file);
        var preview = state.element.querySelector('[data-idn-preview]');
        var placeholder = state.element.querySelector('[data-idn-placeholder]');
        var filename = state.element.querySelector('[data-idn-filename]');
        var chooseLabel = state.element.querySelector('[data-idn-choose-label]');
        var crop = state.element.querySelector('[data-idn-action="crop"]');
        var clear = state.element.querySelector('[data-idn-action="clear"]');

        if (state.previewUrl) {
            URL.revokeObjectURL(state.previewUrl);
            state.previewUrl = null;
        }
        if (hasNew && !isNewPdf) {
            state.previewUrl = URL.createObjectURL(state.file);
            preview.src = state.previewUrl;
            preview.hidden = false;
            placeholder.hidden = true;
        } else if (hasSavedImage) {
            preview.src = state.existingUrl;
            preview.hidden = false;
            placeholder.hidden = true;
        } else {
            preview.removeAttribute('src');
            preview.hidden = true;
            placeholder.hidden = false;
            var icon = placeholder.querySelector('i');
            if (icon) {
                icon.className = 'bi ' + ((isNewPdf || (!hasNew && effectiveExisting && state.existingKind === 'pdf'))
                    ? 'bi-file-earmark-pdf' : 'bi-card-image');
            }
        }

        if (hasNew) {
            filename.textContent = state.file.name + ' (' + friendlyBytes(state.file.size) + ')';
        } else {
            filename.textContent = state.suppressedByPdf
                ? 'Removed when PDF is saved'
                : (effectiveExisting ? 'Saved file' : 'No image');
        }
        if (chooseLabel) {
            chooseLabel.textContent = (hasNew || effectiveExisting) ? 'Replace' : 'Choose';
        }
        crop.disabled = !((hasNew && !isNewPdf) || hasSavedImage);
        clear.hidden = !hasNew && !effectiveExisting;
        state.element.classList.toggle('has-new-file', hasNew);
        state.element.classList.toggle('is-pending-removal', state.removeExisting && !hasNew);
    }

    function putPreparedFile(widget, slotNumber, file) {
        var state = slotState(widget, slotNumber);
        state.removeExisting = false;
        state.removeInput.value = '0';
        if (slotNumber === 1) {
            var back = slotState(widget, 2);
            back.suppressedByPdf = isPdf(file);
            if (isPdf(file) && back.file) {
                back.input.value = '';
                back.file = null;
            }
            renderSlot(back);
            if (isPdf(file) && back.hasExisting) {
                setMessage(widget, 'The PDF will replace both saved images.', false);
            }
        }
        if (slotNumber === 2) {
            var front = slotState(widget, 1);
            if ((front.file && isPdf(front.file))
                || (!front.file && hasSavedFile(front) && front.existingKind === 'pdf')) {
                throw new Error('Image 2 cannot be added while Image 1 is a PDF. Replace the PDF with an image first.');
            }
        }
        setInputFile(state.input, file);
        state.file = file;
        renderSlot(state);
        if (slotNumber === 2) { widget._idnSecondRevealed = true; }
        syncSecondImageControl(widget);
    }

    function assignFile(widget, slotNumber, file) {
        widget._idnBusy += 1;
        widget.classList.add('is-processing');
        setMessage(widget, 'Preparing image...', false);
        return prepareFile(file, slotNumber).then(function (prepared) {
            putPreparedFile(widget, slotNumber, prepared);
            var changed = prepared.size < file.size && !isPdf(file);
            setMessage(widget, changed
                ? 'Image compressed from ' + friendlyBytes(file.size) + ' to ' + friendlyBytes(prepared.size) + '.'
                : 'File ready to upload.', false);
        }).catch(function (error) {
            setMessage(widget, error.message || 'The file could not be prepared.', true);
            throw error;
        }).then(function () {
            widget._idnBusy -= 1;
            widget.classList.toggle('is-processing', widget._idnBusy > 0);
        }, function (error) {
            widget._idnBusy -= 1;
            widget.classList.toggle('is-processing', widget._idnBusy > 0);
            return Promise.reject(error);
        });
    }

    function clearSlot(widget, slotNumber) {
        var state = slotState(widget, slotNumber);
        var removedNewSelection = Boolean(state.file);
        if (removedNewSelection) {
            state.input.value = '';
            state.file = null;
        } else if (hasSavedFile(state)) {
            state.removeExisting = true;
            state.removeInput.value = '1';
        }
        renderSlot(state);
        if (slotNumber === 1) {
            var back = slotState(widget, 2);
            back.suppressedByPdf = false;
            renderSlot(back);
        }
        if (slotNumber === 2 && !state.file && !hasSavedFile(state)) {
            widget._idnSecondRevealed = false;
        }
        syncSecondImageControl(widget);
        setMessage(widget, removedNewSelection
            ? (hasSavedFile(state) ? 'New selection removed. Saved image restored.' : 'Selection removed.')
            : 'Image will be removed when you save.', false);
    }

    function nextCameraSlot(widget) {
        var front = slotState(widget, 1);
        var back = slotState(widget, 2);
        if (!front.file && !hasSavedFile(front)) { return 1; }
        if (!back.file && !hasSavedFile(back)) { return 2; }
        setMessage(widget, 'Both images are filled. The next photo will replace Image 1.', false);
        return 1;
    }

    function initWidget(widget) {
        if (widget._idnReady) { return; }
        widget._idnReady = true;
        widget._idnBusy = 0;
        widget._idnCameraTarget = 1;
        widget._idnSlots = [];
        widget._idnSecondRevealed = false;

        var slotNodes = widget.querySelectorAll('[data-idn-slot]');
        Array.prototype.forEach.call(slotNodes, function (element) {
            var number = parseInt(element.getAttribute('data-idn-slot'), 10);
            var state = {
                number: number,
                element: element,
                input: element.querySelector('[data-idn-input]'),
                removeInput: element.querySelector('[data-idn-remove-input]'),
                file: null,
                previewUrl: null,
                hasExisting: element.getAttribute('data-existing') === '1',
                existingKind: element.getAttribute('data-existing-kind') || 'image',
                existingUrl: element.getAttribute('data-existing-url') || '',
                removeExisting: false,
                suppressedByPdf: false
            };
            widget._idnSlots[number - 1] = state;
            state.input.addEventListener('change', function () {
                var file = this.files && this.files[0];
                if (!file) { return; }
                assignFile(widget, number, file).catch(function () { state.input.value = ''; });
            });
            renderSlot(state);
        });

        widget._idnSecondRevealed = hasSavedFile(slotState(widget, 2));
        syncSecondImageControl(widget);

        var addSecond = widget.closest('.idn-document-field').querySelector('[data-idn-add-second]');
        addSecond.addEventListener('click', function () { revealSecondImage(widget); });
        var syncResponsiveSecond = function () { syncSecondImageControl(widget); };
        if (secondImageMedia.addEventListener) {
            secondImageMedia.addEventListener('change', syncResponsiveSecond);
        } else if (secondImageMedia.addListener) {
            secondImageMedia.addListener(syncResponsiveSecond);
        }

        var multi = widget.querySelector('[data-idn-source="multi"]');
        var camera = widget.querySelector('[data-idn-source="camera"]');
        multi.addEventListener('change', function () {
            var files = Array.prototype.slice.call(this.files || []);
            this.value = '';
            if (!files.length) { return; }
            if (files.length > 2) {
                setMessage(widget, 'Maximum 2 images are allowed for one identity document.', true);
                return;
            }
            if (files.some(isPdf) && files.length > 1) {
                setMessage(widget, 'Choose either one PDF or up to two images, not both.', true);
                return;
            }
            var chain = Promise.resolve();
            files.forEach(function (file, index) {
                chain = chain.then(function () { return assignFile(widget, index + 1, file); });
            });
            chain.catch(function () {});
        });
        camera.addEventListener('change', function () {
            var file = this.files && this.files[0];
            this.value = '';
            if (file) { assignFile(widget, widget._idnCameraTarget, file).catch(function () {}); }
        });

        widget.addEventListener('click', function (event) {
            var button = event.target.closest('[data-idn-action]');
            if (!button) { return; }
            var action = button.getAttribute('data-idn-action');
            var slot = button.closest('[data-idn-slot]');
            var number = slot ? parseInt(slot.getAttribute('data-idn-slot'), 10) : 0;
            if (action === 'choose-all') { multi.click(); }
            else if (action === 'camera-next') {
                widget._idnCameraTarget = nextCameraSlot(widget);
                if (widget._idnCameraTarget === 2) { revealSecondImage(widget); }
                openDeviceCamera(widget, widget._idnCameraTarget, camera);
            } else if (action === 'choose-slot') { slotState(widget, number).input.click(); }
            else if (action === 'camera-slot') {
                widget._idnCameraTarget = number;
                openDeviceCamera(widget, number, camera);
            } else if (action === 'clear') { clearSlot(widget, number); }
            else if (action === 'crop') { openSlotCrop(widget, number); }
        });
    }

    // Live camera preview. On browsers without getUserMedia (commonly an HTTP
    // LAN URL on a phone), the capture=file input remains the native fallback.
    var cameraDialog = null;
    var cameraSession = null;

    function supportsNativeCameraCapture() {
        if (navigator.userAgentData && navigator.userAgentData.mobile === true) {
            return true;
        }
        var userAgent = String(navigator.userAgent || '');
        return /Android|iPhone|iPod|Mobile/i.test(userAgent)
            || (/iPad|Macintosh/i.test(userAgent) && navigator.maxTouchPoints > 1);
    }

    function ensureCameraDialog() {
        if (cameraDialog) { return cameraDialog; }
        cameraDialog = document.createElement('div');
        cameraDialog.className = 'idn-camera-modal';
        cameraDialog.hidden = true;
        cameraDialog.innerHTML =
            '<div class="idn-camera-backdrop" data-camera-action="cancel"></div>' +
            '<section class="idn-camera-dialog" role="dialog" aria-modal="true" aria-labelledby="idnCameraTitle">' +
                '<div class="idn-camera-head"><div><h2 id="idnCameraTitle">Take document photo</h2>' +
                '<p>Allow camera access, then place the complete card inside the guide.</p></div>' +
                '<button type="button" class="idn-camera-close" data-camera-action="cancel" aria-label="Close camera">&times;</button></div>' +
                '<div class="idn-camera-stage">' +
                    '<video autoplay playsinline muted aria-label="Live camera preview"></video>' +
                    '<div class="idn-camera-guide" aria-hidden="true"><span>Keep all document edges visible</span></div>' +
                    '<div class="idn-camera-loading" data-camera-status>Starting camera...</div>' +
                '</div>' +
                '<div class="idn-camera-foot">' +
                    '<button type="button" class="erp-btn erp-btn-ghost" data-camera-action="native"><i class="bi bi-phone"></i> Device camera</button>' +
                    '<button type="button" class="erp-btn erp-btn-ghost" data-camera-action="switch"><i class="bi bi-arrow-repeat"></i> Switch</button>' +
                    '<button type="button" class="idn-camera-capture" data-camera-action="capture" aria-label="Capture photo"><span></span></button>' +
                '</div>' +
            '</section>';
        document.body.appendChild(cameraDialog);
        cameraDialog.querySelector('[data-camera-action="native"]').hidden = !supportsNativeCameraCapture();

        cameraDialog.addEventListener('click', function (event) {
            var actionNode = event.target.closest('[data-camera-action]');
            if (!actionNode) { return; }
            var action = actionNode.getAttribute('data-camera-action');
            if (action === 'cancel') { closeCamera(); }
            else if (action === 'switch') { switchCamera(); }
            else if (action === 'capture') { captureCameraPhoto(); }
            else if (action === 'native' && cameraSession && supportsNativeCameraCapture()) {
                var fallback = cameraSession.fallbackInput;
                closeCamera();
                fallback.click();
            }
        });
        return cameraDialog;
    }

    function cameraErrorMessage(error) {
        if (!error) { return 'Camera could not be started.'; }
        if (error.name === 'NotAllowedError' || error.name === 'SecurityError') {
            return supportsNativeCameraCapture()
                ? 'Camera permission was denied. Allow camera access in browser settings or use Device camera.'
                : 'Camera permission was denied. Allow camera access in browser settings and try again.';
        }
        if (error.name === 'NotFoundError' || error.name === 'DevicesNotFoundError') {
            return 'No camera was found on this device.';
        }
        if (error.name === 'NotReadableError' || error.name === 'TrackStartError') {
            return 'Camera is busy in another app. Close that app and try again.';
        }
        return supportsNativeCameraCapture()
            ? 'Camera could not be started. Use Device camera instead.'
            : 'Camera could not be started. Check its permission and connection, then try again.';
    }

    function setCameraStatus(text, isError) {
        if (!cameraDialog) { return; }
        var status = cameraDialog.querySelector('[data-camera-status]');
        status.textContent = text || '';
        status.classList.toggle('is-error', Boolean(isError));
        status.hidden = !text;
    }

    function stopCameraStream(session) {
        if (!session || !session.stream) { return; }
        session.stream.getTracks().forEach(function (track) { track.stop(); });
        session.stream = null;
    }

    function startCameraStream() {
        var session = cameraSession;
        if (!session) { return; }
        stopCameraStream(session);
        session.requestId = (session.requestId || 0) + 1;
        var requestId = session.requestId;
        setCameraStatus('Starting camera...', false);

        var constraints = {
            audio: false,
            video: {
                facingMode: { ideal: session.facing },
                width: { ideal: 1920 },
                height: { ideal: 1080 }
            }
        };
        navigator.mediaDevices.getUserMedia(constraints).then(function (stream) {
            if (cameraSession !== session || session.requestId !== requestId) {
                stream.getTracks().forEach(function (track) { track.stop(); });
                return;
            }
            session.stream = stream;
            var video = cameraDialog.querySelector('video');
            video.classList.toggle('is-user-camera', session.facing === 'user');
            video.srcObject = stream;
            video.onloadedmetadata = function () {
                if (cameraSession === session) {
                    setCameraStatus('', false);
                    var playResult = video.play();
                    if (playResult && playResult.catch) { playResult.catch(function () {}); }
                }
            };
        }).catch(function (error) {
            if (cameraSession !== session || session.requestId !== requestId) { return; }
            setCameraStatus(cameraErrorMessage(error), true);
            setMessage(session.widget, cameraErrorMessage(error), true);
        });
    }

    function openDeviceCamera(widget, slotNumber, fallbackInput) {
        if (
            !window.isSecureContext
            || !navigator.mediaDevices
            || typeof navigator.mediaDevices.getUserMedia !== 'function'
        ) {
            if (supportsNativeCameraCapture()) {
                setMessage(widget, 'Live preview needs HTTPS or localhost. Opening the device camera instead.', false);
                fallbackInput.click();
            } else {
                setMessage(widget, 'Live camera needs HTTPS or localhost. Open this page on a secure URL to use the laptop camera.', true);
            }
            return;
        }

        var modal = ensureCameraDialog();
        closeCamera();
        modal.querySelector('[data-camera-action="native"]').hidden = !supportsNativeCameraCapture();
        cameraSession = {
            widget: widget,
            slotNumber: slotNumber,
            fallbackInput: fallbackInput,
            facing: 'environment',
            stream: null,
            requestId: 0,
            capturing: false
        };
        modal.hidden = false;
        document.body.classList.add('idn-camera-open');
        modal.querySelector('[data-camera-action="capture"]').focus();
        startCameraStream();
    }

    function switchCamera() {
        if (!cameraSession || cameraSession.capturing) { return; }
        cameraSession.facing = cameraSession.facing === 'environment' ? 'user' : 'environment';
        startCameraStream();
    }

    function captureCameraPhoto() {
        var session = cameraSession;
        if (!session || session.capturing) { return; }
        var video = cameraDialog.querySelector('video');
        if (!session.stream || !video.videoWidth || !video.videoHeight) {
            setCameraStatus('Wait for the live camera preview before taking the photo.', true);
            return;
        }

        session.capturing = true;
        var canvas = document.createElement('canvas');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        var context = canvas.getContext('2d', { alpha: false });
        context.fillStyle = '#fff';
        context.fillRect(0, 0, canvas.width, canvas.height);
        context.drawImage(video, 0, 0, canvas.width, canvas.height);
        setCameraStatus('Preparing photo...', false);

        canvasBlob(canvas, 0.92).then(function (blob) {
            var photo = new File([blob], 'document-camera-' + Date.now() + '.jpg', {
                type: 'image/jpeg',
                lastModified: Date.now()
            });
            var widget = session.widget;
            var slotNumber = session.slotNumber;
            closeCamera();
            return assignFile(widget, slotNumber, photo);
        }).catch(function (error) {
            if (cameraSession === session) { session.capturing = false; }
            setCameraStatus(error.message || 'Photo could not be captured.', true);
            setMessage(session.widget, error.message || 'Photo could not be captured.', true);
        });
    }

    function closeCamera() {
        if (cameraSession) { stopCameraStream(cameraSession); }
        if (cameraDialog) {
            var video = cameraDialog.querySelector('video');
            video.pause();
            video.srcObject = null;
            video.onloadedmetadata = null;
            cameraDialog.hidden = true;
        }
        document.body.classList.remove('idn-camera-open');
        cameraSession = null;
    }

    // One lightweight crop dialog is shared by every identity row.
    var cropDialog = null;
    var cropSession = null;

    function ensureCropDialog() {
        if (cropDialog) { return cropDialog; }
        cropDialog = document.createElement('div');
        cropDialog.className = 'idn-crop-modal';
        cropDialog.hidden = true;
        cropDialog.innerHTML =
            '<div class="idn-crop-backdrop" data-crop-action="cancel"></div>' +
            '<section class="idn-crop-dialog" role="dialog" aria-modal="true" aria-labelledby="idnCropTitle">' +
                '<div class="idn-crop-head"><div><h2 id="idnCropTitle">Crop document</h2>' +
                '<p>Free allows any size; resize from any corner/edge. Ratio presets stay locked.</p></div>' +
                '<button type="button" class="idn-crop-close" data-crop-action="cancel" aria-label="Close">&times;</button></div>' +
                '<div class="idn-crop-toolbar">' +
                    '<div class="idn-crop-ratios" role="group" aria-label="Crop aspect ratio">' +
                        '<span>Ratio</span>' +
                        '<button type="button" class="idn-ratio-btn is-active" data-crop-ratio="free" aria-pressed="true">Free</button>' +
                        '<button type="button" class="idn-ratio-btn" data-crop-ratio="16:9" aria-pressed="false">16:9</button>' +
                        '<button type="button" class="idn-ratio-btn" data-crop-ratio="9:16" aria-pressed="false">9:16</button>' +
                    '</div>' +
                    '<output class="idn-crop-size" data-crop-size aria-live="polite"></output>' +
                '</div>' +
                '<div class="idn-crop-stage"><canvas aria-label="Document crop area"></canvas></div>' +
                '<div class="idn-crop-foot">' +
                    '<button type="button" class="erp-btn erp-btn-ghost" data-crop-action="full">Use full image</button>' +
                    '<div><button type="button" class="erp-btn erp-btn-ghost" data-crop-action="cancel">Cancel</button> ' +
                    '<button type="button" class="erp-btn erp-btn-primary" data-crop-action="save">Apply crop</button></div>' +
                '</div>' +
            '</section>';
        document.body.appendChild(cropDialog);

        var canvas = cropDialog.querySelector('canvas');
        canvas.addEventListener('pointerdown', cropPointerDown);
        canvas.addEventListener('pointermove', cropPointerMove);
        canvas.addEventListener('pointerup', cropPointerUp);
        canvas.addEventListener('pointercancel', cropPointerUp);
        cropDialog.addEventListener('click', function (event) {
            var ratioNode = event.target.closest('[data-crop-ratio]');
            if (ratioNode) {
                setCropAspect(ratioNode.getAttribute('data-crop-ratio'));
                return;
            }
            var actionNode = event.target.closest('[data-crop-action]');
            if (!actionNode) { return; }
            var action = actionNode.getAttribute('data-crop-action');
            if (action === 'cancel') { closeCrop(); }
            else if (action === 'full' && cropSession) {
                setCropAspect('free');
                cropSession.crop = { x: 0, y: 0, w: cropSession.draw.w, h: cropSession.draw.h };
                drawCrop();
            } else if (action === 'save') { saveCrop(); }
        });
        return cropDialog;
    }

    function openCrop(widget, slotNumber, sourceFile) {
        var state = slotState(widget, slotNumber);
        var file = sourceFile || state.file;
        if (!file || isPdf(file)) { return; }
        var modal = ensureCropDialog();
        loadImage(file).then(function (img) {
            var canvas = modal.querySelector('canvas');
            var maxWidth = Math.max(280, Math.min(760, window.innerWidth - 48));
            var scale = Math.min(maxWidth / img.naturalWidth, 520 / img.naturalHeight, 1);
            var width = Math.max(1, Math.round(img.naturalWidth * scale));
            var height = Math.max(1, Math.round(img.naturalHeight * scale));
            canvas.width = width;
            canvas.height = height;
            cropSession = {
                widget: widget,
                slotNumber: slotNumber,
                state: state,
                sourceFile: file,
                image: img,
                canvas: canvas,
                context: canvas.getContext('2d'),
                draw: { x: 0, y: 0, w: width, h: height },
                crop: { x: width * 0.05, y: height * 0.05, w: width * 0.9, h: height * 0.9 },
                aspectCrops: {
                    free: { x: width * 0.05, y: height * 0.05, w: width * 0.9, h: height * 0.9 }
                },
                aspectRatio: null,
                aspectKey: 'free',
                aspectLabel: 'Free',
                dragging: false,
                mode: 'select',
                handle: null
            };
            updateCropRatioButtons();
            drawCrop();
            modal.hidden = false;
            document.body.classList.add('idn-crop-open');
            modal.querySelector('[data-crop-action="save"]').focus();
        }).catch(function (error) {
            setMessage(widget, error.message, true);
        });
    }

    function openSlotCrop(widget, slotNumber) {
        var state = slotState(widget, slotNumber);
        if (state.file) {
            openCrop(widget, slotNumber, state.file);
            return;
        }
        if (!hasSavedFile(state) || state.existingKind !== 'image' || !state.existingUrl) {
            return;
        }
        if (typeof window.fetch !== 'function') {
            setMessage(widget, 'This browser cannot crop a saved image. Choose the image again first.', true);
            return;
        }

        widget._idnBusy += 1;
        widget.classList.add('is-processing');
        setMessage(widget, 'Loading saved image...', false);
        window.fetch(state.existingUrl, {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: {
                Accept: 'image/jpeg,image/png',
                'X-Property-Context-Token': window.APP_PROPERTY_CONTEXT_TOKEN || ''
            }
        }).then(function (response) {
            var type = String(response.headers.get('Content-Type') || '').split(';')[0].toLowerCase();
            if (!response.ok || ['image/jpeg', 'image/png'].indexOf(type) === -1) {
                throw new Error('The saved image could not be loaded for cropping.');
            }
            return response.blob().then(function (blob) {
                if (!blob.size || blob.size > MAX_UPLOAD_BYTES) {
                    throw new Error('The saved image is too large to crop on this device.');
                }
                var extensionName = type === 'image/png' ? '.png' : '.jpg';
                return new File([blob], 'image-' + slotNumber + extensionName, {
                    type: type,
                    lastModified: Date.now()
                });
            });
        }).then(function (file) {
            setMessage(widget, '', false);
            openCrop(widget, slotNumber, file);
        }).catch(function (error) {
            setMessage(widget, error.message || 'The saved image could not be loaded for cropping.', true);
        }).then(function () {
            widget._idnBusy -= 1;
            widget.classList.toggle('is-processing', widget._idnBusy > 0);
        });
    }

    function cropPoint(event) {
        var rect = cropSession.canvas.getBoundingClientRect();
        return {
            x: Math.max(0, Math.min(cropSession.canvas.width, (event.clientX - rect.left) * cropSession.canvas.width / rect.width)),
            y: Math.max(0, Math.min(cropSession.canvas.height, (event.clientY - rect.top) * cropSession.canvas.height / rect.height))
        };
    }

    function insideCrop(point, crop) {
        return point.x >= crop.x && point.x <= crop.x + crop.w
            && point.y >= crop.y && point.y <= crop.y + crop.h;
    }

    function cropClamp(value, minimum, maximum) {
        return Math.max(minimum, Math.min(maximum, value));
    }

    function cropHandlePoints(crop) {
        var centerX = crop.x + crop.w / 2;
        var centerY = crop.y + crop.h / 2;
        return {
            nw: { x: crop.x, y: crop.y },
            n:  { x: centerX, y: crop.y },
            ne: { x: crop.x + crop.w, y: crop.y },
            e:  { x: crop.x + crop.w, y: centerY },
            se: { x: crop.x + crop.w, y: crop.y + crop.h },
            s:  { x: centerX, y: crop.y + crop.h },
            sw: { x: crop.x, y: crop.y + crop.h },
            w:  { x: crop.x, y: centerY }
        };
    }

    function cropHandleAt(point, crop) {
        var handles = cropHandlePoints(crop);
        var order = ['nw', 'ne', 'se', 'sw', 'n', 'e', 's', 'w'];
        // A 24px radius creates an approximately 48px touch target without
        // making the visible handles visually heavy on a small phone.
        var hitSize = 24;
        for (var i = 0; i < order.length; i += 1) {
            var name = order[i];
            if (
                Math.abs(point.x - handles[name].x) <= hitSize
                && Math.abs(point.y - handles[name].y) <= hitSize
            ) {
                return name;
            }
        }
        return null;
    }

    function cropCursor(handle) {
        if (handle === 'n' || handle === 's') { return 'ns-resize'; }
        if (handle === 'e' || handle === 'w') { return 'ew-resize'; }
        if (handle === 'nw' || handle === 'se') { return 'nwse-resize'; }
        if (handle === 'ne' || handle === 'sw') { return 'nesw-resize'; }
        return 'crosshair';
    }

    function updateCropRatioButtons() {
        if (!cropDialog || !cropSession) { return; }
        Array.prototype.forEach.call(cropDialog.querySelectorAll('[data-crop-ratio]'), function (button) {
            var active = button.getAttribute('data-crop-ratio') === cropSession.aspectKey;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
    }

    function copyCrop(crop) {
        return { x: crop.x, y: crop.y, w: crop.w, h: crop.h };
    }

    function fitCropToAspect(crop, ratio, draw) {
        var centerX = crop.x + crop.w / 2;
        var centerY = crop.y + crop.h / 2;
        var width = Math.min(crop.w, crop.h * ratio);
        var height = width / ratio;
        if (height > crop.h) {
            height = crop.h;
            width = height * ratio;
        }
        width = Math.min(width, draw.w);
        height = Math.min(height, draw.h);
        return {
            x: cropClamp(centerX - width / 2, 0, draw.w - width),
            y: cropClamp(centerY - height / 2, 0, draw.h - height),
            w: width,
            h: height
        };
    }

    function setCropAspect(value) {
        if (!cropSession) { return; }
        var ratios = { '16:9': 16 / 9, '9:16': 9 / 16 };
        var nextKey = ratios[value] ? value : 'free';

        // Re-clicking an active ratio must be a no-op. When moving between
        // portrait and landscape presets, keep one crop box per ratio. Using
        // the already-fitted box as the next input would shrink the coverage
        // on every 16:9 -> 9:16 -> 16:9 cycle.
        if (cropSession.aspectKey === nextKey) {
            updateCropRatioButtons();
            drawCrop();
            return;
        }

        cropSession.aspectCrops[cropSession.aspectKey] = copyCrop(cropSession.crop);
        cropSession.aspectRatio = ratios[nextKey] || null;
        cropSession.aspectKey = nextKey;
        cropSession.aspectLabel = ratios[nextKey] ? nextKey : 'Free';

        var storedCrop = cropSession.aspectCrops[nextKey];
        if (storedCrop) {
            cropSession.crop = copyCrop(storedCrop);
        } else if (cropSession.aspectRatio) {
            var referenceCrop = cropSession.aspectCrops.free || cropSession.crop;
            cropSession.crop = fitCropToAspect(referenceCrop, cropSession.aspectRatio, cropSession.draw);
            cropSession.aspectCrops[nextKey] = copyCrop(cropSession.crop);
        }
        updateCropRatioButtons();
        drawCrop();
    }

    function resizeFreeCrop(start, handle, point, draw) {
        var left = start.x;
        var top = start.y;
        var right = start.x + start.w;
        var bottom = start.y + start.h;
        var minimum = 20;
        if (handle.indexOf('w') !== -1) { left = cropClamp(point.x, 0, right - minimum); }
        if (handle.indexOf('e') !== -1) { right = cropClamp(point.x, left + minimum, draw.w); }
        if (handle.indexOf('n') !== -1) { top = cropClamp(point.y, 0, bottom - minimum); }
        if (handle.indexOf('s') !== -1) { bottom = cropClamp(point.y, top + minimum, draw.h); }
        return { x: left, y: top, w: right - left, h: bottom - top };
    }

    function cropSizeWithin(desired, maximum, minimum) {
        if (maximum <= minimum) { return maximum; }
        return cropClamp(desired, minimum, maximum);
    }

    function resizeLockedCrop(start, handle, point, ratio, draw) {
        var isWest = handle.indexOf('w') !== -1;
        var isEast = handle.indexOf('e') !== -1;
        var isNorth = handle.indexOf('n') !== -1;
        var isSouth = handle.indexOf('s') !== -1;
        var minimumWidth = Math.max(20, 20 * ratio);

        if ((isWest || isEast) && (isNorth || isSouth)) {
            var anchorX = isWest ? start.x + start.w : start.x;
            var anchorY = isNorth ? start.y + start.h : start.y;
            var widthByX = Math.abs(point.x - anchorX);
            var widthByY = Math.abs(point.y - anchorY) * ratio;
            var desiredWidth = Math.abs(widthByX - start.w) >= Math.abs(widthByY - start.w)
                ? widthByX : widthByY;
            var maximumWidth = Math.min(
                isWest ? anchorX : draw.w - anchorX,
                (isNorth ? anchorY : draw.h - anchorY) * ratio
            );
            var width = cropSizeWithin(desiredWidth, maximumWidth, minimumWidth);
            var height = width / ratio;
            return {
                x: isWest ? anchorX - width : anchorX,
                y: isNorth ? anchorY - height : anchorY,
                w: width,
                h: height
            };
        }

        if (isWest || isEast) {
            var fixedX = isWest ? start.x + start.w : start.x;
            var centerY = start.y + start.h / 2;
            var horizontalLimit = isWest ? fixedX : draw.w - fixedX;
            var verticalLimit = Math.max(0, 2 * Math.min(centerY, draw.h - centerY) * ratio);
            var sideWidth = cropSizeWithin(
                Math.abs(point.x - fixedX),
                Math.min(horizontalLimit, verticalLimit),
                minimumWidth
            );
            var sideHeight = sideWidth / ratio;
            return {
                x: isWest ? fixedX - sideWidth : fixedX,
                y: centerY - sideHeight / 2,
                w: sideWidth,
                h: sideHeight
            };
        }

        var fixedY = isNorth ? start.y + start.h : start.y;
        var centerX = start.x + start.w / 2;
        var verticalMaximum = isNorth ? fixedY : draw.h - fixedY;
        var horizontalMaximum = Math.max(0, 2 * Math.min(centerX, draw.w - centerX) / ratio);
        var minimumHeight = minimumWidth / ratio;
        var edgeHeight = cropSizeWithin(
            Math.abs(point.y - fixedY),
            Math.min(verticalMaximum, horizontalMaximum),
            minimumHeight
        );
        var edgeWidth = edgeHeight * ratio;
        return {
            x: centerX - edgeWidth / 2,
            y: isNorth ? fixedY - edgeHeight : fixedY,
            w: edgeWidth,
            h: edgeHeight
        };
    }

    function createLockedCrop(start, point, ratio, draw) {
        var directionX = point.x >= start.x ? 1 : -1;
        var directionY = point.y >= start.y ? 1 : -1;
        var rawWidth = Math.abs(point.x - start.x);
        var rawHeight = Math.abs(point.y - start.y);
        var width;
        var height;
        if (rawHeight > 0 && rawWidth / rawHeight > ratio) {
            width = rawWidth;
            height = width / ratio;
        } else {
            height = rawHeight;
            width = height * ratio;
        }
        var maxWidth = directionX > 0 ? draw.w - start.x : start.x;
        var maxHeight = directionY > 0 ? draw.h - start.y : start.y;
        var scale = Math.min(1, width ? maxWidth / width : 1, height ? maxHeight / height : 1);
        width = Math.max(1, width * scale);
        height = Math.max(1, height * scale);
        return {
            x: directionX > 0 ? start.x : start.x - width,
            y: directionY > 0 ? start.y : start.y - height,
            w: width,
            h: height
        };
    }

    function cropPointerDown(event) {
        if (!cropSession) { return; }
        event.preventDefault();
        var point = cropPoint(event);
        cropSession.dragging = true;
        cropSession.canvas.setPointerCapture(event.pointerId);
        var crop = cropSession.crop;
        var handle = cropHandleAt(point, crop);
        cropSession.originalCrop = { x: crop.x, y: crop.y, w: crop.w, h: crop.h };
        if (handle) {
            cropSession.mode = 'resize';
            cropSession.handle = handle;
            cropSession.start = point;
            cropSession.startCrop = { x: crop.x, y: crop.y, w: crop.w, h: crop.h };
        } else if (insideCrop(point, crop)) {
            cropSession.mode = 'move';
            cropSession.offset = { x: point.x - cropSession.crop.x, y: point.y - cropSession.crop.y };
        } else {
            cropSession.mode = 'select';
            cropSession.handle = null;
            cropSession.start = point;
            cropSession.crop = { x: point.x, y: point.y, w: 1, h: 1 };
        }
        drawCrop();
    }

    function cropPointerMove(event) {
        if (!cropSession) { return; }
        var point = cropPoint(event);
        if (!cropSession.dragging) {
            var hoveredHandle = cropHandleAt(point, cropSession.crop);
            cropSession.canvas.style.cursor = hoveredHandle
                ? cropCursor(hoveredHandle)
                : (insideCrop(point, cropSession.crop) ? 'move' : 'crosshair');
            return;
        }
        var draw = cropSession.draw;
        if (cropSession.mode === 'move') {
            cropSession.crop.x = Math.max(0, Math.min(draw.w - cropSession.crop.w, point.x - cropSession.offset.x));
            cropSession.crop.y = Math.max(0, Math.min(draw.h - cropSession.crop.h, point.y - cropSession.offset.y));
        } else if (cropSession.mode === 'resize') {
            cropSession.crop = cropSession.aspectRatio
                ? resizeLockedCrop(cropSession.startCrop, cropSession.handle, point, cropSession.aspectRatio, draw)
                : resizeFreeCrop(cropSession.startCrop, cropSession.handle, point, draw);
        } else {
            cropSession.crop = cropSession.aspectRatio
                ? createLockedCrop(cropSession.start, point, cropSession.aspectRatio, draw)
                : {
                    x: Math.min(point.x, cropSession.start.x),
                    y: Math.min(point.y, cropSession.start.y),
                    w: Math.abs(point.x - cropSession.start.x),
                    h: Math.abs(point.y - cropSession.start.y)
                };
        }
        drawCrop();
    }

    function cropPointerUp(event) {
        if (!cropSession) { return; }
        cropSession.dragging = false;
        cropSession.handle = null;
        if (cropSession.crop.w < 20 || cropSession.crop.h < 20) {
            cropSession.crop = cropSession.originalCrop;
        }
        var point = cropPoint(event);
        var handle = cropHandleAt(point, cropSession.crop);
        cropSession.canvas.style.cursor = handle
            ? cropCursor(handle)
            : (insideCrop(point, cropSession.crop) ? 'move' : 'crosshair');
        drawCrop();
        try { cropSession.canvas.releasePointerCapture(event.pointerId); } catch (ignore) {}
    }

    function drawCrop() {
        if (!cropSession) { return; }
        var ctx = cropSession.context;
        var d = cropSession.draw;
        var c = cropSession.crop;
        ctx.clearRect(0, 0, d.w, d.h);
        ctx.drawImage(cropSession.image, 0, 0, d.w, d.h);
        ctx.save();
        ctx.fillStyle = 'rgba(0, 0, 0, 0.55)';
        ctx.beginPath();
        ctx.rect(0, 0, d.w, d.h);
        ctx.rect(c.x, c.y, c.w, c.h);
        ctx.fill('evenodd');
        ctx.strokeStyle = '#fff';
        ctx.lineWidth = 2;
        ctx.setLineDash([]);
        ctx.strokeRect(c.x, c.y, c.w, c.h);

        // Rule-of-thirds guide inside the selected region.
        ctx.save();
        ctx.beginPath();
        ctx.rect(c.x, c.y, c.w, c.h);
        ctx.clip();
        ctx.strokeStyle = 'rgba(255,255,255,.55)';
        ctx.lineWidth = 1;
        ctx.beginPath();
        ctx.moveTo(c.x + c.w / 3, c.y);
        ctx.lineTo(c.x + c.w / 3, c.y + c.h);
        ctx.moveTo(c.x + c.w * 2 / 3, c.y);
        ctx.lineTo(c.x + c.w * 2 / 3, c.y + c.h);
        ctx.moveTo(c.x, c.y + c.h / 3);
        ctx.lineTo(c.x + c.w, c.y + c.h / 3);
        ctx.moveTo(c.x, c.y + c.h * 2 / 3);
        ctx.lineTo(c.x + c.w, c.y + c.h * 2 / 3);
        ctx.stroke();
        ctx.restore();

        // Eight large touch-friendly handles: four corners and four edges.
        var handles = cropHandlePoints(c);
        Object.keys(handles).forEach(function (name) {
            var handle = handles[name];
            var size = name.length === 2 ? 14 : 11;
            ctx.fillStyle = '#fff';
            ctx.fillRect(handle.x - size / 2, handle.y - size / 2, size, size);
            ctx.strokeStyle = '#6d5df6';
            ctx.lineWidth = 2;
            ctx.strokeRect(handle.x - size / 2, handle.y - size / 2, size, size);
        });
        ctx.restore();

        var sizeOutput = cropDialog && cropDialog.querySelector('[data-crop-size]');
        if (sizeOutput) {
            var sourceWidth = Math.max(1, Math.round(c.w * cropSession.image.naturalWidth / d.w));
            var sourceHeight = Math.max(1, Math.round(c.h * cropSession.image.naturalHeight / d.h));
            sizeOutput.textContent = sourceWidth + ' × ' + sourceHeight + ' px &middot; ' + cropSession.aspectLabel;
        }
    }

    function saveCrop() {
        if (!cropSession || cropSession.crop.w < 20 || cropSession.crop.h < 20) {
            if (cropSession) { setMessage(cropSession.widget, 'Select a larger crop area.', true); }
            return;
        }
        var session = cropSession;
        var ratioX = session.image.naturalWidth / session.draw.w;
        var ratioY = session.image.naturalHeight / session.draw.h;
        var source = {
            x: Math.round(session.crop.x * ratioX),
            y: Math.round(session.crop.y * ratioY),
            w: Math.round(session.crop.w * ratioX),
            h: Math.round(session.crop.h * ratioY)
        };
        var scale = Math.min(1, MAX_OUTPUT_DIMENSION / Math.max(source.w, source.h));
        var output = document.createElement('canvas');
        output.width = Math.max(1, Math.round(source.w * scale));
        output.height = Math.max(1, Math.round(source.h * scale));
        var context = output.getContext('2d', { alpha: false });
        context.fillStyle = '#fff';
        context.fillRect(0, 0, output.width, output.height);
        context.drawImage(session.image, source.x, source.y, source.w, source.h, 0, 0, output.width, output.height);

        imageFileFromCanvas(output, session.sourceFile.name).then(function (file) {
            putPreparedFile(session.widget, session.slotNumber, file);
            setMessage(session.widget, 'Crop applied. Image is now ' + friendlyBytes(file.size) + '.', false);
            closeCrop();
        }).catch(function (error) {
            setMessage(session.widget, error.message, true);
        });
    }

    function closeCrop() {
        if (!cropDialog) { return; }
        cropDialog.hidden = true;
        document.body.classList.remove('idn-crop-open');
        cropSession = null;
    }

    function enhanceRow(row) {
        var select = row.querySelector('select.erp-select');
        if (select && window.SearchableSelect) { window.SearchableSelect.enhance(select); }
        Array.prototype.forEach.call(row.querySelectorAll('[data-idn-upload]'), initWidget);
    }

    Array.prototype.forEach.call(container.querySelectorAll('[data-idn-upload]'), initWidget);

    if (addBtn) {
        addBtn.addEventListener('click', function () {
            if (container.querySelectorAll('.idn-row').length >= MAX_ROWS) {
                return;
            }
            var fragment = tpl.content.cloneNode(true);
            var row = fragment.querySelector('.idn-row');
            container.appendChild(fragment);
            enhanceRow(row);
        });
    }

    container.addEventListener('click', function (event) {
        var button = event.target.closest('.idn-remove');
        if (!button) { return; }
        var row = button.closest('.idn-row');
        if (!row) { return; }
        Array.prototype.forEach.call(row.querySelectorAll('[data-idn-upload]'), function (widget) {
            (widget._idnSlots || []).forEach(function (state) {
                if (state.previewUrl) { URL.revokeObjectURL(state.previewUrl); }
            });
        });
        row.parentNode.removeChild(row);
    });

    var form = container.closest('form');
    if (form) {
        form.addEventListener('submit', function (event) {
            var busy = Array.prototype.some.call(container.querySelectorAll('[data-idn-upload]'), function (widget) {
                return widget._idnBusy > 0;
            });
            if (busy) {
                event.preventDefault();
                var firstBusy = container.querySelector('[data-idn-upload].is-processing');
                if (firstBusy) { setMessage(firstBusy, 'Please wait for image compression to finish, then save again.', true); }
            }
        });
    }

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') { return; }
        if (cameraDialog && !cameraDialog.hidden) { closeCamera(); }
        else if (cropDialog && !cropDialog.hidden) { closeCrop(); }
    });
})();
