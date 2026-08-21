<?php
/**
 * One competitor product's full profile. Shared by the single-analysis view and
 * each product inside a combined site-crawl report. Expects $p = an associative
 * array of the canonical fields (from competitor_parse_ai_response + details).
 * Set $show_product_source = true to print the product's own source URL at top
 * (used inside the crawl accordion, where each product has a distinct URL).
 */
$P  = (array) $p;
$sv = function ($k) use ($P) { $v = isset($P[$k]) ? $P[$k] : ''; return is_array($v) ? '' : trim((string) $v); };
$av = function ($k) use ($P) { $v = isset($P[$k]) ? $P[$k] : array(); return is_array($v) ? $v : array(); };
// Flatten a list into clean non-empty strings — tolerant of nested-array items
// (some AI replies put objects/arrays inside a list), so strval never warns.
$flat = function ($items) {
    if ( ! is_array($items)) { return array(); }
    $out = array();
    foreach ($items as $it) {
        if (is_array($it)) {
            $it = implode(' ', array_filter(array_map(function ($x) { return is_scalar($x) ? (string) $x : ''; }, $it), 'strlen'));
        }
        $it = trim((string) $it);
        if ($it !== '') { $out[] = $it; }
    }
    return $out;
};
$has = function ($v) use ($flat) {
    if (is_array($v)) { return count($flat($v)) > 0; }
    return trim((string) $v) !== '';
};
$chips = function ($items, $bg = '#EEF2F7', $fg = '#3F4254') use ($flat) {
    $items = $flat($items);
    if (empty($items)) { echo '<span class="text-muted" style="font-size:13px;">-</span>'; return; }
    foreach ($items as $it) {
        echo '<span class="label label-inline font-weight-bold mr-2 mb-2" style="font-size:12px; padding:12px 12px; background:'
            . $bg . '; color:' . $fg . ';">' . htmlspecialchars($it) . '</span>';
    }
};
$bullets = function ($items) use ($flat) {
    $items = $flat($items);
    if (empty($items)) { echo '<p class="text-muted" style="font-size:13px;">-</p>'; return; }
    echo '<ul style="font-size:14px; padding-left:18px; margin-bottom:0;">';
    foreach ($items as $it) { echo '<li class="mb-1">' . htmlspecialchars($it) . '</li>'; }
    echo '</ul>';
};
// Price range: prefer explicit from/to, fall back to the headline price string.
$price_range = '';
if ($has($sv('price_from')) || $has($sv('price_to'))) {
    $from = $sv('price_from'); $to = $sv('price_to');
    if ($from !== '' && $to !== '' && $from !== $to) { $price_range = 'From ' . $from . ' to ' . $to; }
    else { $price_range = $from !== '' ? $from : $to; }
}
if ($price_range === '') { $price_range = $sv('price'); }
$meals = $av('meals');
?>

<?php if(!empty($show_product_source) && preg_match('#^https?://#i', $sv('url'))) { ?>
    <div class="mb-4">
        <span class="text-muted font-weight-bold mr-2" style="font-size:12px;">Source:</span>
        <a href="<?php echo htmlspecialchars($sv('url')); ?>" target="_blank" rel="noopener" style="font-size:12px;"><?php echo htmlspecialchars($sv('url')); ?></a>
    </div>
<?php } ?>

<!-- Headline facts -->
<div class="row mb-3">
    <?php
    $facts = array(
        'Tour Code'      => $sv('tour_code'),
        'Destination'    => $sv('destination'),
        'Duration'       => $sv('duration'),
        'Departure City' => $sv('departure_city'),
        'Price Range'    => $price_range,
        'Currency'       => $sv('currency'),
        'Difficulty'     => $sv('difficulty'),
        'Suitable Age'   => $sv('suitable_age'),
    );
    foreach($facts as $label => $val) { ?>
        <div class="col-md-3 col-sm-6 mb-4">
            <div class="p-4 rounded" style="background:#F3F6F9; height:100%;">
                <div class="text-muted font-weight-bold" style="font-size:12px;"><?php echo $label; ?></div>
                <div class="font-weight-bolder mt-1" style="font-size:15px;"><?php echo htmlspecialchars($val !== '' ? $val : '-'); ?></div>
            </div>
        </div>
    <?php } ?>
</div>

