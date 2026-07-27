<?php
/**
 * Run with: php tests/helpers/FaqLinkItemsTest.php
 *
 * Locks the contract for the per-sub-Q&A "reference links" feature (multiple
 * {label, url} pairs on each sub-item), implemented by the pure static helpers
 * on Faq_Model:
 *   - Normalize_Links() : clean + scheme-guard a stored {l,u} link list
 *   - Build_Links()     : zip posted sub_link_labels[i][]/sub_link_urls[i][]
 *   - Build_Items()     : attach cleaned links to the right rows
 *   - Decode_Items()    : round-trip links through the stored Description JSON
 *   - Search_Blob()     : link labels are searchable
 *
 * Runs without a DB. CI_Model is stubbed so the model file can be required.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}
if (!class_exists('CI_Model')) {
    class CI_Model {}
}
require_once __DIR__ . '/../../application/models/Faq_Model.php';

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

// ---- Normalize_Links ------------------------------------------------------
check('links: trims + keeps label/url',
    array(array('l'=>'Booking Form','u'=>'https://x.com/f')),
    Faq_Model::Normalize_Links(array(array('l'=>'  Booking Form  ','u'=>'  https://x.com/f  '))));

check('links: blank url dropped',
    array(),
    Faq_Model::Normalize_Links(array(array('l'=>'Label only','u'=>''))));

check('links: blank label kept (url is the fallback)',
    array(array('l'=>'','u'=>'https://x.com')),
    Faq_Model::Normalize_Links(array(array('l'=>'','u'=>'https://x.com'))));

check('links: bare domain upgraded to https',
    array(array('l'=>'Docs','u'=>'https://example.com/docs')),
    Faq_Model::Normalize_Links(array(array('l'=>'Docs','u'=>'example.com/docs'))));

check('links: javascript scheme dropped',
    array(),
    Faq_Model::Normalize_Links(array(array('l'=>'x','u'=>'javascript:alert(1)'))));

check('links: data scheme dropped',
    array(),
    Faq_Model::Normalize_Links(array(array('l'=>'x','u'=>'data:text/html,x'))));

check('links: mailto + site-relative kept',
    array(array('l'=>'Mail','u'=>'mailto:a@b.com'), array('l'=>'Home','u'=>'/dashboard')),
    Faq_Model::Normalize_Links(array(array('l'=>'Mail','u'=>'mailto:a@b.com'), array('l'=>'Home','u'=>'/dashboard'))));

check('links: non-array -> []', array(), Faq_Model::Normalize_Links('nope'));

// ---- Build_Links (zip posted parallel arrays) -----------------------------
check('build_links: zips aligned arrays',
    array(array('l'=>'A','u'=>'https://a'), array('l'=>'B','u'=>'https://b')),
    Faq_Model::Build_Links(array('A','B'), array('https://a','https://b')));

check('build_links: drops the empty alignment placeholder',
    array(array('l'=>'A','u'=>'https://a')),
    Faq_Model::Build_Links(array('A',''), array('https://a','')));

// ---- Build_Items with links ----------------------------------------------
$r = Faq_Model::Build_Items(
    array('Q1','Q2'), array('A1','A2'),
    null, '', '', null,
    array(0 => array('Site',''), 1 => array('')),          // labels per row
    array(0 => array('https://s',''), 1 => array(''))       // urls per row
);
check('build_items: link attached to row 0 only',
    array(
        array('q'=>'Q1','a'=>'A1','links'=>array(array('l'=>'Site','u'=>'https://s'))),
        array('q'=>'Q2','a'=>'A2'),
    ),
    $r['items']);
check('build_items: link rows no error', null, $r['error']);

// no link args at all -> bare {q,a} shape preserved
$r = Faq_Model::Build_Items(array('Q'), array('A'));
check('build_items: no link args -> bare shape', array(array('q'=>'Q','a'=>'A')), $r['items']);

// ---- Decode round-trip ----------------------------------------------------
$items   = array(array('q'=>'Q','a'=>'A','links'=>array(array('l'=>'Form','u'=>'https://x/y'))));
$encoded = Faq_Model::Encode_Items($items);
check('decode: links round trip', $items, Faq_Model::Decode_Items($encoded));

// a hand-edited stored javascript: href is scrubbed on decode
check('decode: unsafe stored href scrubbed',
    array(array('q'=>'Q','a'=>'A')),
    Faq_Model::Decode_Items('{"q":"Q","a":"A","links":[{"l":"x","u":"javascript:alert(1)"}]}'));

// ---- Search_Blob includes link labels ------------------------------------
check('search_blob: includes link label',
    'Q A Booking Form',
    Faq_Model::Search_Blob(array(array('q'=>'Q','a'=>'A','links'=>array(array('l'=>'Booking Form','u'=>'https://x'))))));

if ($failures === 0) {
    echo "\nAll FaqLinkItems assertions passed.\n";
    exit(0);
}
echo "\n{$failures} assertion(s) failed.\n";
exit(1);
