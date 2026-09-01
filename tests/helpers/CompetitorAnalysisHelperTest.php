<?php
/**
 * Run with: php tests/helpers/CompetitorAnalysisHelperTest.php
 *
 * Locks the pure Competitor Analysis helpers (no DB, no network). The AI agent
 * scrapes the page itself via OpenAI's web_search tool, so these cover the
 * request shaping and reply normalisation only:
 *   - competitor_format_our_products()   flattens the costing dashboard rows
 *   - competitor_build_agent_input()     shapes the Responses API instructions+input
 *   - competitor_extract_responses_text() pulls assistant text from a Responses body
 *   - competitor_parse_ai_response()     normalises the model's JSON reply
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}
require_once __DIR__ . '/../../application/helpers/competitor_analysis_helper.php';

$failures = 0;
function check($label, $expected, $actual) {
    global $failures;
    if ($expected === $actual) {
        echo "PASS  {$label}\n";
    } else {
        $failures++;
        echo "FAIL  {$label}\n";
        echo "      expected: " . json_encode($expected) . "\n";
        echo "      actual:   " . json_encode($actual) . "\n";
    }
}
function check_true($label, $cond) { check($label, true, (bool) $cond); }

// ---- competitor_format_our_products -----------------------------------------
$dashboard = array('packages' => array(
    array(
        'name' => 'Langkawi Getaway', 'tour_code' => 'LGK-01',
        'bookings' => array(array('selling_price_per_pax' => '1200.50')),
    ),
    (object) array(
        'name' => 'Bali Escape', 'tour_code' => 'BAL-02',
        'bookings' => array(),
    ),
    array('name' => '', 'tour_code' => 'SKIP'),   // no name -> skipped
));
$products = competitor_format_our_products($dashboard);
check('format_our_products drops nameless rows', 2, count($products));
check('format_our_products name', 'Langkawi Getaway', $products[0]['name']);
check('format_our_products price from latest booking', 1200.5, $products[0]['price_myr']);
check('format_our_products null price when no booking', null, $products[1]['price_myr']);
check('format_our_products respects limit', 1, count(competitor_format_our_products($dashboard, 1)));

// ---- competitor_products_block (cost trim) ----------------------------------
// Null prices / empty codes carry no signal — they must NOT be sent (save tokens).
$block = competitor_products_block($products);
check_true('products_block keeps known price', strpos($block, '1200.5') !== false);
check_true('products_block keeps tour_code', strpos($block, 'LGK-01') !== false);
check_true('products_block drops null price key', strpos($block, 'price_myr":null') === false);
$decoded_block = json_decode($block, true);
check('products_block omits price_myr when unknown', false, array_key_exists('price_myr', $decoded_block[1]));
$empty_code = json_decode(competitor_products_block(array(array('name' => 'X', 'tour_code' => '', 'price_myr' => null))), true);
check('products_block drops empty tour_code key', false, array_key_exists('tour_code', $empty_code[0]));
check('products_block skips nameless rows', 0,
    count(json_decode(competitor_products_block(array(array('name' => '', 'price_myr' => 9))), true)));

// ---- competitor_build_agent_input -------------------------------------------
$spec = competitor_build_agent_input('https://x.com/tour', $products);
check('build_agent_input keys', array('instructions', 'input'), array_keys($spec));
check_true('build_agent_input tells agent to scrape via web_search',
    stripos($spec['instructions'], 'web_search') !== false);
check_true('build_agent_input demands JSON', stripos($spec['instructions'], 'JSON') !== false);
check_true('build_agent_input input carries url', strpos($spec['input'], 'https://x.com/tour') !== false);
check_true('build_agent_input input carries our products', strpos($spec['input'], 'Langkawi Getaway') !== false);
check_true('build_agent_input does NOT embed page html', stripos($spec['input'], '<html') === false);
// SPA hybrid: pass partial scraped text so the agent fills the gaps
$spec_p = competitor_build_agent_input('https://x.com/tour', $products, 'Bali 5D4N (only metadata scraped)');
check_true('build_agent_input includes partial scraped text',
    strpos($spec_p['input'], 'Bali 5D4N (only metadata scraped)') !== false);
check_true('build_agent_input instructs agent to fill gaps',
    stripos($spec_p['instructions'], 'fill in') !== false);
check_true('build_agent_input still web_search when partial given',
    stripos($spec_p['instructions'], 'web_search') !== false);

// ---- competitor_html_to_text (own scraper) ----------------------------------
$html = '<html><head><title>T</title><style>.x{color:red}</style><script>var a=1;</script></head>'
      . '<body><nav>Menu</nav><h1>Bali 5D4N</h1><p>Great tour &amp; value</p>'
      . '<ul><li>Hotel</li><li>Breakfast</li></ul><!-- note --></body></html>';
$text = competitor_html_to_text($html);
check_true('html_to_text drops nav chrome', strpos($text, 'Menu') === false);
check_true('html_to_text drops script', strpos($text, 'var a=1') === false);
check_true('html_to_text drops style', strpos($text, 'color:red') === false);
check_true('html_to_text drops comment', strpos($text, 'note') === false);
check_true('html_to_text keeps heading', strpos($text, 'Bali 5D4N') !== false);
check_true('html_to_text decodes entities', strpos($text, 'Great tour & value') !== false);
check_true('html_to_text keeps list items',
    strpos($text, 'Hotel') !== false && strpos($text, 'Breakfast') !== false);
check('html_to_text empty on non-string', '', competitor_html_to_text(null));
$long = '<p>' . str_repeat('word ', 20000) . '</p>';
check_true('html_to_text truncates to cap', strlen(competitor_html_to_text($long, 5000)) <= 5000);

// ---- html_to_text table cells -----------------------------------------------
check('html_to_text separates table cells with |', '3D2N Semporna | T/CODE: SEMP',
    competitor_html_to_text("<table><tr><td>3D2N Semporna</td><td>T/CODE: SEMP</td></tr></table>"));

// ---- html_to_text strips forms + dedupes repeated lines (cleaner AI input) ---
check_true('html_to_text drops form widgets',
    strpos(competitor_html_to_text('<form><input name="q"><button>Search</button></form><h1>Bali 5D4N</h1>'), 'Search') === false);
check('html_to_text collapses consecutive duplicate lines',
    "Book Now\nBali 5D4N",
    competitor_html_to_text('<p>Book Now</p><p>Book Now</p><p>Book Now</p><p>Bali 5D4N</p>'));

// ---- competitor_spa_api_url (JS-app adapter) --------------------------------
check('spa_api_url ICE post with category', 'https://www.gd.my/api/v1/posts/65',
    competitor_spa_api_url('https://www.gd.my/web/posts/domestic/65'));
check('spa_api_url ICE post no category', 'https://www.gd.my/api/v1/posts/65',
    competitor_spa_api_url('https://www.gd.my/web/posts/65'));
check('spa_api_url none for a normal page', '',
    competitor_spa_api_url('https://comp.com/tours/bali-5d4n'));
check('spa_api_url none for junk', '', competitor_spa_api_url('not a url'));

// ---- competitor_flatten_json_text -------------------------------------------
check_true('flatten_json keeps prose, skips noise keys',
    strpos(competitor_flatten_json_text(array('@type' => 'Product', 'url' => 'https://x', 'name' => 'Sabah Trip', 'nights' => 3)), 'Sabah Trip') !== false);
check('flatten_json drops url value', false,
    strpos(competitor_flatten_json_text(array('link' => 'https://x/y', 'name' => 'A trip here')), 'https://x/y') !== false);
check('flatten_json drops bare number under non-price key', false,
    strpos(competitor_flatten_json_text(array('nights' => 3, 'name' => 'Trip')), '3') !== false);
check_true('flatten_json keeps number under price key',
    strpos(competitor_flatten_json_text(array('price' => 1999)), '1999') !== false);
check('flatten_json empty on non-array', '', competitor_flatten_json_text('nope'));

// ---- competitor_extract_meta_text -------------------------------------------
$metaHtml = '<head><title>Korea Winter 7D</title>'
    . '<meta property="og:description" content="Ski &amp; snow tour in Korea">'
    . '<meta property="product:price:amount" content="3200">'
    . '<meta name="og:image" content="https://x/p.jpg"></head>';
$metaTxt = competitor_extract_meta_text($metaHtml);
check_true('meta_text reads title', strpos($metaTxt, 'Korea Winter 7D') !== false);
check_true('meta_text reads og:description decoded', strpos($metaTxt, 'Ski & snow tour in Korea') !== false);
check_true('meta_text reads product price', strpos($metaTxt, '3200') !== false);
check_true('meta_text ignores og:image', strpos($metaTxt, 'p.jpg') === false);
check('meta_text empty when none', '', competitor_extract_meta_text('<div>hi</div>'));

// ---- competitor_extract_embedded_json ---------------------------------------
// JSON-LD Product with a long description so it clears the thin gate.
$richLd = '<html><body><div id="app">Loading…</div><script type="application/ld+json">'
    . json_encode(array('@context' => 'https://schema.org', '@type' => 'Product',
        'name' => 'Bali 5D4N Adventure',
        'description' => str_repeat('Explore the beaches, temples and rice terraces of Bali. ', 12),
        'image' => 'https://cdn.co/x.jpg',
        'offers' => array('@type' => 'Offer', 'price' => '1999', 'priceCurrency' => 'MYR')))
    . '</script></body></html>';
$emb = competitor_extract_embedded_json($richLd);
check_true('embedded_json reads JSON-LD name', strpos($emb, 'Bali 5D4N Adventure') !== false);
check_true('embedded_json reads JSON-LD price', strpos($emb, '1999') !== false);
check_true('embedded_json reads JSON-LD currency', strpos($emb, 'MYR') !== false);
check_true('embedded_json drops JSON-LD image url', strpos($emb, 'cdn.co') === false);
check_true('embedded_json rescues a thin JS shell',
    competitor_scrape_is_thin(competitor_html_to_text($richLd)) && ! competitor_scrape_is_thin($emb));

// Next.js hydration blob.
$nextHtml = '<script id="__NEXT_DATA__" type="application/json">'
    . json_encode(array('props' => array('pageProps' => array('tour' => array(
        'title' => 'Japan 6D5N Sakura', 'price' => 4599,
        'cities' => array('Tokyo', 'Osaka'), 'image' => 'https://x/y.png')))))
    . '</script>';
$en = competitor_extract_embedded_json($nextHtml);
check_true('embedded_json reads __NEXT_DATA__ title', strpos($en, 'Japan 6D5N Sakura') !== false);
check_true('embedded_json reads __NEXT_DATA__ price', strpos($en, '4599') !== false);
check_true('embedded_json reads __NEXT_DATA__ nested list',
    strpos($en, 'Tokyo') !== false && strpos($en, 'Osaka') !== false);
check_true('embedded_json drops __NEXT_DATA__ image', strpos($en, 'y.png') === false);

// og/meta only (no JSON blobs).
check_true('embedded_json falls back to meta tags',
    strpos(competitor_extract_embedded_json($metaHtml), 'Korea Winter 7D') !== false);
check('embedded_json empty on non-string', '', competitor_extract_embedded_json(null));
check('embedded_json empty when nothing embedded', '',
    competitor_extract_embedded_json('<html><body>hi there</body></html>'));

// ---- competitor_websearch_cap -----------------------------------------------
check('websearch_cap default when unset', 15, competitor_websearch_cap(false));
check('websearch_cap default when empty', 15, competitor_websearch_cap(''));
check('websearch_cap honours value', 30, competitor_websearch_cap('30'));
check('websearch_cap explicit 0 = unlimited', 0, competitor_websearch_cap('0'));
check('websearch_cap negative -> default', 15, competitor_websearch_cap('-5'));
check('websearch_cap custom default', 5, competitor_websearch_cap(false, 5));

// ---- competitor_headless_cap ------------------------------------------------
check('headless_cap default when unset', 30, competitor_headless_cap(false));
check('headless_cap default when empty', 30, competitor_headless_cap(''));
check('headless_cap honours value', 8, competitor_headless_cap('8'));
check('headless_cap explicit 0 = unlimited', 0, competitor_headless_cap('0'));
check('headless_cap negative -> default', 30, competitor_headless_cap('-2'));
check('headless_cap custom default', 5, competitor_headless_cap(false, 5));

// ---- competitor_job_progress_message ----------------------------------------
check('job_progress queued', 'Queued…', competitor_job_progress_message(array('state' => 'queued')));
// running label reflects the phase: discovering (no total) vs reading/analysing (N/total)
check('job_progress discovering (no total)', 'Discovering…',
    competitor_job_progress_message(array('state' => 'running', 'phase' => 'discovering')));
check('job_progress discovering ignores stale total', 'Discovering…',
    competitor_job_progress_message(array('state' => 'running', 'phase' => 'discovering', 'done' => 0, 'total' => 0)));
check('job_progress reading shows N/total', 'Reading products… 2 / 5',
    competitor_job_progress_message(array('state' => 'running', 'phase' => 'reading', 'done' => 2, 'total' => 5)));
check('job_progress analysing shows N/total', 'Analysing… 4 / 5',
    competitor_job_progress_message(array('state' => 'running', 'phase' => 'analysing', 'done' => 4, 'total' => 5)));
check('job_progress done shows N', 'Done — 3', competitor_job_progress_message(array('state' => 'done', 'count' => 3)));
check('job_progress done no count', 'Done.', competitor_job_progress_message(array('state' => 'done')));
check('job_progress error', 'Error: boom',
    competitor_job_progress_message(array('state' => 'error', 'message' => 'boom')));
check('job_progress unknown', 'Job not found.', competitor_job_progress_message(array('state' => 'unknown')));

// ---- competitor_item_title --------------------------------------------------
check('item_title strips Product: label', '10D7N EAST COAST USA',
    competitor_item_title("Product: 10D7N EAST COAST USA\nCode: 10UYY\n...", 'https://x.com/a'));
check('item_title uses first meaningful line', 'LAPLAND NORTHERN LIGHTS',
    competitor_item_title("title: LAPLAND NORTHERN LIGHTS\nmore", 'https://x.com/a'));
check('item_title falls back to url label when no text', 'Bali 5D4N',
    competitor_item_title('', 'https://comp.com/tours/bali-5d4n'));
check_true('item_title truncates long titles',
    mb_strlen(competitor_item_title(str_repeat('A very long tour name ', 20), 'https://x.com/a'), 'UTF-8') <= 91);
check('item_title skips bare nav lines (MENUMENU/Home)', 'Bali 5D4N Getaway',
    competitor_item_title("MENUMENU\nHome\nBali 5D4N Getaway\nFrom RM 1899", 'https://x.com/a'));

// ---- competitor_page_title (real <h1>/og:title, not first body line) --------
check('page_title prefers <h1>', 'Pakej Kunming',
    competitor_page_title('<title>Pakej Kunming | Muslim | Raha Holidays</title><h1 class="t">Pakej Kunming</h1>'));
check('page_title uses og:title when no h1', '9D8N New Zealand',
    competitor_page_title('<meta property="og:title" content="9D8N New Zealand"><body>x</body>'));
check('page_title strips site suffix from <title>', 'Vacations in Tunisia',
    competitor_page_title('<title>Vacations in Tunisia - Raha Holidays</title>'));
check('page_title empty when none', '', competitor_page_title('<div>no title here</div>'));

// ---- competitor_strip_shared_chrome (mega-menu / footer removal) -------------
$menu = "MENUMENU\nHome\nInternational\nThailand\nVietnam";
$foot = "Contact Us\nYour trusted travel partner";
$chromeItems = array(
    array('url' => 'https://x.com/a', 'text' => $menu . "\nVacations in Tunisia\n8 days 7 nights\n" . $foot),
    array('url' => 'https://x.com/b', 'text' => $menu . "\nVacations in Greece\n6 days 5 nights\n" . $foot),
    array('url' => 'https://x.com/c', 'text' => $menu . "\nVacations in Spain\n5 days 4 nights\n" . $foot),
    array('url' => 'https://x.com/d', 'text' => $menu . "\nVacations in Italy\n7 days 6 nights\n" . $foot),
);
$stripped = competitor_strip_shared_chrome($chromeItems);
check('strip_chrome removes leading menu (title now real)', 'Vacations in Tunisia',
    competitor_item_title($stripped[0]['text'], 'https://x.com/a'));
check_true('strip_chrome removes trailing footer',
    strpos($stripped[1]['text'], 'Your trusted travel partner') === false);
check_true('strip_chrome keeps the unique product body',
    strpos($stripped[2]['text'], 'Vacations in Spain') !== false);
check('strip_chrome no-op under 3 items', 2,
    count(competitor_strip_shared_chrome(array($chromeItems[0], $chromeItems[1]))));

// ---- competitor_job_public_view ---------------------------------------------
$pv = competitor_job_public_view(array('job' => 'j1', 'url' => 'https://x.com', 'state' => 'done',
    'count' => 3, 'ts' => '2026-08-19 21:00:00'));
check('job_view id/url/state', array('j1', 'https://x.com', 'done'),
    array($pv['job'], $pv['url'], $pv['state']));
check('job_view message', 'Done — 3', $pv['message']);
check('job_view no analysis_id when unset', 0, $pv['analysis_id']);
check('job_view exposes analysis_id', 42,
    competitor_job_public_view(array('state' => 'done', 'analysis_id' => 42))['analysis_id']);

// ---- competitor_ice_listing_api_url -----------------------------------------
check('ice_listing_api maps /web/listing + query',
    'https://www.gd.my/api/v1/series?keyword=EAST%20COAST&location_id=85&brands[]=GD_Standard',
    competitor_ice_listing_api_url('https://www.gd.my/web/listing?keyword=EAST%20COAST&location_id=85&brands[]=GD_Standard'));
check('ice_listing_api none for a post page', '',
    competitor_ice_listing_api_url('https://www.gd.my/web/posts/domestic/65'));
check('ice_listing_api none for listing without query', '',
    competitor_ice_listing_api_url('https://www.gd.my/web/listing'));
check('ice_listing_api none for junk', '', competitor_ice_listing_api_url('not a url'));

// ---- competitor_json_api_to_text --------------------------------------------
$apiJson = json_encode(array('data' => array('attributes' => array(
    'title' => 'SABAH      ',
    'body'  => "<table><tr><td>3D2N Semporna</td><td>T/CODE: SEMP</td>"
             . "<td><a href='https://cdn.co/x.pdf'>View File</a></td></tr></table>",
))));
$apiText = competitor_json_api_to_text($apiJson);
check_true('json_api_to_text has trimmed title', strpos($apiText, 'SABAH') !== false && strpos($apiText, 'SABAH   ') === false);
check_true('json_api_to_text has package name', strpos($apiText, '3D2N Semporna') !== false);
check_true('json_api_to_text has tour code', strpos($apiText, 'SEMP') !== false);
check_true('json_api_to_text keeps file url', strpos($apiText, 'https://cdn.co/x.pdf') !== false);
check('json_api_to_text empty on junk', '', competitor_json_api_to_text('nope'));
check('json_api_to_text handles data list', true,
    strpos(competitor_json_api_to_text(json_encode(array('data' => array(
        array('attributes' => array('title' => 'Tour A')),
        array('attributes' => array('title' => 'Tour B')),
    )))), 'Tour B') !== false);

// ---- competitor_ice_post_packages -------------------------------------------
$postJson = json_encode(array('data' => array('attributes' => array('title' => 'SABAH   ', 'body' =>
    "<table class='editorjs_table_attach'><tr><td>3D2N / 4D3N Semporna</td><td>(T/CODE: SEMP - Updated on 29 JAN 2026)</td><td><a href='https://cdn.co/x1'>📎 View File</a></td></tr></table>"
    . "<table class='editorjs_table_attach'><tr><td>5D4N Semporna + Sipadan-Kapalai (Non-divers)</td><td>(T/CODE: SEKK)</td><td><a href='https://cdn.co/x2'>View</a></td></tr></table>"
    . "<p>Some other content</p>"))));
$pkgs = competitor_ice_post_packages($postJson);
check('post_packages splits into 2', 2, count($pkgs));
check('post_packages name', '3D2N / 4D3N Semporna', $pkgs[0]['name']);
check('post_packages tour code', 'SEMP', $pkgs[0]['code']);
check('post_packages pdf file', 'https://cdn.co/x1', $pkgs[0]['file']);
check('post_packages carries post title', 'SABAH', $pkgs[0]['title']);
check('post_packages second code', 'SEKK', $pkgs[1]['code']);
check('post_packages empty on junk', array(), competitor_ice_post_packages('nope'));
check('post_packages empty when no attach tables', array(),
    competitor_ice_post_packages(json_encode(array('data' => array('attributes' => array('body' => '<p>hi</p>'))))));

// ---- competitor_ice_api_kind ------------------------------------------------
check('ice_api_kind series', 'series', competitor_ice_api_kind('https://www.gd.my/api/v1/series/6618'));
check('ice_api_kind posts', 'posts', competitor_ice_api_kind('https://www.gd.my/api/v1/posts/65'));
check('ice_api_kind none', '', competitor_ice_api_kind('https://www.gd.my/web/posts/domestic/65'));

// ---- competitor_ice_series_items --------------------------------------------
$seriesList = json_encode(array('itineraries' => array(
    array('id' => 6618, 'code' => 'FS-P4HAK-1', 'caption' => '4D3N Hainan  Together', 'country' => 'CHINA', 'price' => '1388.0', 'price_currency' => 'MYR'),
    array('id' => 6619, 'code' => 'FS-XYZ', 'caption' => '', 'country' => 'CHINA', 'price' => '999.50', 'price_currency' => 'MYR'),
    array('id' => 6618, 'code' => 'DUP'),   // duplicate id -> deduped
    array('code' => 'NOID'),                // no id -> skipped
)));
$items = competitor_ice_series_items($seriesList, 'https://www.gd.my/');
check('ice_series_items count (dedup + skip no-id)', 2, count($items));
check('ice_series_items url is detail api', 'https://www.gd.my/api/v1/series/6618', $items[0]['url']);
check('ice_series_items label with price trimmed', '4D3N Hainan Together · CHINA · MYR 1388 · FS-P4HAK-1', $items[0]['label']);
check('ice_series_items label falls back to code', 'FS-XYZ · CHINA · MYR 999.5', $items[1]['label']);
check('ice_series_items respects limit', 1, count(competitor_ice_series_items($seriesList, 'https://www.gd.my', 1)));
check('ice_series_items empty on junk', array(), competitor_ice_series_items('nope', 'https://x.com'));

// ---- competitor_ice_series_web_url ------------------------------------------
$webSeries = json_encode(array('code' => 'FS-4VSIT', 'caption' => '4D3N NHA TRANG',
    'tours' => array(array('departure_date' => '26/09/2026'), array('departure_date' => '24/10/2026'))));
check('ice_series_web_url with depart_date', 'https://www.gd.my/web/itinerary/FS-4VSIT?type=series&depart_date=26%2F09%2F2026',
    competitor_ice_series_web_url($webSeries, 'https://www.gd.my/'));
check('ice_series_web_url no tours = no depart_date', 'https://www.gd.my/web/itinerary/FS-4VSIT?type=series',
    competitor_ice_series_web_url(json_encode(array('code' => 'FS-4VSIT')), 'https://www.gd.my'));
check('ice_series_web_url unwraps data', 'https://x.com/web/itinerary/AB-1?type=series',
    competitor_ice_series_web_url(json_encode(array('data' => array('code' => 'AB-1'))), 'https://x.com'));
check('ice_series_web_url empty when no code', '', competitor_ice_series_web_url(json_encode(array('caption' => 'x')), 'https://www.gd.my'));
check('ice_series_web_url empty on junk', '', competitor_ice_series_web_url('nope', 'https://www.gd.my'));

// ---- competitor_ice_series_to_text ------------------------------------------
$seriesDetail = json_encode(array(
    'id' => 6618, 'code' => 'FS-P4HAK-1', 'country' => 'CHINA', 'price_currency' => 'MYR',
    'caption' => "4D3N LET'S GO TO HAINAN", 'other_caption' => '海南',
    'description' => "<p>Direct flight to Hainan</p><p>Shopping stop: Health museum</p>",
    'includings' => array('hotel' => '4 star', 'wifi' => true, 'meal_onboard' => false,
        'accommodation' => array('nights' => '3', 'type' => 'hotel')),
    'tours' => array(
        array('departure_date' => '2026-06-01', 'departure_location' => 'Penang', 'price' => '1388', 'guide_languages' => array('EN', 'CN'), 'highlight' => '<p>Great Wall of Hainan</p>'),
        array('departure_date' => '2026-07-01', 'departure_location' => 'KL', 'price' => '1488'),
    ),
    'file_copy_url' => 'https://www.gd.my/i/FS-P4HAK-1',
));
$stext = competitor_ice_series_to_text($seriesDetail);
check_true('ice_series_to_text has product name', strpos($stext, "4D3N LET'S GO TO HAINAN") !== false);
check_true('ice_series_to_text has code+country', strpos($stext, 'Code: FS-P4HAK-1') !== false && strpos($stext, 'Country: CHINA') !== false);
check_true('ice_series_to_text renders description', strpos($stext, 'Direct flight to Hainan') !== false);
check_true('ice_series_to_text has inclusions', strpos($stext, 'hotel: 4 star') !== false && preg_match('/Inclusions:.*wifi/s', $stext) === 1);
check_true('ice_series_to_text lists not-included', strpos($stext, 'Not included:') !== false && strpos($stext, 'meal onboard') !== false);
check_true('ice_series_to_text nested including', strpos($stext, 'accommodation:') !== false);
check_true('ice_series_to_text has departures', strpos($stext, '2026-06-01 from Penang: 1388') !== false);
check_true('ice_series_to_text has guide langs', strpos($stext, 'guide: EN/CN') !== false);
check_true('ice_series_to_text has highlights', strpos($stext, 'Great Wall of Hainan') !== false);
check_true('ice_series_to_text has file url', strpos($stext, 'https://www.gd.my/i/FS-P4HAK-1') !== false);
check('ice_series_to_text empty on junk', '', competitor_ice_series_to_text('nope'));

// ---- competitor_ice_meals_text ----------------------------------------------
check('ice_meals_text string', 'Breakfast / Lunch', competitor_ice_meals_text('Breakfast / Lunch'));
check('ice_meals_text list', 'Breakfast, Dinner', competitor_ice_meals_text(array('Breakfast', 'Dinner')));
check('ice_meals_text bool map keeps true', 'Breakfast, Dinner', competitor_ice_meals_text(array('breakfast' => true, 'lunch' => false, 'dinner' => true)));
check('ice_meals_text empty', '', competitor_ice_meals_text(null));

// ---- competitor_ice_itinerary_text ------------------------------------------
$plans = array(
    array('day' => 1, 'title' => 'Arrive Nha Trang', 'title_two' => '抵达芽庄', 'display_meals' => array('dinner' => true),
        'activities' => array(
            array('title' => 'Hon Chong', 'title_two' => '钟屿石岬角', 'tagline' => 'Coastal rocks', 'category' => 'sightseeing'),
            array('title' => 'Night Market', 'subtitle' => 'Free time', 'category' => 'sightseeing'),
        )),
    array('day' => 3, 'title' => 'Free & Easy', 'display_meals' => 'Breakfast',
        'activities' => array(array('title' => 'Optional VinWonders', 'tagline' => 'USD 55', 'category' => 'recommended_optional'))),
);
$itin = competitor_ice_itinerary_text($plans, '<p>Overview: relaxed beach tour</p>');
check_true('itinerary_text has general_content', strpos($itin, 'Overview: relaxed beach tour') !== false);
check_true('itinerary_text day1 bilingual title', strpos($itin, 'Day 1: Arrive Nha Trang (抵达芽庄)') !== false);
check_true('itinerary_text day1 meals', strpos($itin, '[Meals: Dinner]') !== false);
check_true('itinerary_text activity bilingual + tagline', strpos($itin, 'Hon Chong (钟屿石岬角) — Coastal rocks') !== false);
check_true('itinerary_text activity subtitle', strpos($itin, 'Night Market: Free time') !== false);
check_true('itinerary_text keeps explicit day number', strpos($itin, 'Day 3: Free & Easy') !== false);
check('itinerary_text empty when no plans', '', competitor_ice_itinerary_text(array(), ''));

// integration: series_to_text folds in the structured itinerary
$seriesWithItin = json_encode(array('code' => 'AB-1', 'caption' => 'Beach Trip',
    'itinerary_plans' => $plans));
check_true('ice_series_to_text includes itinerary', strpos(competitor_ice_series_to_text($seriesWithItin), 'Day 1: Arrive Nha Trang') !== false);

// ---- competitor_ice_includings_split ----------------------------------------
$sp = competitor_ice_includings_split(array(
    'airport_taxes' => true, 'group_departure' => true, 'luggage' => true, 'wifi' => false,
    'meal_onboard' => true, 'hotel' => true, 'gratuities' => false, 'acf' => false, 'accommodation' => false));
check_true('includings_split keeps real inclusions',
    in_array('hotel', $sp['inclusions']) && in_array('airport taxes', $sp['inclusions']) && in_array('meal onboard', $sp['inclusions']));
check_true('includings_split real exclusions',
    in_array('wifi', $sp['exclusions']) && in_array('gratuities', $sp['exclusions']));
check_true('includings_split drops opaque acf',
    ! in_array('acf', $sp['exclusions']) && ! in_array('acf', $sp['inclusions']));
check_true('includings_split drops bare bool accommodation',
    ! in_array('accommodation', $sp['exclusions']) && ! in_array('accommodation', $sp['inclusions']));
check_true('includings_split keeps scalar value',
    in_array('hotel: 4 star', competitor_ice_includings_split(array('hotel' => '4 star'))['inclusions']));
check_true('includings_split keeps nested accommodation object',
    count(competitor_ice_includings_split(array('accommodation' => array('nights' => '3')))['inclusions']) === 1);
check('includings_split empty on junk', array('inclusions' => array(), 'exclusions' => array()),
    competitor_ice_includings_split('nope'));

// ---- competitor_ice_flights_text --------------------------------------------
$flightTours = array(
    array('departure_date' => '24/10/2026', 'flights' => array()),           // empty leg -> skipped
    array('departure_date' => '26/09/2026', 'flights' => array(
        array('airline' => 'AirAsia', 'flight_no' => 'AK 204', 'from_airport' => 'Kuala Lumpur (KUL)',
            'to_airport' => 'Nha Trang (CXR)', 'departure_date' => '26/09/2026', 'departure_time' => '10:10',
            'arrival_date' => '26/09/2026', 'arrival_time' => '11:30'),
        array('airline' => 'AirAsia', 'flight_no' => 'AK 205', 'from_airport' => 'Nha Trang (CXR)',
            'to_airport' => 'Kuala Lumpur (KUL)', 'departure_date' => '29/09/2026', 'departure_time' => '12:00',
            'arrival_date' => '29/09/2026', 'arrival_time' => '15:25'),
    )),
);
$ftext = competitor_ice_flights_text($flightTours);
check_true('ice_flights_text renders outbound leg', strpos($ftext, 'AirAsia AK 204: Kuala Lumpur (KUL) -> Nha Trang (CXR), depart 26/09/2026 10:10, arrive 26/09/2026 11:30') !== false);
check_true('ice_flights_text renders return leg', strpos($ftext, 'AirAsia AK 205: Nha Trang (CXR) -> Kuala Lumpur (KUL)') !== false);
check('ice_flights_text empty when no flights', '', competitor_ice_flights_text(array(array('flights' => array()))));
check('ice_flights_text empty on junk', '', competitor_ice_flights_text('nope'));

// integration: series_to_text surfaces the flight legs
$seriesWithFlights = json_encode(array('code' => 'FS-4VSIT', 'caption' => 'Nha Trang', 'tours' => $flightTours));
check_true('ice_series_to_text includes flights', strpos(competitor_ice_series_to_text($seriesWithFlights), 'Flights:') !== false
    && strpos(competitor_ice_series_to_text($seriesWithFlights), 'AK 204') !== false);

// ---- competitor_scrape_is_thin ----------------------------------------------
check_true('scrape_is_thin true for empty', competitor_scrape_is_thin(''));
check_true('scrape_is_thin true for JS skeleton', competitor_scrape_is_thin('Loading...'));
check('scrape_is_thin false for rich text', false,
    competitor_scrape_is_thin(str_repeat('Bali tour itinerary day. ', 60)));

// ---- competitor_resolve_url (crawler) ---------------------------------------
$B = 'https://comp.com/tours/asia';
check('resolve_url absolute kept', 'https://comp.com/x', competitor_resolve_url($B, 'https://comp.com/x'));
check('resolve_url root-relative', 'https://comp.com/tour/bali', competitor_resolve_url($B, '/tour/bali'));
check('resolve_url protocol-relative', 'https://cdn.com/a', competitor_resolve_url($B, '//cdn.com/a'));
check('resolve_url dir-relative', 'https://comp.com/tours/bali-5d', competitor_resolve_url($B, 'bali-5d'));
check('resolve_url strips fragment', 'https://comp.com/tours/x', competitor_resolve_url($B, 'x#top'));
check('resolve_url drops mailto', '', competitor_resolve_url($B, 'mailto:a@b.com'));
check('resolve_url drops javascript', '', competitor_resolve_url($B, 'javascript:void(0)'));
check('resolve_url empty on blank', '', competitor_resolve_url($B, '   '));

// ---- competitor_extract_links -----------------------------------------------
$page = '<a href="/tours/bali-5d4n">Bali</a> <a href="tours/japan-6d">JP</a>'
      . '<a href="https://other.com/x">off</a> <a href="/logo.png">img</a>'
      . '<a href="mailto:a@b.com">mail</a> <a href="/tours/bali-5d4n">dup</a>'
      . '<a href="/about">About</a>';
$links = competitor_extract_links($page, 'https://comp.com/');
check('extract_links same-host only + deduped + no assets', array(
    'https://comp.com/tours/bali-5d4n',
    'https://comp.com/tours/japan-6d',
    'https://comp.com/about',
), $links);
check('extract_links empty on junk', array(), competitor_extract_links('nope', 'https://comp.com/'));

// ---- competitor_is_product_url ----------------------------------------------
check_true('is_product_url tour slug', competitor_is_product_url('https://comp.com/tours/bali-5d4n'));
check_true('is_product_url package slug', competitor_is_product_url('https://comp.com/holiday-packages/japan-6d5n'));
check('is_product_url skips bare listing', false, competitor_is_product_url('https://comp.com/tours'));
check('is_product_url skips homepage', false, competitor_is_product_url('https://comp.com/'));
check('is_product_url skips about', false, competitor_is_product_url('https://comp.com/tours/about'));
check('is_product_url skips blog', false, competitor_is_product_url('https://comp.com/blog/best-tours'));
check('is_product_url needs a signal', false, competitor_is_product_url('https://comp.com/deals/bali-special-offer'));
// a duration code (5D4N) qualifies on its own, even without the word tour/package
check_true('is_product_url accepts duration-code slug', competitor_is_product_url('https://comp.com/promo/beijing-5d4n'));
// category/listing hubs are NOT products (chanbrothers nav links)
check('is_product_url skips travelstyles hub', false, competitor_is_product_url('https://www.chanbrothers.com/travelstyles/package-tours'));
check('is_product_url skips destinations hub', false, competitor_is_product_url('https://www.chanbrothers.com/destinations/europe/finland'));
check_true('is_product_url keeps real tour under package-tours', competitor_is_product_url('https://www.chanbrothers.com/package-tours/europe/finland/arctic-circle-adventure'));
// WordPress taxonomy/archive roots are listings — dropped even when slug has a keyword
// flat WordPress permalinks: a tour lives at root as a specific multi-word slug
check_true('is_product_url keeps flat root tour permalink',
    competitor_is_product_url('https://www.holidaygogogo.com/3d2n-kl-bukit-tinggi-genting-tour-suggested-itinerary/'));
check_true('is_product_url keeps flat root holiday permalink',
    competitor_is_product_url('https://www.holidaygogogo.com/3d2n-holiday-in-sun-beach-resort-pulau-tioman/'));
check('is_product_url skips bare single-word hub (no hyphen)', false,
    competitor_is_product_url('https://comp.com/tours'));
check('is_product_url skips flat slug without keyword', false,
    competitor_is_product_url('https://www.holidaygogogo.com/bukit-panchor-recreational-forest-penang/'));
check('is_product_url skips WP /theme/ taxonomy', false, competitor_is_product_url('https://www.holidaygogogo.com/theme/bagan-datuk-tour/'));
check('is_product_url skips WP /theme/ duration taxonomy', false, competitor_is_product_url('https://www.holidaygogogo.com/theme/3-7-days/'));

// ---- competitor_is_category_url (drill targets) -----------------------------
check_true('category_url plural -tours slug', competitor_is_category_url('https://www.wtstravel.com.sg/japan-tours/'));
check_true('category_url listing.php', competitor_is_category_url('https://x.com/en/listing.php?travelbadges=Cruise'));
check_true('category_url /category/ path', competitor_is_category_url('https://x.com/category/asia'));
check_true('category_url travelstyle hub', competitor_is_category_url('https://www.chanbrothers.com/travelstyles/package-tours'));
check('category_url false for singular tour product', false, competitor_is_category_url('https://x.com/tours/bali-5d4n-tour'));
check('category_url false for flat tour permalink', false, competitor_is_category_url('https://x.com/3d2n-genting-tour-itinerary'));
check('category_url false for raha tours product', false, competitor_is_category_url('https://rahaholidays.com/tours/yunnan-travel/'));

// ---- competitor_filter_urls_by_keyword (targeted crawl) ---------------------
$kwUrls = array(
    'https://rahaholidays.com/tours/yunnan-travel/',
    'https://rahaholidays.com/tours/trip-korea/',
    'https://rahaholidays.com/tour/pakej-kunming/',
    'https://rahaholidays.com/vacations/vacations-in-tunisia/',
);
check('keyword filter matches slug (yunnan)', array('https://rahaholidays.com/tours/yunnan-travel/'),
    competitor_filter_urls_by_keyword($kwUrls, 'yunnan'));
check('keyword filter empty keyword returns all', 4,
    count(competitor_filter_urls_by_keyword($kwUrls, '')));
check('keyword filter multi-word matches ANY', 2,
    count(competitor_filter_urls_by_keyword($kwUrls, 'korea tunisia')));
check('keyword filter is case-insensitive', 1,
    count(competitor_filter_urls_by_keyword($kwUrls, 'KUNMING')));
check('keyword filter ignores domain (raha matches nothing in path)', 0,
    count(competitor_filter_urls_by_keyword($kwUrls, 'raha')));
check('keyword filter no match returns empty', 0,
    count(competitor_filter_urls_by_keyword($kwUrls, 'antarctica')));

// ---- competitor_matches_keyword (URL slug OR content) -----------------------
$body = "Product: Trip Yunnan-9D7N\nDay 1 Kunming arrival\nDay 2 Dali old town";
check_true('matches_keyword by URL slug', competitor_matches_keyword('https://x.com/tours/yunnan-travel/', 'no body', 'yunnan'));
check_true('matches_keyword by content (kunming in body, not url)',
    competitor_matches_keyword('https://x.com/tours/yunnan-travel/', $body, 'kunming'));
check('matches_keyword false when neither', false,
    competitor_matches_keyword('https://x.com/tours/yunnan-travel/', $body, 'antarctica'));
check_true('matches_keyword empty keyword = always', competitor_matches_keyword('https://x.com/a', '', ''));
check_true('matches_keyword multi-word ANY', competitor_matches_keyword('https://x.com/tours/bali/', $body, 'japan dali'));

// ---- competitor_item_meta (duration + snippet, no price) --------------------
$m1 = competitor_item_meta("Product: Trip Yunnan-9D7N\nDay 1 Kunming\nDay 2 Dali", 'Trip Yunnan-9D7N');
check('item_meta duration from title (9D7N)', '9D7N', $m1['duration']);
check('item_meta snippet skips the title line', 'Day 1 Kunming', $m1['snippet']);
$m2 = competitor_item_meta("Amazing 5 Days 4 Nights Bali\nBeach and temples", 'Bali Escape');
check('item_meta duration "5 Days 4 Nights"', '5 Days 4 Nights', $m2['duration']);
$m3 = competitor_item_meta('', '');
check('item_meta empty when no text', '', $m3['duration'] . $m3['snippet']);
check('is_product_url skips WP /tag/ taxonomy', false, competitor_is_product_url('https://www.holidaygogogo.com/tag/china-tour-packages/'));
check('is_product_url skips WP /author/ archive', false, competitor_is_product_url('https://www.holidaygogogo.com/author/holiday-tour-admin/'));
check('is_product_url skips date archive', false, competitor_is_product_url('https://www.holidaygogogo.com/2024/05/best-tour-deals/'));
check_true('is_product_url keeps slug that merely contains "tag" (vintage)', competitor_is_product_url('https://comp.com/tours/vintage-heritage-tour'));

// ---- competitor_file_binary_kind --------------------------------------------
check('file_kind pdf', 'pdf', competitor_file_binary_kind("%PDF-1.4\nrest"));
check('file_kind jpeg', 'image', competitor_file_binary_kind("\xFF\xD8\xFF\xE0JFIF"));
check('file_kind png', 'image', competitor_file_binary_kind("\x89PNG\r\n\x1a\n...."));
check('file_kind gif', 'image', competitor_file_binary_kind("GIF89a...."));
check('file_kind webp', 'image', competitor_file_binary_kind("RIFF\x24\x00\x00\x00WEBP"));
check('file_kind none for html', '', competitor_file_binary_kind("<html><body>"));
check('file_kind none for tiny', '', competitor_file_binary_kind("ab"));

// ---- competitor_extract_file_links ------------------------------------------
$fileHtml = '<a href="/files/bali.pdf">Bali</a> <a href="brochure.JPG">img</a> '
    . '<a href="https://cdn.co/x.pdf">cdn</a> <a href="/about">About</a> '
    . '<a href="/files/bali.pdf">dup</a> <a href="deck.pptx">deck</a>';
// documents only — linked images (brochure.JPG) are excluded as likely photos.
check('file_links docs only, resolved, deduped', array(
    'https://comp.com/files/bali.pdf',
    'https://cdn.co/x.pdf',
    'https://comp.com/tours/deck.pptx',
), competitor_extract_file_links($fileHtml, 'https://comp.com/tours/'));
check('file_links empty on junk', array(), competitor_extract_file_links('no anchors', 'https://comp.com/'));
check('file_links empty on non-string', array(), competitor_extract_file_links(null, 'https://comp.com/'));
// junk legal/policy PDFs are filtered out
check('file_links drops legal/policy junk', array('https://cdn.co/bali-itinerary.pdf'),
    competitor_extract_file_links(
        '<a href="https://cdn.co/bali-itinerary.pdf">Bali</a>'
        . '<a href="/media/privacy-policy.pdf">privacy</a>'
        . '<a href="/docs/pdpa-notice.pdf">pdpa</a>'
        . '<a href="/x/code-of-conduct.pdf">conduct</a>', 'https://comp.com/'));

// ---- competitor_is_junk_file_url --------------------------------------------
check_true('junk_file privacy', competitor_is_junk_file_url('https://x.com/privacy-policy.pdf'));
check_true('junk_file pdpa', competitor_is_junk_file_url('https://x.com/media/pdpa-notice.pdf'));
check_true('junk_file code of conduct', competitor_is_junk_file_url('https://x.com/code-of-conduct.pdf'));
check('junk_file real brochure kept', false, competitor_is_junk_file_url('https://x.com/bali-5d4n-itinerary.pdf'));

// ---- competitor_jsonld_product_text -----------------------------------------
$ldTrip = '<script type="application/ld+json">' . json_encode(array(
    '@context' => 'https://schema.org', '@type' => 'Trip', 'name' => 'Best of Japan 10D',
    'description' => 'Tokyo Kyoto Osaka highlights tour.',
    'offers' => array('@type' => 'Offer', 'price' => '4999', 'priceCurrency' => 'USD'))) . '</script>';
check_true('jsonld_product reads Trip name', strpos(competitor_jsonld_product_text($ldTrip), 'Best of Japan 10D') !== false);
check_true('jsonld_product reads offer price', strpos(competitor_jsonld_product_text($ldTrip), '4999') !== false);
check('jsonld_product ignores non-product type', '',
    competitor_jsonld_product_text('<script type="application/ld+json">{"@type":"WebSite","name":"X"}</script>'));
$ldGraph = '<script type="application/ld+json">' . json_encode(array('@graph' => array(
    array('@type' => 'Organization', 'name' => 'Org Inc'),
    array('@type' => 'Product', 'name' => 'Bali 5D4N', 'description' => 'beach tour')))) . '</script>';
check_true('jsonld_product finds product inside @graph',
    strpos(competitor_jsonld_product_text($ldGraph), 'Bali 5D4N') !== false);
check_true('jsonld_product skips the Organization node in @graph',
    strpos(competitor_jsonld_product_text($ldGraph), 'Org Inc') === false);
check('jsonld_product empty when no ld+json', '', competitor_jsonld_product_text('<div>hi</div>'));

// ---- competitor_jsonld_types + looks_like_listing (drop catalogue pages) -----
$ldTypes = competitor_jsonld_types($ldTrip . '<script type="application/ld+json">{"@type":"BreadcrumbList"}</script>');
check_true('jsonld_types lists all @types (lowercased)',
    in_array('trip', $ldTypes, true) && in_array('breadcrumblist', $ldTypes, true));
$ldList = '<script type="application/ld+json">{"@type":"CollectionPage","name":"All Tours"}</script>';
check_true('jsonld_types reads CollectionPage', in_array('collectionpage', competitor_jsonld_types($ldList), true));
check('jsonld_types empty when none', array(), competitor_jsonld_types('<div>hi</div>'));
check_true('looks_like_listing true for CollectionPage-only page',
    competitor_looks_like_listing(competitor_jsonld_types($ldList)));
check_true('looks_like_listing true for ItemList search page',
    competitor_looks_like_listing(array('searchresultspage', 'itemlist')));
check('looks_like_listing false when a product type is also present', false,
    competitor_looks_like_listing(array('collectionpage', 'product')));
check('looks_like_listing false for a plain product page', false,
    competitor_looks_like_listing(competitor_jsonld_types($ldTrip)));
check('looks_like_listing false when no JSON-LD (no evidence)', false,
    competitor_looks_like_listing(array()));

// ---- competitor_text_looks_like_listing (content catch, no JSON-LD) ----------
$onePrice = "Bali 5D4N Getaway\nFrom RM 1899 per pax\nDay 1 Arrival\nDay 2 Ubud\nDay 3 Beach\nSingle supplement RM 500";
check('text_listing false for single product (one Day 1)', false,
    competitor_text_looks_like_listing($onePrice));
// A single tour with a departure-price table (many prices, ONE itinerary) must NOT
// be dropped — this was the Trip Yunnan-9D7N false positive (14 prices, 1 Day 1).
$manyPrices = "Trip Yunnan-9D7N\nDay 1 Kunming\nDay 2 Dali";
foreach (array(3299,3599,3899,4099,4399,4599,4899,5099,5399,5599,5899,6199,6599,6999) as $p) {
    $manyPrices .= "\nDeparture RM {$p}";
}
check('text_listing keeps single tour with many departure prices', false,
    competitor_text_looks_like_listing($manyPrices));
$manyItins = "Best of Asia\nDay 1 China\nDay 2 Beijing\nJapan Highlights\nDay 1 Tokyo\nDay 2 Kyoto\nKorea Escape\nDay 1 Seoul\nDay 2 Busan";
check_true('text_listing true for 3+ itineraries (multiple Day 1)',
    competitor_text_looks_like_listing($manyItins));
check('text_listing false on empty', false, competitor_text_looks_like_listing(''));

// ---- competitor_has_product_signal + looks_like_article ---------------------
check_true('product_signal on price', competitor_has_product_signal('Great trip, only RM 1899 per pax'));
check_true('product_signal on duration code', competitor_has_product_signal('Bali 5D4N package'));
check_true('product_signal on itinerary', competitor_has_product_signal('Day 1 arrival\nDay 2 city tour'));
check('product_signal false on pure prose', false,
    competitor_has_product_signal('We visited the spice garden and enjoyed the fragrant herbs and lovely weather.'));
$prose = str_repeat('The spice garden in Penang is a lovely place to spend an afternoon among fragrant herbs. ', 20);
check_true('looks_like_article true for long prose w/o signal', competitor_looks_like_article($prose));
check('looks_like_article false when it has a price', false,
    competitor_looks_like_article($prose . ' Book this 3D2N tour from RM 899.'));
check('looks_like_article false when thin (benefit of doubt)', false,
    competitor_looks_like_article('Short SPA product shell.'));

// ---- competitor_json_alternate_url ------------------------------------------
$altHtml = '<head><link rel="canonical" href="/x">'
    . '<link rel="alternate" type="application/json" href="/api/tour/42.json">'
    . '<link rel="alternate" type="application/rss+xml" href="/feed"></head>';
check('json_alternate resolves the JSON alternate', 'https://comp.com/api/tour/42.json',
    competitor_json_alternate_url($altHtml, 'https://comp.com/tours/'));
check('json_alternate ignores ld+json inline', '',
    competitor_json_alternate_url('<script type="application/ld+json">{}</script>', 'https://comp.com/'));
check('json_alternate none when absent', '',
    competitor_json_alternate_url('<link rel="alternate" type="text/html" href="/x">', 'https://comp.com/'));

// ---- competitor_extract_urls ------------------------------------------------
$filesLine = 'Files: https://cdn.co/a , https://cdn.co/b , see https://cdn.co/a again';
check('extract_urls dedupes + http only', array('https://cdn.co/a', 'https://cdn.co/b'),
    competitor_extract_urls($filesLine));
check('extract_urls strips trailing punctuation', array('https://cdn.co/x'),
    competitor_extract_urls('open https://cdn.co/x.'));
check('extract_urls respects limit', 1, count(competitor_extract_urls($filesLine, 1)));
check('extract_urls empty when none', array(), competitor_extract_urls('no links at all'));
check('extract_urls empty on non-string', array(), competitor_extract_urls(null));

// ---- competitor_is_site_root ------------------------------------------------
check_true('site_root bare host', competitor_is_site_root('https://comp.com'));
check_true('site_root trailing slash', competitor_is_site_root('https://comp.com/'));
check('site_root false for listing', false, competitor_is_site_root('https://comp.com/destinations/europe'));
check('site_root false for product', false, competitor_is_site_root('https://comp.com/tours/bali-5d4n'));

// ---- competitor_robots_sitemaps ---------------------------------------------
$robots = "User-agent: *\nDisallow: /admin\nSitemap: https://comp.com/sitemap.xml\nSitemap: https://comp.com/tours-sitemap.xml\n";
check('robots_sitemaps extracts both', array('https://comp.com/sitemap.xml', 'https://comp.com/tours-sitemap.xml'),
    competitor_robots_sitemaps($robots));
check('robots_sitemaps empty when none', array(), competitor_robots_sitemaps("User-agent: *\nDisallow: /"));

// ---- competitor_parse_sitemap -----------------------------------------------
$idx = '<?xml version="1.0"?><sitemapindex><sitemap><loc>https://comp.com/s1.xml</loc></sitemap><sitemap><loc>https://comp.com/s2.xml</loc></sitemap></sitemapindex>';
$pi = competitor_parse_sitemap($idx);
check('parse_sitemap detects index', true, $pi['is_index']);
check('parse_sitemap index children', array('https://comp.com/s1.xml', 'https://comp.com/s2.xml'), $pi['urls']);
$us = '<urlset><url><loc>https://comp.com/tours/bali-5d4n</loc></url><url><loc>https://comp.com/about</loc></url></urlset>';
$pu = competitor_parse_sitemap($us);
check('parse_sitemap urlset not index', false, $pu['is_index']);
check('parse_sitemap urlset locs', array('https://comp.com/tours/bali-5d4n', 'https://comp.com/about'), $pu['urls']);
check('parse_sitemap empty on junk', array('is_index' => false, 'urls' => array()), competitor_parse_sitemap('nope'));

// ---- competitor_filter_product_urls -----------------------------------------
$discovered = array(
    'https://comp.com/tours/bali-5d4n',
    'https://comp.com/about',
    'https://comp.com/tours/japan-6d5n',
    'https://comp.com/tours/bali-5d4n',   // dup
    'https://comp.com/tours',             // bare listing
    'https://comp.com/packages/korea-7d',
);
check('filter_product_urls keeps products, dedupes', array(
    'https://comp.com/tours/bali-5d4n',
    'https://comp.com/tours/japan-6d5n',
    'https://comp.com/packages/korea-7d',
), competitor_filter_product_urls($discovered));
check('filter_product_urls respects limit', 2, count(competitor_filter_product_urls($discovered, 2)));

// ---- competitor_url_label ---------------------------------------------------
check('url_label slug -> title case', 'Bali 5D4N', competitor_url_label('https://comp.com/tours/bali-5d4n'));
check('url_label strips .html', 'Japan 6D5N', competitor_url_label('https://comp.com/tour/japan-6d5n.html'));
check('url_label underscores', 'Korea Winter', competitor_url_label('https://comp.com/packages/korea_winter'));
check('url_label root -> host', 'comp.com', competitor_url_label('https://www.comp.com/'));
check('url_label decodes %20 + strips .pdf + title-case', 'Charms Of Hangzhou',
    competitor_url_label('https://cdn.com/files/CHARMS%20OF%20HANGZHOU.pdf'));
check('url_label decodes encoded slashes', 'Hangzhou Wuxi Oriental',
    competitor_url_label('https://c.com/f/HANGZHOU%2FWUXI%2FORIENTAL'));
check('url_label truncates very long labels', true,
    mb_strlen(competitor_url_label('https://c.com/x/' . str_repeat('a', 200)), 'UTF-8') <= 81);

// ---- competitor_crawl_row_summary -------------------------------------------
$crawl_products = array(
    array('destination' => 'Bali'), array('destination' => 'Japan'),
    array('destination' => 'Bali'), array('destination' => 'Korea'),
    array('destination' => 'Vietnam'),
);
$summary = competitor_crawl_row_summary('https://www.comp.com/tours', $crawl_products);
check('crawl_summary label strips www + counts', 'comp.com — 5 products', $summary['product_name']);
check('crawl_summary product_count', 5, $summary['product_count']);
check('crawl_summary distinct destinations + overflow', 'Bali, Japan, Korea, +1 more', $summary['destination']);
check('crawl_summary singular label', 'comp.com — 1 product',
    competitor_crawl_row_summary('https://comp.com', array(array('destination' => 'Bali')))['product_name']);

// ---- competitor_build_scraped_agent -----------------------------------------
$sspec = competitor_build_scraped_agent('https://x.com/tour', 'Bali 5D4N. Hotel. Breakfast.', $products);
check('build_scraped_agent keys', array('instructions', 'input'), array_keys($sspec));
check_true('build_scraped_agent does NOT use web_search',
    stripos($sspec['instructions'], 'web_search') === false);
check_true('build_scraped_agent forbids browsing',
    stripos($sspec['instructions'], 'do not browse') !== false);
check_true('build_scraped_agent demands JSON', stripos($sspec['instructions'], 'JSON') !== false);
check_true('build_scraped_agent carries scraped content',
    strpos($sspec['input'], 'Bali 5D4N') !== false);
check_true('build_scraped_agent carries url', strpos($sspec['input'], 'https://x.com/tour') !== false);
check_true('build_scraped_agent carries our products',
    strpos($sspec['input'], 'Langkawi Getaway') !== false);

// ---- competitor_build_discovery_agent (AI fallback for JS sites) ------------
$dspec = competitor_build_discovery_agent('https://www.gd.my/web', 10);
check('discovery_agent keys', array('instructions', 'input'), array_keys($dspec));
check_true('discovery_agent uses web_search', stripos($dspec['instructions'], 'web_search') !== false);
check_true('discovery_agent asks for JSON array', stripos($dspec['instructions'], 'JSON array') !== false);
check_true('discovery_agent carries url', strpos($dspec['input'], 'https://www.gd.my/web') !== false);

// ---- competitor_parse_url_list ----------------------------------------------
$arr = '["https://www.gd.my/web/tour/a","https://www.gd.my/web/tour/b","http://other.com/x","https://www.gd.my/web/tour/a"]';
check('parse_url_list json array, same-host, deduped', array(
    'https://www.gd.my/web/tour/a', 'https://www.gd.my/web/tour/b',
), competitor_parse_url_list($arr, 'www.gd.my'));
check('parse_url_list www-insensitive host', array('https://gd.my/tour/a'),
    competitor_parse_url_list('["https://gd.my/tour/a"]', 'www.gd.my'));
check('parse_url_list objects with url key', array('https://c.com/tour/x'),
    competitor_parse_url_list('[{"url":"https://c.com/tour/x"}]', 'c.com'));
check('parse_url_list code fence + prose fallback', array('https://c.com/tour/y'),
    competitor_parse_url_list("Here:\nhttps://c.com/tour/y.", 'c.com'));
check('parse_url_list respects limit', 1,
    count(competitor_parse_url_list($arr, 'www.gd.my', 1)));
check('parse_url_list empty -> []', array(), competitor_parse_url_list('', 'c.com'));

// ---- competitor_build_file_agent (PDF/image mode) ---------------------------
$fspec = competitor_build_file_agent($products);
check('build_file_agent keys', array('instructions', 'text'), array_keys($fspec));
check_true('build_file_agent tells agent to READ the attached file',
    stripos($fspec['instructions'], 'attached') !== false);
check_true('build_file_agent does NOT use web_search',
    stripos($fspec['instructions'], 'web_search') === false);
check_true('build_file_agent demands JSON', stripos($fspec['instructions'], 'JSON') !== false);
check_true('build_file_agent text carries our products',
    strpos($fspec['text'], 'Langkawi Getaway') !== false);

// ---- competitor_file_input_part ---------------------------------------------
$png = competitor_file_input_part('png', 'QUJD');
check('file_input_part image type', 'input_image', $png['type']);
check('file_input_part image data uri', 'data:image/png;base64,QUJD', $png['image_url']);
check('file_input_part jpg -> jpeg mime', 'data:image/jpeg;base64,QUJD',
    competitor_file_input_part('JPG', 'QUJD')['image_url']);   // case + jpg alias
check('file_input_part strips leading dot', 'input_image',
    competitor_file_input_part('.webp', 'QUJD')['type']);
$pdf = competitor_file_input_part('pdf', 'QUJD');
check('file_input_part pdf type', 'input_file', $pdf['type']);
check('file_input_part pdf data uri', 'data:application/pdf;base64,QUJD', $pdf['file_data']);
check('file_input_part pdf filename', 'competitor.pdf', $pdf['filename']);
check('file_input_part rejects unsupported', null, competitor_file_input_part('exe', 'QUJD'));
check('file_input_part rejects empty ext', null, competitor_file_input_part('', 'QUJD'));

// ---- competitor_extract_responses_text --------------------------------------
// Raw Responses API shape: web_search_call + message(output_text)
$body = array('output' => array(
    array('type' => 'web_search_call', 'id' => 'ws_1'),
    array('type' => 'message', 'role' => 'assistant', 'content' => array(
        array('type' => 'output_text', 'text' => '{"product_name":"Bali 5D4N"}'),
    )),
));
check('extract_responses_text from output array', '{"product_name":"Bali 5D4N"}',
    competitor_extract_responses_text($body));
// SDK convenience top-level string wins when present
check('extract_responses_text honours output_text string', 'HELLO',
    competitor_extract_responses_text(array('output_text' => 'HELLO')));
// output_text as array of strings
check('extract_responses_text joins output_text array', 'AB',
    competitor_extract_responses_text(array('output_text' => array('A', 'B'))));
// concatenates multiple output_text parts
$multi = array('output' => array(array('type' => 'message', 'content' => array(
    array('type' => 'output_text', 'text' => 'foo'),
    array('type' => 'output_text', 'text' => 'bar'),
))));
check('extract_responses_text concatenates parts', 'foobar', competitor_extract_responses_text($multi));
check('extract_responses_text empty on junk', '', competitor_extract_responses_text('nope'));
check('extract_responses_text empty when no message', '',
    competitor_extract_responses_text(array('output' => array(array('type' => 'web_search_call')))));

// ---- competitor_extract_usage -----------------------------------------------
$usage_body = array('usage' => array('input_tokens' => 1200, 'output_tokens' => 340, 'total_tokens' => 1540));
check('extract_usage input tokens', 1200, competitor_extract_usage($usage_body)['input_tokens']);
check('extract_usage output tokens', 340, competitor_extract_usage($usage_body)['output_tokens']);
// Chat Completions fallback names
$chat_usage = array('usage' => array('prompt_tokens' => 50, 'completion_tokens' => 10));
check('extract_usage prompt_tokens fallback', 50, competitor_extract_usage($chat_usage)['input_tokens']);
check('extract_usage completion_tokens fallback', 10, competitor_extract_usage($chat_usage)['output_tokens']);
check('extract_usage zero when absent', array('input_tokens' => 0, 'output_tokens' => 0),
    competitor_extract_usage(array('output' => array())));
check('extract_usage zero on junk', array('input_tokens' => 0, 'output_tokens' => 0),
    competitor_extract_usage('nope'));

// ---- competitor_estimate_cost -----------------------------------------------
// gpt-4o-mini = 0.15 / 0.60 per 1M. 1M in + 1M out = 0.15 + 0.60 = 0.75
check('estimate_cost gpt-4o-mini', 0.75, competitor_estimate_cost('gpt-4o-mini', 1000000, 1000000));
// unknown model falls back to gpt-4o-mini rates
check('estimate_cost unknown model falls back', 0.75, competitor_estimate_cost('mystery', 1000000, 1000000));
// gpt-4o = 2.50 / 10.00 per 1M
check('estimate_cost gpt-4o', 12.5, competitor_estimate_cost('gpt-4o', 1000000, 1000000));
// case-insensitive model key
check('estimate_cost is case-insensitive', 0.75, competitor_estimate_cost('GPT-4O-MINI', 1000000, 1000000));
// explicit .env-style rate override wins over the table
check('estimate_cost honours rate override', 3.0,
    competitor_estimate_cost('gpt-4o-mini', 1000000, 1000000, array('input' => 1.0, 'output' => 2.0)));
check('estimate_cost zero tokens -> 0', 0.0, competitor_estimate_cost('gpt-4o-mini', 0, 0));

// ---- competitor_parse_ai_response -------------------------------------------
$clean = json_encode(array(
    'product_name' => 'Bali 5D4N', 'price' => 'RM1,299', 'currency' => 'MYR',
    'destination' => 'Bali', 'duration' => '5D4N',
    'inclusions' => array('Hotel', 'Breakfast'),
    'pros' => array('Cheap'), 'cons' => array('No flight'),
    'summary' => 'Budget Bali tour.', 'comparison' => 'Cheaper than ours.',
    'matched_product' => 'Bali Deluxe 5D4N',
));
$rec = competitor_parse_ai_response($clean);
check('parse product_name', 'Bali 5D4N', $rec['product_name']);
check('parse inclusions as list', array('Hotel', 'Breakfast'), $rec['inclusions']);
check('parse comparison', 'Cheaper than ours.', $rec['comparison']);
check('parse matched_product', 'Bali Deluxe 5D4N', $rec['matched_product']);
check('parse matched_product default ""', '', competitor_parse_ai_response('{"foo":"bar"}')['matched_product']);

// code-fenced + surrounding prose
$fenced = "Here you go:\n```json\n" . $clean . "\n```\nthanks";
$rec2 = competitor_parse_ai_response($fenced);
check('parse tolerates code fence + prose', 'Bali 5D4N', $rec2['product_name']);

// list field arriving as a string is normalised
$str_list = json_encode(array('product_name' => 'X', 'inclusions' => "Hotel\nBreakfast\nTransfer"));
check('parse coerces string list', array('Hotel', 'Breakfast', 'Transfer'),
    competitor_parse_ai_response($str_list)['inclusions']);

// scalar arriving as array is joined
$arr_scalar = json_encode(array('product_name' => array('A', 'B')));
check('parse joins array scalar', 'A, B', competitor_parse_ai_response($arr_scalar)['product_name']);

check('parse garbage -> null', null, competitor_parse_ai_response('not json at all'));
check('parse empty -> null', null, competitor_parse_ai_response(''));
check('parse missing keys default', '', competitor_parse_ai_response('{"foo":"bar"}')['product_name']);

// end-to-end: Responses body -> text -> parsed record
$e2e = competitor_parse_ai_response(competitor_extract_responses_text($body));
check('e2e responses body -> parsed', 'Bali 5D4N', $e2e['product_name']);

// ---- rich competitor schema ------------------------------------------------
$rich = json_encode(array(
    'product_name' => 'China 8D7N', 'tour_code' => 'CN-8D7N-01',
    'price' => 'RM3999', 'price_from' => 'RM3999', 'price_to' => 'RM4599', 'currency' => 'MYR',
    'destination' => 'China', 'countries' => array('China'),
    'cities' => array('Beijing', 'Shanghai'), 'duration' => '8D7N',
    'travel_months' => array('June', 'July'), 'departure_city' => 'Kuala Lumpur',
    'flight_departure' => 'MH370 KUL-PEK 09:00', 'flight_return' => 'MH371 PVG-KUL 22:00',
    'themes' => array('Culture', 'History'), 'tour_styles' => array('Group tour'),
    'difficulty' => 'Moderate', 'local_transport' => array('Coach', 'Bullet train'),
    'inclusions' => array('Hotel', 'Breakfast'), 'exclusions' => array('Tips', 'Visa'),
    'hotels' => array('Hilton Beijing'),
    'meals' => array('breakfast' => '7', 'lunch' => '5', 'dinner' => '6'),
    'shopping_stops' => array('Silk factory'), 'optional_tours' => array('Kung fu show'),
    'special_remarks' => array('Min 20 pax'), 'scenic_highlights' => array('Great Wall'),
    'signature_meals' => array('Peking duck'), 'target_traveller' => 'Families',
    'suitable_age' => '6-70', 'child_friendly' => 'Yes — gentle pace', 'senior_friendly' => 'Yes',
    'usp' => array('Iconic landmarks'),
    'itinerary' => array(
        array('day' => 1, 'title' => 'Arrival', 'description' => 'Land in Beijing'),
        array('day' => 'Day 2', 'title' => 'Great Wall', 'description' => 'Visit the wall'),
    ),
    'pros' => array('Comprehensive'), 'cons' => array('Long flights'),
    'summary' => 'A cultural China tour.', 'comparison' => 'Pricier than ours.',
));
$r = competitor_parse_ai_response($rich);
check('parse tour_code', 'CN-8D7N-01', $r['tour_code']);
check('parse price range', array('RM3999', 'RM4599'), array($r['price_from'], $r['price_to']));
check('parse countries', array('China'), $r['countries']);
check('parse cities', array('Beijing', 'Shanghai'), $r['cities']);
check('parse travel_months', array('June', 'July'), $r['travel_months']);
check('parse departure_city', 'Kuala Lumpur', $r['departure_city']);
check('parse flights', 'MH371 PVG-KUL 22:00', $r['flight_return']);
check('parse themes', array('Culture', 'History'), $r['themes']);
check('parse difficulty', 'Moderate', $r['difficulty']);
check('parse local_transport', array('Coach', 'Bullet train'), $r['local_transport']);
check('parse exclusions', array('Tips', 'Visa'), $r['exclusions']);
check('parse hotels', array('Hilton Beijing'), $r['hotels']);
check('parse meals slots', array('7', '5', '6'),
    array($r['meals']['breakfast'], $r['meals']['lunch'], $r['meals']['dinner']));
check('parse shopping_stops', array('Silk factory'), $r['shopping_stops']);
check('parse optional_tours', array('Kung fu show'), $r['optional_tours']);
check('parse special_remarks', array('Min 20 pax'), $r['special_remarks']);
check('parse scenic_highlights', array('Great Wall'), $r['scenic_highlights']);
check('parse signature_meals', array('Peking duck'), $r['signature_meals']);
check('parse target_traveller', 'Families', $r['target_traveller']);
check('parse suitable_age', '6-70', $r['suitable_age']);
check('parse child_friendly', 'Yes — gentle pace', $r['child_friendly']);
check('parse usp', array('Iconic landmarks'), $r['usp']);
check('parse itinerary count', 2, count($r['itinerary']));
check('parse itinerary numeric day -> label', 'Day 1', $r['itinerary'][0]['day']);
check('parse itinerary keeps day label', 'Day 2', $r['itinerary'][1]['day']);
check('parse itinerary title', 'Great Wall', $r['itinerary'][1]['title']);

// missing rich fields default cleanly (old/sparse replies)
$min = competitor_parse_ai_response('{"product_name":"X"}');
check('parse missing list -> []', array(), $min['cities']);
check('parse missing meals -> blank slots',
    array('breakfast' => '', 'lunch' => '', 'dinner' => ''), $min['meals']);
check('parse missing itinerary -> []', array(), $min['itinerary']);
check('parse missing scalar -> ""', '', $min['tour_code']);

// itinerary tolerates a bare-string day + skips empty entries
$bare = json_encode(array('itinerary' => array('First day free', array('day' => 3), 'Last day shopping')));
$bi = competitor_parse_ai_response($bare)['itinerary'];
check('parse itinerary bare string count (empty skipped)', 2, count($bi));
check('parse itinerary bare string desc', 'First day free', $bi[0]['description']);
check('parse itinerary bare keeps day counter', 'Day 3', $bi[1]['day']);

// contract advertises the new fields + INFER instruction
$contract = competitor_output_contract();
check_true('contract lists itinerary', stripos($contract, 'itinerary') !== false);
check_true('contract lists exclusions', stripos($contract, 'exclusions') !== false);
check_true('contract lists meals', stripos($contract, 'meals') !== false);
check_true('contract marks INFER fields', strpos($contract, 'INFER') !== false);
check_true('contract asks for full flight detail', stripos($contract, 'FULL outbound flight detail') !== false);
check_true('contract asks optionals verbatim with price', stripos($contract, 'each one verbatim WITH its price') !== false);

echo "\n" . ($failures === 0 ? "ALL PASS\n" : "{$failures} FAILURE(S)\n");
exit($failures === 0 ? 0 : 1);
