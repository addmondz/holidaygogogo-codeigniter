<?php
defined('BASEPATH') OR exit('No direct script access allowed');
$booking_label = !empty($context->BookingNumber) ? $context->BookingNumber : ('#' . $context->booking_id);
$action_url = base_url('customer-intake/' . urlencode($token) . '/submit');
$old_room_rows = !empty($old['rooms']) && is_array($old['rooms']) ? $old['rooms'] : array(array());
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Booking Details &mdash; <?php echo htmlspecialchars($booking_label); ?></title>
<style>
* { box-sizing: border-box; }
body {
    margin: 0;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    background: #f4f6fa;
    color: #1f2533;
    line-height: 1.5;
}
.shell { max-width: 720px; margin: 0 auto; padding: 24px 16px 80px; }
.header { background: #1c3d5a; color: #fff; padding: 24px 20px; border-radius: 12px 12px 0 0; }
.header h1 { margin: 0 0 6px; font-size: 20px; font-weight: 600; }
.header p { margin: 0; opacity: 0.85; font-size: 14px; }
.card { background: #fff; padding: 24px 20px; border-radius: 0 0 12px 12px; box-shadow: 0 1px 3px rgba(15,23,42,0.06); }
.section { margin-bottom: 28px; }
.section h2 { font-size: 15px; font-weight: 600; margin: 0 0 12px; color: #1c3d5a; text-transform: uppercase; letter-spacing: 0.04em; }
.field { margin-bottom: 16px; }
.field label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: #28324a; }
.field .hint { font-weight: normal; color: #6b7385; font-size: 12px; margin-left: 4px; }
.field input[type=text], .field input[type=tel], .field input[type=date], .field input[type=number], .field textarea, .field select {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #cbd2dd;
    border-radius: 8px;
    font: inherit;
    background: #fff;
    color: #1f2533;
}
.field input:focus, .field textarea:focus, .field select:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.18); }
.field textarea { resize: vertical; min-height: 80px; }
.travel-range { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 0; }
.travel-range .field { margin-bottom: 16px; }
@media (max-width: 540px) { .travel-range { grid-template-columns: 1fr; } }
.error { color: #c0392b; font-size: 12px; margin-top: 4px; }
.error-banner { background: #fff1f0; color: #b3261e; border: 1px solid #f5c6c2; padding: 10px 14px; border-radius: 8px; margin-bottom: 16px; font-size: 14px; }
.room-card { border: 1px solid #d8dde8; border-radius: 10px; padding: 16px; margin-bottom: 14px; background: #fafbfd; }
.room-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
.room-head h3 { margin: 0; font-size: 14px; font-weight: 700; color: #1c3d5a; }
.room-grid { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 12px; margin-bottom: 12px; }
@media (max-width: 540px) { .room-grid { grid-template-columns: 1fr 1fr; } }
.age-list { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 6px; }
.age-list input { width: 80px; padding: 6px 8px; border: 1px solid #cbd2dd; border-radius: 6px; font: inherit; }
.age-row { font-size: 12px; color: #4a5266; margin-top: 4px; }
.btn { display: inline-block; padding: 10px 16px; border: none; border-radius: 8px; font: inherit; font-weight: 600; cursor: pointer; }
.btn-primary { background: #2563eb; color: #fff; }
.btn-primary:hover { background: #1e4dbb; }
.btn-ghost { background: transparent; color: #2563eb; border: 1px dashed #2563eb; }
.btn-ghost:hover { background: rgba(37,99,235,0.06); }
.btn-icon { background: transparent; color: #b3261e; border: none; font-size: 18px; cursor: pointer; line-height: 1; padding: 4px 8px; }
.actions { display: flex; gap: 12px; justify-content: flex-end; padding-top: 12px; border-top: 1px solid #e3e7ef; }
.footer-note { font-size: 12px; color: #6b7385; text-align: center; margin-top: 16px; }
</style>
</head>
<body>
<div class="shell">
    <div class="header">
        <h1>Tell us about your trip</h1>
        <p>Booking reference: <strong><?php echo htmlspecialchars($booking_label); ?></strong></p>
    </div>
    <div class="card">
        <?php if (!empty($errors)): ?>
            <div class="error-banner">Please fix the highlighted fields below before submitting.</div>
        <?php endif; ?>

        <form method="post" action="<?php echo htmlspecialchars($action_url); ?>" id="intake-form" novalidate>
            <div class="section">
                <h2>Lead Guest</h2>
                <div class="field">
                    <label for="booking_name">Booking Name <span class="hint">(Full name as per IC / Passport)</span></label>
                    <input type="text" id="booking_name" name="booking_name" required
                           value="<?php echo htmlspecialchars(isset($old['booking_name']) ? $old['booking_name'] : ''); ?>">
                    <?php if (!empty($errors['booking_name'])): ?><div class="error"><?php echo htmlspecialchars($errors['booking_name']); ?></div><?php endif; ?>
                </div>
                <div class="field">
                    <label for="contact_number">Contact Number</label>
                    <input type="tel" id="contact_number" name="contact_number" required
                           value="<?php echo htmlspecialchars(isset($old['contact_number']) ? $old['contact_number'] : ''); ?>">
                    <?php if (!empty($errors['contact_number'])): ?><div class="error"><?php echo htmlspecialchars($errors['contact_number']); ?></div><?php endif; ?>
                </div>
                <div class="field">
                    <label for="ic_passport_no">IC / Passport No.</label>
                    <input type="text" id="ic_passport_no" name="ic_passport_no" required
                           value="<?php echo htmlspecialchars(isset($old['ic_passport_no']) ? $old['ic_passport_no'] : ''); ?>">
                    <?php if (!empty($errors['ic_passport_no'])): ?><div class="error"><?php echo htmlspecialchars($errors['ic_passport_no']); ?></div><?php endif; ?>
                </div>
                <div class="travel-range">
                    <div class="field">
                        <label for="travel_start_date">Travel Start Date</label>
                        <input type="date" id="travel_start_date" name="travel_start_date" required
                               min="<?php echo date('Y-m-d'); ?>"
                               value="<?php echo htmlspecialchars(isset($old['travel_start_date']) ? $old['travel_start_date'] : ''); ?>">
                        <?php if (!empty($errors['travel_start_date'])): ?><div class="error"><?php echo htmlspecialchars($errors['travel_start_date']); ?></div><?php endif; ?>
                    </div>
                    <div class="field">
                        <label for="travel_end_date">Travel End Date</label>
                        <input type="date" id="travel_end_date" name="travel_end_date" required
                               min="<?php echo htmlspecialchars(!empty($old['travel_start_date']) ? $old['travel_start_date'] : date('Y-m-d')); ?>"
                               value="<?php echo htmlspecialchars(isset($old['travel_end_date']) ? $old['travel_end_date'] : ''); ?>">
                        <?php if (!empty($errors['travel_end_date'])): ?><div class="error"><?php echo htmlspecialchars($errors['travel_end_date']); ?></div><?php endif; ?>
                    </div>
                </div>
                <div class="field">
                    <label for="special_remarks">Special Remarks <span class="hint">(optional)</span></label>
                    <textarea id="special_remarks" name="special_remarks" placeholder="Dietary, mobility, anniversary, etc."><?php echo htmlspecialchars(isset($old['special_remarks']) ? $old['special_remarks'] : ''); ?></textarea>
                </div>
            </div>

            <div class="section">
                <h2>Rooms &amp; Pax</h2>
                <?php if (!empty($errors['rooms'])): ?><div class="error" style="margin-bottom:10px;"><?php echo htmlspecialchars($errors['rooms']); ?></div><?php endif; ?>
                <div id="rooms-container">
                    <?php foreach ($old_room_rows as $i => $room): ?>
                        <?php
                        $room = is_array($room) ? $room : array();
                        $rtype  = isset($room['room_type'])   ? $room['room_type'] : '';
                        $adult  = isset($room['adult_count']) ? (int) $room['adult_count'] : 0;
                        $child  = isset($room['child_ages'])  ? (is_array($room['child_ages']) ? $room['child_ages'] : array_filter(explode(',', (string) $room['child_ages']), 'strlen')) : array();
                        $baby   = isset($room['baby_ages'])   ? (is_array($room['baby_ages'])  ? $room['baby_ages']  : array_filter(explode(',', (string) $room['baby_ages']),  'strlen')) : array();
                        ?>
                        <div class="room-card" data-room-card>
                            <div class="room-head">
                                <h3>Room <span data-room-number><?php echo $i + 1; ?></span></h3>
                                <button type="button" class="btn-icon" data-remove-room aria-label="Remove room">&times;</button>
                            </div>
                            <div class="room-grid">
                                <div class="field" style="margin-bottom:0;">
                                    <label>Room Type</label>
                                    <input type="text" name="rooms[<?php echo $i; ?>][room_type]" value="<?php echo htmlspecialchars($rtype); ?>" placeholder="e.g. Deluxe Twin">
                                </div>
                                <div class="field" style="margin-bottom:0;">
                                    <label>Adults</label>
                                    <input type="number" min="0" name="rooms[<?php echo $i; ?>][adult_count]" value="<?php echo (int) $adult; ?>">
                                </div>
                                <div class="field" style="margin-bottom:0;">
                                    <label>Children</label>
                                    <input type="number" min="0" data-age-count data-target="child" value="<?php echo count((array) $child); ?>">
                                </div>
                                <div class="field" style="margin-bottom:0;">
                                    <label>Babies</label>
                                    <input type="number" min="0" data-age-count data-target="baby" value="<?php echo count((array) $baby); ?>">
                                </div>
                            </div>
                            <div class="age-row" data-age-section="child" data-room-index="<?php echo $i; ?>" style="<?php echo empty($child) ? 'display:none;' : ''; ?>">
                                Child age(s):
                                <div class="age-list" data-age-list="child">
                                    <?php foreach ((array) $child as $age): ?>
                                        <input type="number" min="0" max="17" name="rooms[<?php echo $i; ?>][child_ages][]" value="<?php echo htmlspecialchars((string) $age); ?>" placeholder="Age">
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="age-row" data-age-section="baby" data-room-index="<?php echo $i; ?>" style="<?php echo empty($baby) ? 'display:none;' : ''; ?>">
                                Baby age(s):
                                <div class="age-list" data-age-list="baby">
                                    <?php foreach ((array) $baby as $age): ?>
                                        <input type="number" min="0" max="3" name="rooms[<?php echo $i; ?>][baby_ages][]" value="<?php echo htmlspecialchars((string) $age); ?>" placeholder="Age">
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" id="add-room-btn" class="btn btn-ghost">+ Add Room</button>
            </div>

            <div class="actions">
                <button type="submit" class="btn btn-primary">Submit Details</button>
            </div>
        </form>
        <p class="footer-note">You can only submit this form once. Please double-check before clicking Submit.</p>
    </div>
</div>

<script>
(function() {
    // Keep travel_end_date's minimum in lockstep with travel_start_date so the
    // native date picker refuses end < start. Also auto-bump end if start moves
    // past the currently-selected end value.
    var startInput = document.getElementById('travel_start_date');
    var endInput   = document.getElementById('travel_end_date');
    if (startInput && endInput) {
        startInput.addEventListener('change', function() {
            if (startInput.value) {
                endInput.min = startInput.value;
                if (endInput.value && endInput.value < startInput.value) {
                    endInput.value = startInput.value;
                }
            }
        });
    }
})();
(function() {
    var container = document.getElementById('rooms-container');
    var addBtn    = document.getElementById('add-room-btn');

    function reindexRooms() {
        var rooms = container.querySelectorAll('[data-room-card]');
        rooms.forEach(function(card, idx) {
            card.querySelector('[data-room-number]').textContent = (idx + 1);
            card.querySelectorAll('input[name]').forEach(function(input) {
                input.name = input.name.replace(/rooms\[\d+\]/, 'rooms[' + idx + ']');
            });
            card.querySelectorAll('[data-age-section]').forEach(function(sec) {
                sec.setAttribute('data-room-index', idx);
            });
        });
    }

    function buildRoomCard(idx) {
        var card = document.createElement('div');
        card.className = 'room-card';
        card.setAttribute('data-room-card', '');
        card.innerHTML =
            '<div class="room-head">' +
            '<h3>Room <span data-room-number>' + (idx + 1) + '</span></h3>' +
            '<button type="button" class="btn-icon" data-remove-room aria-label="Remove room">&times;</button>' +
            '</div>' +
            '<div class="room-grid">' +
              '<div class="field" style="margin-bottom:0;"><label>Room Type</label>' +
                '<input type="text" name="rooms[' + idx + '][room_type]" placeholder="e.g. Deluxe Twin"></div>' +
              '<div class="field" style="margin-bottom:0;"><label>Adults</label>' +
                '<input type="number" min="0" name="rooms[' + idx + '][adult_count]" value="0"></div>' +
              '<div class="field" style="margin-bottom:0;"><label>Children</label>' +
                '<input type="number" min="0" data-age-count data-target="child" value="0"></div>' +
              '<div class="field" style="margin-bottom:0;"><label>Babies</label>' +
                '<input type="number" min="0" data-age-count data-target="baby" value="0"></div>' +
            '</div>' +
            '<div class="age-row" data-age-section="child" data-room-index="' + idx + '" style="display:none;">' +
              'Child age(s): <div class="age-list" data-age-list="child"></div>' +
            '</div>' +
            '<div class="age-row" data-age-section="baby" data-room-index="' + idx + '" style="display:none;">' +
              'Baby age(s): <div class="age-list" data-age-list="baby"></div>' +
            '</div>';
        return card;
    }

    function syncAgeInputs(card, target, count) {
        var section = card.querySelector('[data-age-section="' + target + '"]');
        var list    = section.querySelector('[data-age-list="' + target + '"]');
        var idx     = section.getAttribute('data-room-index');
        var existing = list.querySelectorAll('input');

        if (count > 0) {
            section.style.display = '';
        } else {
            section.style.display = 'none';
        }

        if (count > existing.length) {
            for (var i = existing.length; i < count; i++) {
                var input = document.createElement('input');
                input.type = 'number';
                input.min = '0';
                input.max = (target === 'baby') ? '3' : '17';
                input.name = 'rooms[' + idx + '][' + target + '_ages][]';
                input.placeholder = 'Age';
                list.appendChild(input);
            }
        } else if (count < existing.length) {
            for (var j = existing.length - 1; j >= count; j--) {
                existing[j].parentNode.removeChild(existing[j]);
            }
        }
    }

    container.addEventListener('input', function(e) {
        var t = e.target;
        if (t.matches('[data-age-count]')) {
            var card = t.closest('[data-room-card]');
            var target = t.getAttribute('data-target');
            var n = Math.max(0, parseInt(t.value, 10) || 0);
            syncAgeInputs(card, target, n);
        }
    });

    container.addEventListener('click', function(e) {
        if (e.target.matches('[data-remove-room]')) {
            var card = e.target.closest('[data-room-card]');
            if (container.querySelectorAll('[data-room-card]').length <= 1) {
                return; // Always keep at least one room.
            }
            card.parentNode.removeChild(card);
            reindexRooms();
        }
    });

    addBtn.addEventListener('click', function() {
        var idx = container.querySelectorAll('[data-room-card]').length;
        container.appendChild(buildRoomCard(idx));
    });

    // Initialise age sections for any pre-filled rooms (after a validation error).
    container.querySelectorAll('[data-room-card]').forEach(function(card) {
        ['child', 'baby'].forEach(function(target) {
            var counter = card.querySelector('[data-age-count][data-target="' + target + '"]');
            if (counter) {
                syncAgeInputs(card, target, parseInt(counter.value, 10) || 0);
            }
        });
    });
})();
</script>
</body>
</html>
