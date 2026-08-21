<?php
/**
 * Competitor Analysis detail. Two shapes, both rendered with the shared
 * _product.php partial:
 *   - Site crawl  ($a->products non-empty): a combined report — header with the
 *     base URL + product count + total cost, then an accordion, one product per
 *     panel (first open).
 *   - Single      (upload / single URL): one product profile built from the row.
 */
$is_crawl = ! empty($a->products) && is_array($a->products);
$title = $a->product_name ?: ($a->page_title ?: 'Competitor Analysis');
?>
<div class="d-flex flex-column-fluid">
    <div class="container-fluid">
        <div class="card card-custom mb-5">
            <div class="card-header flex-wrap py-3" style="background-color:#D7E2F2;">
                <div class="card-title">
                    <h3 class="card-label" style="color:#6082B6;">
                        <strong><?php echo htmlspecialchars($title); ?></strong>
                        <?php if($is_crawl) { ?>
                            <span class="label label-light-primary label-inline font-weight-bold ml-2" style="font-size:12px;"><?php echo (int) $a->product_count; ?> products</span>
                        <?php } elseif(!empty($a->tour_code)) { ?>
                            <span class="label label-light-primary label-inline font-weight-bold ml-2" style="font-size:12px;"><?php echo htmlspecialchars($a->tour_code); ?></span>
                        <?php } ?>
                    </h3>
                </div>
                <div class="card-toolbar">
                    <a href="<?php echo base_url('Competitor_Analysis'); ?>" class="btn btn-light-primary font-weight-bold">
                        <i class="la la-arrow-left"></i> Back
                    </a>
                </div>
            </div>
            <div class="card-body">

                <?php if($a->status === 'error') { ?>
                    <div class="alert alert-custom alert-light-danger fade show mb-5" role="alert">
                        <div class="alert-icon"><i class="la la-warning"></i></div>
                        <div class="alert-text"><strong>Analysis failed:</strong> <?php echo htmlspecialchars($a->error_message ?: 'Unknown error'); ?></div>
                    </div>
                <?php } ?>

                <div class="mb-5">
                    <span class="text-muted font-weight-bold mr-2"><?php echo $is_crawl ? 'Site:' : 'Source:'; ?></span>
                    <?php if(preg_match('#^https?://#i', (string) $a->url)) { ?>
                        <a href="<?php echo htmlspecialchars($a->url); ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars($a->url); ?></a>
                    <?php } else { ?>
                        <span><i class="la la-file"></i> <?php echo htmlspecialchars($a->url); ?> <span class="text-muted">(uploaded file)</span></span>
                    <?php } ?>
                    <span class="text-muted ml-3" style="font-size:12px;">Analysed <?php echo date('d M Y H:i', strtotime($a->created_at)); ?><?php if($a->model) { echo ' · ' . htmlspecialchars($a->model); } ?><?php if((float) $a->cost_usd > 0) { echo ' · AI cost USD ' . number_format((float) $a->cost_usd, 4) . ' (' . number_format((int) $a->input_tokens) . ' in / ' . number_format((int) $a->output_tokens) . ' out tokens)'; } ?></span>
                </div>

                <?php if($is_crawl) { ?>
                    <div class="accordion accordion-toggle-arrow" id="ca_products">
                        <?php foreach($a->products as $i => $p) {
                            $p = (array) $p;
                            $pid   = 'ca_p_' . $i;
                            $pname = trim((string) (isset($p['product_name']) ? $p['product_name'] : '')) ?: ('Product ' . ($i + 1));
                            $pprice = trim((string) (isset($p['price']) ? $p['price'] : ''));
                            $pdest  = trim((string) (isset($p['destination']) ? $p['destination'] : ''));
                            $open   = $i === 0;
                        ?>
                            <div class="card">
                                <div class="card-header" id="head_<?php echo $pid; ?>">
                                    <div class="card-title <?php echo $open ? '' : 'collapsed'; ?>" data-toggle="collapse" data-target="#body_<?php echo $pid; ?>" aria-expanded="<?php echo $open ? 'true' : 'false'; ?>" style="cursor:pointer; font-size:15px;">
                                        <span class="font-weight-bolder text-dark"><?php echo ($i + 1) . '. ' . htmlspecialchars($pname); ?></span>
                                        <?php if($pdest !== '') { ?><span class="text-muted ml-2" style="font-size:13px;"><?php echo htmlspecialchars($pdest); ?></span><?php } ?>
                                        <?php if($pprice !== '') { ?><span class="label label-light-success label-inline font-weight-bold ml-2" style="font-size:12px;"><?php echo htmlspecialchars($pprice); ?></span><?php } ?>
                                    </div>
                                </div>
                                <div id="body_<?php echo $pid; ?>" class="collapse <?php echo $open ? 'show' : ''; ?>" data-parent="#ca_products">
                                    <div class="card-body">
                                        <?php $show_product_source = true; include __DIR__ . '/_product.php'; ?>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                <?php } else {
                    // Single analysis: build the canonical $p from the row (Read_One
                    // already merged details_json onto $a).
                    $p = array(
                        'url' => $a->url,
                        'product_name' => $a->product_name, 'tour_code' => $a->tour_code ?? '',
                        'price' => $a->price, 'price_from' => $a->price_from ?? '', 'price_to' => $a->price_to ?? '',
                        'currency' => $a->currency, 'destination' => $a->destination, 'duration' => $a->duration,
                        'departure_city' => $a->departure_city ?? '', 'flight_departure' => $a->flight_departure ?? '', 'flight_return' => $a->flight_return ?? '',
                        'difficulty' => $a->difficulty ?? '', 'target_traveller' => $a->target_traveller ?? '', 'suitable_age' => $a->suitable_age ?? '',
                        'child_friendly' => $a->child_friendly ?? '', 'senior_friendly' => $a->senior_friendly ?? '',
                        'summary' => $a->summary, 'comparison' => $a->comparison,
                        'countries' => $a->countries ?? array(), 'cities' => $a->cities ?? array(), 'travel_months' => $a->travel_months ?? array(),
                        'themes' => $a->themes ?? array(), 'tour_styles' => $a->tour_styles ?? array(), 'local_transport' => $a->local_transport ?? array(),
                        'inclusions' => $a->inclusions, 'exclusions' => $a->exclusions ?? array(), 'hotels' => $a->hotels ?? array(),
                        'shopping_stops' => $a->shopping_stops ?? array(), 'optional_tours' => $a->optional_tours ?? array(), 'special_remarks' => $a->special_remarks ?? array(),
                        'scenic_highlights' => $a->scenic_highlights ?? array(), 'signature_meals' => $a->signature_meals ?? array(), 'usp' => $a->usp ?? array(),
                        'pros' => $a->pros, 'cons' => $a->cons, 'meals' => $a->meals ?? array(), 'itinerary' => $a->itinerary ?? array(),
                    );
                    $show_product_source = false;
                    include __DIR__ . '/_product.php';
                } ?>

            </div>
        </div>
    </div>
</div>
