<?php
/** @var array<string,mixed> $event */
$id = (int)($event['id'] ?? 0);
$dtLocal = static fn($v) => $v ? date('Y-m-d\TH:i', strtotime((string)$v)) : '';
?>
<div class="card shadow-sm border-0 mb-3">
    <div class="card-body">
        <h6 class="fw-bold"><?= $id ? 'Event file' : 'New event' ?></h6>
        <form method="POST" enctype="multipart/form-data" class="row g-2" id="eventFileForm">
            <input type="hidden" name="event_id" value="<?= $id ?>">
            <input type="hidden" name="save_event" value="1">
            <input type="hidden" name="cover_framed" id="eventCoverFramed" value="0">
            <div class="col-12"><label class="form-label small fw-bold text-muted">NAME</label><input name="title" class="form-control" value="<?= htmlspecialchars((string)$event['title']) ?>" required></div>
            <div class="col-md-6"><label class="form-label small fw-bold text-muted">STARTS</label><input type="datetime-local" name="starts_at" class="form-control" value="<?= htmlspecialchars($dtLocal($event['starts_at'] ?? '')) ?>" required></div>
            <div class="col-md-6"><label class="form-label small fw-bold text-muted">ENDS</label><input type="datetime-local" name="ends_at" class="form-control" value="<?= htmlspecialchars($dtLocal($event['ends_at'] ?? '')) ?>"><div class="form-text">Leave blank if it does not end.</div></div>
            <div class="col-md-6">
                <label class="form-label small fw-bold text-muted">STORE</label>
                <select name="location_id" class="form-select">
                    <option value="0">No store</option>
                    <?php foreach ($locations as $loc): ?>
                    <option value="<?= (int)$loc['id'] ?>" <?= (int)($event['location_id'] ?? 0) === (int)$loc['id'] ? 'selected' : '' ?>><?= htmlspecialchars($loc['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6"><label class="form-label small fw-bold text-muted">VENUE NOTE</label><input name="venue_note" class="form-control" value="<?= htmlspecialchars((string)($event['venue_note'] ?? '')) ?>"></div>
            <div class="col-md-4">
                <label class="form-label small fw-bold text-muted">VISIBILITY</label>
                <select name="visibility" class="form-select no-search">
                    <option value="private" <?= ($event['visibility'] ?? '') === 'private' ? 'selected' : '' ?>>Private invite</option>
                    <option value="published" <?= ($event['visibility'] ?? '') === 'published' ? 'selected' : '' ?>>On the shop</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-bold text-muted">STATUS</label>
                <select name="status" class="form-select no-search">
                    <?php foreach (['draft','open','closed','cancelled'] as $st): ?>
                    <option value="<?= $st ?>" <?= ($event['status'] ?? 'draft') === $st ? 'selected' : '' ?>><?= $st ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2"><label class="form-label small fw-bold text-muted">CAPACITY</label><input type="number" min="0" name="capacity" class="form-control" value="<?= (int)($event['capacity'] ?? 0) ?>"></div>
            <div class="col-md-2"><label class="form-label small fw-bold text-muted">DEPOSIT</label><input type="number" step="0.01" min="0" name="deposit_amount" class="form-control" value="<?= htmlspecialchars((string)($event['deposit_amount'] ?? '0')) ?>"></div>
            <div class="col-md-4">
                <label class="form-label small fw-bold text-muted">GUEST ACTION</label>
                <select name="signup_mode" class="form-select no-search">
                    <?php foreach (['apply' => 'Apply', 'register' => 'Register', 'rsvp' => 'RSVP'] as $mode => $label): ?>
                    <option value="<?= $mode ?>" <?= ($event['signup_mode'] ?? 'apply') === $mode ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-8 d-flex align-items-end"><p class="form-text mb-2">Guests see this word on the shop. Apply suits a request, Register a class or ticket, and RSVP an invitation.</p></div>
            <div class="col-12"><label class="form-label small fw-bold text-muted">SHORT LINE</label><input name="summary" class="form-control" maxlength="500" value="<?= htmlspecialchars((string)($event['summary'] ?? '')) ?>"></div>
            <div class="col-12"><label class="form-label small fw-bold text-muted">STORY</label><textarea name="story" class="form-control" rows="5" placeholder="The page guests read on the shop."><?= htmlspecialchars((string)($event['story'] ?? '')) ?></textarea></div>
            <div class="col-12">
                <label class="form-label small fw-bold text-muted">COVER</label>
                <input type="hidden" name="cover_path" id="eventCoverPath" value="<?= htmlspecialchars((string)($event['cover_path'] ?? '')) ?>">
                <input type="file" name="cover_upload" id="eventCoverUpload" class="d-none" accept="image/jpeg">
                <div id="eventCoverPreview" class="event-cover-preview"><?php if (!empty($event['cover_path'])): ?><img src="<?= htmlspecialchars(\App\Helpers\Media::url((string)$event['cover_path'])) ?>" alt=""><?php else: ?><span class="text-muted small">No cover yet. Choose a picture and frame it.</span><?php endif; ?></div>
                <div class="d-flex flex-wrap gap-2 mt-2">
                    <label class="btn btn-sm btn-outline-dark mb-0"><i class="bi bi-upload"></i> Choose picture<input type="file" id="eventCoverFile" class="d-none" accept="image/jpeg,image/png,image/webp,image/gif"></label>
                    <button type="button" class="btn btn-sm btn-outline-secondary d-none" id="eventCoverOut"><i class="bi bi-zoom-out"></i> Zoom out</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary d-none" id="eventCoverIn"><i class="bi bi-zoom-in"></i> Zoom in</button>
                    <button type="button" class="btn btn-sm btn-outline-danger" id="eventCoverClear">Remove cover</button>
                </div>
                <div id="eventCoverStage" class="d-none mt-2 rounded overflow-hidden border" style="background:#1c2128;max-height:420px;">
                    <img id="eventCoverImage" alt="" style="display:block;max-width:100%;">
                </div>
                <p class="form-text mb-2">Drag the picture to position it. The preview is what guests see on the shop.</p>
                <?php if (!empty($library)): ?>
                <div class="event-cover-library" id="eventCoverLibrary">
                    <?php foreach ($library as $image): ?>
                    <button type="button" class="event-cover-pick<?= ($event['cover_path'] ?? '') === $image['path'] ? ' active' : '' ?>" data-path="<?= htmlspecialchars($image['path']) ?>">
                        <img src="<?= htmlspecialchars($image['path']) ?>" alt="">
                        <span><?= htmlspecialchars((string)($image['label'] ?? 'Photo')) ?></span>
                    </button>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="col-12"><button type="submit" class="btn btn-dark fw-bold">Save event</button></div>
        </form>
    </div>
</div>
<style>
.event-cover-preview { aspect-ratio: 2 / 1; border-radius: 16px; overflow: hidden; background: #f6efe6; display: flex; align-items: center; justify-content: center; }
.event-cover-preview img { width: 100%; height: 100%; object-fit: cover; display: block; }
.event-cover-library { display: grid; grid-template-columns: repeat(auto-fill, minmax(108px, 1fr)); gap: 8px; max-height: 280px; overflow: auto; }
.event-cover-pick { border: 2px solid transparent; border-radius: 12px; padding: 0; background: #fff; overflow: hidden; text-align: left; }
.event-cover-pick img { width: 100%; height: 72px; object-fit: cover; display: block; }
.event-cover-pick span { display: block; font-size: .72rem; padding: 4px 6px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.event-cover-pick.active { border-color: #212529; }
</style>
<link rel="stylesheet" href="vendor/cropperjs/cropper.min.css">
<script src="vendor/cropperjs/cropper.min.js"></script>
<script>
(function () {
    var form = document.getElementById('eventFileForm');
    var pathInput = document.getElementById('eventCoverPath');
    var framedInput = document.getElementById('eventCoverFramed');
    var uploadInput = document.getElementById('eventCoverUpload');
    var fileInput = document.getElementById('eventCoverFile');
    var stage = document.getElementById('eventCoverStage');
    var image = document.getElementById('eventCoverImage');
    var zoomIn = document.getElementById('eventCoverIn');
    var zoomOut = document.getElementById('eventCoverOut');
    var preview = document.getElementById('eventCoverPreview');
    var cropper = null;
    var timer = null;
    var sending = false;
    var pickedName = '';

    function showPreview(src) {
        preview.textContent = '';
        if (!src) {
            var note = document.createElement('span');
            note.className = 'text-muted small';
            note.textContent = 'No cover yet. Choose a picture and frame it.';
            preview.appendChild(note);
            return;
        }
        var img = document.createElement('img');
        img.alt = '';
        img.src = src;
        preview.appendChild(img);
    }

    function startCrop(src) {
        zoomIn.classList.remove('d-none');
        zoomOut.classList.remove('d-none');
        if (cropper) {
            cropper.destroy();
            cropper = null;
        }
        image.onload = function () {
            stage.classList.remove('d-none');
            if (cropper) cropper.destroy();
            cropper = new Cropper(image, {
                aspectRatio: 2,
                viewMode: 1,
                dragMode: 'move',
                autoCropArea: 1,
                background: false,
                responsive: true,
                crop: function () {
                    clearTimeout(timer);
                    timer = setTimeout(function () {
                        if (!cropper) return;
                        var canvas = cropper.getCroppedCanvas({ width: 640, height: 320, imageSmoothingQuality: 'high' });
                        if (canvas) showPreview(canvas.toDataURL('image/jpeg', 0.85));
                    }, 60);
                }
            });
        };
        image.src = '';
        image.src = src;
    }

    function coverFileName() {
        var title = form.querySelector('[name="title"]');
        var raw = (pickedName || (title ? title.value : '') || 'event-cover');
        var base = String(raw).replace(/\.[a-z0-9]+$/i, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
        return (base || 'event-cover').slice(0, 60) + '-cover.jpg';
    }

    document.querySelectorAll('.event-cover-pick').forEach(function (button) {
        button.addEventListener('click', function () {
            document.querySelectorAll('.event-cover-pick').forEach(function (el) { el.classList.remove('active'); });
            button.classList.add('active');
            pathInput.value = button.getAttribute('data-path') || '';
            pickedName = (button.querySelector('span') || {}).textContent || '';
            startCrop(pathInput.value);
        });
    });

    fileInput.addEventListener('change', function () {
        var chosen = fileInput.files && fileInput.files[0];
        if (!chosen) return;
        if (!/^image\/(jpeg|png|webp|gif)$/.test(chosen.type)) {
            fileInput.value = '';
            return;
        }
        pickedName = chosen.name;
        pathInput.value = '';
        document.querySelectorAll('.event-cover-pick').forEach(function (el) { el.classList.remove('active'); });
        var reader = new FileReader();
        reader.onload = function () { startCrop(String(reader.result || '')); };
        reader.readAsDataURL(chosen);
    });

    zoomIn.addEventListener('click', function () { if (cropper) cropper.zoom(0.1); });
    zoomOut.addEventListener('click', function () { if (cropper) cropper.zoom(-0.1); });

    document.getElementById('eventCoverClear').addEventListener('click', function () {
        pathInput.value = '';
        framedInput.value = '0';
        pickedName = '';
        fileInput.value = '';
        uploadInput.value = '';
        if (cropper) {
            cropper.destroy();
            cropper = null;
        }
        image.removeAttribute('src');
        stage.classList.add('d-none');
        zoomIn.classList.add('d-none');
        zoomOut.classList.add('d-none');
        document.querySelectorAll('.event-cover-pick').forEach(function (el) { el.classList.remove('active'); });
        showPreview('');
    });

    form.addEventListener('submit', function (event) {
        if (sending || !cropper) return;
        event.preventDefault();
        var canvas = cropper.getCroppedCanvas({ width: 1400, height: 700, imageSmoothingQuality: 'high' });
        if (!canvas) return;
        canvas.toBlob(function (blob) {
            if (!blob) return;
            var transfer = new DataTransfer();
            transfer.items.add(new File([blob], coverFileName(), { type: 'image/jpeg' }));
            uploadInput.files = transfer.files;
            if (!uploadInput.files || !uploadInput.files.length) return;
            pathInput.value = '';
            framedInput.value = '1';
            sending = true;
            form.submit();
        }, 'image/jpeg', 0.9);
    });
})();
</script>