<!-- Traveller fit -->
<?php if($has($sv('target_traveller')) || $has($sv('child_friendly')) || $has($sv('senior_friendly')) || $has($av('usp'))) { ?>
    <h5 class="font-weight-bolder text-dark mt-3 mb-3"><i class="la la-users mr-1" style="color:#6082B6;"></i>Traveller Fit</h5>
    <div class="row mb-2">
        <?php
        $fit = array('Target Traveller' => $sv('target_traveller'), 'Child Friendly' => $sv('child_friendly'), 'Senior Friendly' => $sv('senior_friendly'));
        foreach($fit as $label => $val) { if(!$has($val)) { continue; } ?>
            <div class="col-md-4 mb-4">
                <div class="p-4 rounded" style="background:#F3F6F9; height:100%;">
                    <div class="text-muted font-weight-bold" style="font-size:12px;"><?php echo $label; ?></div>
                    <div class="font-weight-bold mt-1" style="font-size:14px;"><?php echo htmlspecialchars($val); ?></div>
                </div>
            </div>
        <?php } ?>
    </div>
    <?php if($has($av('usp'))) { ?>
        <div class="mb-5">
            <div class="text-muted font-weight-bold mb-2" style="font-size:13px;">Unique Selling Points</div>
            <?php $chips($av('usp'), '#E1F0FF', '#0073E9'); ?>
        </div>
    <?php } ?>
<?php } ?>

<!-- Coverage -->
<h5 class="font-weight-bolder text-dark mt-3 mb-3"><i class="la la-globe mr-1" style="color:#6082B6;"></i>Coverage</h5>
<div class="row mb-4">
    <div class="col-md-6 mb-4">
        <div class="text-muted font-weight-bold mb-2" style="font-size:13px;">Countries</div>
        <?php $chips($av('countries'), '#E1F0FF', '#0073E9'); ?>
    </div>
    <div class="col-md-6 mb-4">
        <div class="text-muted font-weight-bold mb-2" style="font-size:13px;">Cities</div>
        <?php $chips($av('cities')); ?>
    </div>
    <div class="col-md-6 mb-4">
        <div class="text-muted font-weight-bold mb-2" style="font-size:13px;">Travel Months</div>
        <?php $chips($av('travel_months'), '#FFF4DE', '#FFA800'); ?>
    </div>
    <div class="col-md-6 mb-4">
        <div class="text-muted font-weight-bold mb-2" style="font-size:13px;">Themes</div>
        <?php $chips($av('themes'), '#EEE5FF', '#8950FC'); ?>
    </div>
    <div class="col-md-6 mb-4">
        <div class="text-muted font-weight-bold mb-2" style="font-size:13px;">Tour Style</div>
        <?php $chips($av('tour_styles'), '#C9F7F5', '#1BC5BD'); ?>
    </div>
    <div class="col-md-6 mb-4">
        <div class="text-muted font-weight-bold mb-2" style="font-size:13px;">Local Transport</div>
        <?php $chips($av('local_transport')); ?>
    </div>
</div>

<!-- Flights & Meals -->
<div class="row mb-2">
    <?php if($has($sv('flight_departure')) || $has($sv('flight_return'))) { ?>
        <div class="col-md-6 mb-4">
            <h6 class="font-weight-bolder text-dark"><i class="la la-plane mr-1" style="color:#6082B6;"></i>Flight Details</h6>
            <div class="p-4 rounded" style="background:#F3F6F9;">
                <div class="mb-2"><span class="text-muted font-weight-bold" style="font-size:12px;">Departure:</span>
                    <div style="font-size:14px;"><?php echo htmlspecialchars($sv('flight_departure') !== '' ? $sv('flight_departure') : '-'); ?></div></div>
                <div><span class="text-muted font-weight-bold" style="font-size:12px;">Return:</span>
                    <div style="font-size:14px;"><?php echo htmlspecialchars($sv('flight_return') !== '' ? $sv('flight_return') : '-'); ?></div></div>
            </div>
        </div>
    <?php } ?>
    <div class="col-md-6 mb-4">
        <h6 class="font-weight-bolder text-dark"><i class="la la-utensils mr-1" style="color:#6082B6;"></i>Meals</h6>
        <div class="p-4 rounded d-flex" style="background:#F3F6F9;">
            <?php
            $meal_labels = array('breakfast' => 'Breakfast', 'lunch' => 'Lunch', 'dinner' => 'Dinner');
            foreach($meal_labels as $key => $label) { ?>
                <div class="text-center mr-5">
                    <div class="text-muted font-weight-bold" style="font-size:12px;"><?php echo $label; ?></div>
                    <div class="font-weight-bolder mt-1" style="font-size:18px; color:#6082B6;"><?php echo htmlspecialchars(trim((string) (isset($meals[$key]) ? $meals[$key] : '')) !== '' ? $meals[$key] : '-'); ?></div>
                </div>
            <?php } ?>
        </div>
        <?php if($has($av('signature_meals'))) { ?>
            <div class="mt-3">
                <div class="text-muted font-weight-bold mb-2" style="font-size:13px;">Signature Meals</div>
                <?php $chips($av('signature_meals'), '#FFE2E5', '#F64E60'); ?>
            </div>
        <?php } ?>
    </div>
