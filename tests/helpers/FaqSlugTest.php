<?php
/**
 * Run with: php tests/helpers/FaqSlugTest.php
 *
 * Locks the two pure helpers behind the per-FAQ unique page URL (/faq/<slug>):
 *
 *   - Slugify($title): turn a free-text Title into a URL-safe slug. Lowercase,
 *     every run of non-[a-z0-9] becomes a single hyphen, leading/trailing
 *     hyphens trimmed, capped at 80 chars (trimmed back to a hyphen boundary).
 *     A title that slugifies to nothing falls back to 'faq' so the column is
 *     never blank.
 *   - Unique_Slug($base, $taken): given the base slug and the slugs already in
 *     use, return $base if free, else the lowest 'base-N' (N>=2) not taken.
 *     Fills gaps (base, base-3 taken -> base-2). The DB query that gathers
 *     $taken lives in the model; this disambiguation stays pure + testable.
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

// ---- Slugify --------------------------------------------------------------
check('slug: basic words',        'how-to-book-a-trip', Faq_Model::Slugify('How to Book a Trip'));
check('slug: trims + punctuation','trim-me',            Faq_Model::Slugify('  Trim  Me!!  '));
check('slug: symbols collapse',   'a-b-c',              Faq_Model::Slugify('A & B / C'));
check('slug: multiple spaces',    'multiple-spaces',    Faq_Model::Slugify("Multiple   \t Spaces"));
check('slug: already slugged',    'already-slugged',    Faq_Model::Slugify('Already-Slugged'));
check('slug: keeps digits',       'top-10-tips',        Faq_Model::Slugify('Top 10 Tips'));
check('slug: empty -> faq',       'faq',                Faq_Model::Slugify(''));
check('slug: symbols only -> faq','faq',                Faq_Model::Slugify('!!! @@@ ###'));
check('slug: non-string -> faq',  'faq',                Faq_Model::Slugify(null));

// Length cap: 80 chars, trimmed back to a hyphen boundary (no trailing hyphen).
$long = str_repeat('word ', 30); // 'word word ...' -> 'word-word-...'
$slug = Faq_Model::Slugify($long);
check('slug: capped <= 80',       true,                 strlen($slug) <= 80);
check('slug: no trailing hyphen', true,                 substr($slug, -1) !== '-');

// ---- Unique_Slug ----------------------------------------------------------
check('unique: free base',        'guide',              Faq_Model::Unique_Slug('guide', array()));
check('unique: not in taken',     'guide',              Faq_Model::Unique_Slug('guide', array('other')));
check('unique: first collision',  'guide-2',            Faq_Model::Unique_Slug('guide', array('guide')));
check('unique: chained collision','guide-3',            Faq_Model::Unique_Slug('guide', array('guide', 'guide-2')));
check('unique: fills the gap',    'guide-2',            Faq_Model::Unique_Slug('guide', array('guide', 'guide-3')));

if ($failures === 0) {
    echo "\nAll FaqSlug assertions passed.\n";
    exit(0);
}
echo "\n{$failures} assertion(s) failed.\n";
exit(1);