</div>

<!-- Stay & Highlights -->
<div class="row mb-2">
    <div class="col-md-4 mb-4">
        <h6 class="font-weight-bolder text-dark"><i class="la la-hotel mr-1" style="color:#6082B6;"></i>Hotels</h6>
        <?php $bullets($av('hotels')); ?>
    </div>
    <div class="col-md-4 mb-4">
        <h6 class="font-weight-bolder text-dark"><i class="la la-mountain mr-1" style="color:#6082B6;"></i>Scenic Highlights</h6>
        <?php $bullets($av('scenic_highlights')); ?>
    </div>
    <div class="col-md-4 mb-4">
        <h6 class="font-weight-bolder text-dark"><i class="la la-shopping-bag mr-1" style="color:#6082B6;"></i>Shopping Stops</h6>
        <?php $bullets($av('shopping_stops')); ?>
    </div>
</div>

<!-- Inclusions / Exclusions / Optional -->
<div class="row mb-2">
    <div class="col-md-4 mb-4">
        <h6 class="font-weight-bolder" style="color:#1BC5BD;">Inclusions</h6>
        <?php $bullets($av('inclusions')); ?>
    </div>
    <div class="col-md-4 mb-4">
        <h6 class="font-weight-bolder" style="color:#F64E60;">Exclusions</h6>
        <?php $bullets($av('exclusions')); ?>
    </div>
    <div class="col-md-4 mb-4">
        <h6 class="font-weight-bolder" style="color:#8950FC;">Optional Tours</h6>
        <?php $bullets($av('optional_tours')); ?>
    </div>
</div>

<?php if($has($av('special_remarks'))) { ?>
    <div class="mb-5">
        <h6 class="font-weight-bolder text-dark"><i class="la la-info-circle mr-1" style="color:#FFA800;"></i>Special Remarks</h6>
        <?php $bullets($av('special_remarks')); ?>
    </div>
<?php } ?>

<!-- Daily itinerary -->
<?php if($has($av('itinerary'))) { ?>
    <div class="mb-5">
        <h5 class="font-weight-bolder text-dark mb-3"><i class="la la-route mr-1" style="color:#6082B6;"></i>Daily Itinerary</h5>
        <div style="border-left:2px solid #E4E6EF; padding-left:20px;">
            <?php foreach($av('itinerary') as $day) {
                $day = (array) $day;
                $d_label = trim((string) (isset($day['day']) ? $day['day'] : ''));
                $d_title = trim((string) (isset($day['title']) ? $day['title'] : ''));
                $d_desc  = trim((string) (isset($day['description']) ? $day['description'] : ''));
            ?>
                <div class="mb-4" style="position:relative;">
                    <span style="position:absolute; left:-27px; top:2px; width:12px; height:12px; border-radius:50%; background:#6082B6; border:2px solid #fff;"></span>
                    <div class="font-weight-bolder" style="font-size:14px; color:#6082B6;"><?php echo htmlspecialchars($d_label); ?><?php if($d_title !== '') { echo ' — <span style="color:#3F4254;">' . htmlspecialchars($d_title) . '</span>'; } ?></div>
                    <?php if($d_desc !== '') { ?><div class="text-dark-50 mt-1" style="font-size:13px; white-space:pre-line;"><?php echo htmlspecialchars($d_desc); ?></div><?php } ?>
                </div>
            <?php } ?>
        </div>
    </div>
<?php } ?>

<?php if($has($sv('summary'))) { ?>
    <div class="mb-5">
        <h5 class="font-weight-bolder text-dark">Summary</h5>
        <p style="font-size:14px; white-space:pre-line;"><?php echo htmlspecialchars($sv('summary')); ?></p>
    </div>
<?php } ?>

<!-- Pros / Cons -->
<div class="row">
    <div class="col-md-6 mb-5">
        <h6 class="font-weight-bolder" style="color:#1BC5BD;">Pros</h6>
        <?php $bullets($av('pros')); ?>
    </div>
    <div class="col-md-6 mb-5">
        <h6 class="font-weight-bolder" style="color:#F64E60;">Cons</h6>
        <?php $bullets($av('cons')); ?>
    </div>
</div>

<?php if($has($sv('comparison'))) { ?>
    <div class="mt-3">
        <h5 class="font-weight-bolder text-dark">Comparison vs Our Products</h5>
        <div class="p-4 rounded" style="background:#FFF4DE; font-size:14px; white-space:pre-line;">
            <?php echo htmlspecialchars($sv('comparison')); ?>
        </div>
    </div>
<?php } ?>
