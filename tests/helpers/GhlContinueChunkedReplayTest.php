<?php
/**
 * Run with: php tests/helpers/GhlContinueChunkedReplayTest.php
 *
 * Volume / chunked-replay correctness test for the GHL continue pipeline.
 *
 * It builds a large message stream (~TARGET_MESSAGES, mirroring production
 * scale), sorts it into arrival order, and feeds it through the continue
 * pipeline CHUNK_SIZE messages at a time -- exactly like real cron runs picking
 * up new messages over time. After every chunk it runs leads + conversions, then
 * compares the final state against a single full rebuild of the same data.
 *
 * What it locks (the properties the duplicate-lead fix guarantees):
 *   A. NO OVER-SPLIT: for every conversation, continue never produces MORE leads
 *      than a rebuild. This is the "1 lead wrongly became 2" / duplicate-lead
 *      symptom -- it must never happen, at any chunk boundary.
 *   B. NO DUPLICATE LEADS: the natural key
 *      (conversation_id, lead_started_at, first_customer_message_id) is unique.
 *   C. CONVERGENCE: for ordinary conversations (single trip, convert-and-return,
 *      90-day gap, unconverted return) chunked continue == rebuild == expected.
 *
 * What it documents (a known, milder limitation -- reported, not a duplicate):
 *   D. UNDER-SPLIT GAP: a customer who converts and returns 3+ times can come out
 *      with fewer leads under continue than under rebuild, depending on where the
 *      chunk boundary lands, because each trip's split needs one
 *      leads->conversions cycle and an already-covered message is not
 *      re-processed. The test asserts any such conversation is ONLY of the
 *      chained multi-trip type and that continue is never GREATER than rebuild.
 */

date_default_timezone_set('UTC');
if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

const CHUNK_SIZE      = 5000;   // process this many messages per continue run
const TARGET_MESSAGES = 20000;  // approximate total volume to generate
const SPLIT_DAYS      = 90;     // inactivity split window
const DAY             = 86400;

// --- conversation templates: messages, booking times, expected final leads ----
// message token: "<i|o> <id> <YYYY-MM-DD HH:MM:SS>"
$TEMPLATES = array(
    'single_trip' => array(
        'msgs' => array('i M1 2026-03-01 09:00:00', 'o M2 2026-03-02 09:00:00'),
        'bookings' => array('2026-03-05 09:00:00'),
        'expected' => 1,
        'chained' => false,
    ),
    'convert_return' => array(
        'msgs' => array('i M1 2026-03-01 09:00:00', 'o M2 2026-03-02 09:00:00', 'i M3 2026-03-20 09:00:00'),
        'bookings' => array('2026-03-10 09:00:00'),
        'expected' => 2,
        'chained' => false,
    ),
    'gap_no_convert' => array(
        'msgs' => array('i M1 2026-01-01 09:00:00', 'o M2 2026-01-02 09:00:00', 'i M3 2026-06-01 09:00:00'),
        'bookings' => array(),
        'expected' => 2,
        'chained' => false,
    ),
    'return_no_convert' => array(
        'msgs' => array('i M1 2026-03-01 09:00:00', 'o M2 2026-03-02 09:00:00', 'i M3 2026-03-05 09:00:00'),
        'bookings' => array(),
        'expected' => 1,
        'chained' => false,
    ),
    'three_trips' => array(
        'msgs' => array('i M1 2026-03-01 09:00:00', 'i M3 2026-03-20 09:00:00', 'i M5 2026-04-15 09:00:00'),
        'bookings' => array('2026-03-10 09:00:00', '2026-03-28 09:00:00'),
        'expected' => 3,
        'chained' => true,
    ),
);

function ts($s) { return strtotime($s); }

function parse_template_messages($conv, $spec) {
    $out = array();
    foreach ($spec as $line) {
        list($dir, $id, $date, $time) = explode(' ', $line, 4);
        $out[] = array(
            'conv' => $conv,
            'id' => $id,
            'dir' => ($dir === 'i') ? 'inbound' : 'outbound',
            'at' => $date . ' ' . $time,
        );
    }
    return $out;
}

/** Split logic mirroring Cron::process_single_ghl_conversation. */
function build_leads($messages, $existing) {
    usort($messages, function($a, $b) {
        $ta = ts($a['at']); $tb = ts($b['at']);
        return $ta === $tb ? strcmp($a['id'], $b['id']) : ($ta < $tb ? -1 : 1);
    });

    $leads = array(); $cur = null; $last = null;
    foreach ($messages as $m) {
        $t = ts($m['at']);
        if ($m['dir'] === 'inbound') {
            $new = false;
            if ($cur === null) {
                $new = true;
            } else {
                $key = $cur['started'] . '|' . $cur['first'];
                if (!empty($existing[$key]['converted_at'])) {
                    $ca = ts($existing[$key]['converted_at']);
                    if ($ca !== false && $t > $ca) { $new = true; }
                }
                if (!$new && $last !== null && $t >= $last && ($t - $last) >= SPLIT_DAYS * DAY) {
                    $new = true;
                }
            }
            if ($new) {
                if ($cur !== null) { $leads[] = $cur; }
                $cur = array('started' => $m['at'], 'first' => $m['id']);
            }
        }
        $last = $t;
    }
    if ($cur !== null) { $leads[] = $cur; }
    return $leads;
}

/** In-memory pipeline: store keyed "conv\x00started|first" -> lead record. */
class Pipeline {
    public $store = array();
    public $bookings = array();
    public function __construct($bookings) { $this->bookings = $bookings; }

    private function conv_map($conv) {
        $map = array();
        foreach ($this->store as $k => $v) {
            if ($v['conv'] === $conv) {
                $map[$v['started'] . '|' . $v['first']] = array('converted_at' => $v['conv_at']);
            }
        }
        return $map;
    }
    public function process_leads($conv, $msgs) {
        $leads = build_leads($msgs, $this->conv_map($conv));
        $newkeys = array();
        foreach ($leads as $L) {
            $nk = $L['started'] . '|' . $L['first'];
            $newkeys[$nk] = true;
            $sk = $conv . "\x00" . $nk;
            if (!isset($this->store[$sk])) {
                $this->store[$sk] = array('conv' => $conv, 'started' => $L['started'],
                    'first' => $L['first'], 'is_conv' => 0, 'conv_at' => null);
            }
        }
        foreach (array_keys($this->store) as $sk) {
            if ($this->store[$sk]['conv'] === $conv) {
                $nk = $this->store[$sk]['started'] . '|' . $this->store[$sk]['first'];
                if (!isset($newkeys[$nk])) { unset($this->store[$sk]); }
            }
        }
    }
    public function run_conversions() {
        foreach ($this->store as $sk => $v) {
            if ($v['is_conv']) { continue; }
            $st = ts($v['started']);
            $best = null;
            foreach (isset($this->bookings[$v['conv']]) ? $this->bookings[$v['conv']] : array() as $b) {
                if ($b >= $st && ($best === null || $b < $best)) { $best = $b; }
            }
            if ($best !== null) {
                $this->store[$sk]['is_conv'] = 1;
                $this->store[$sk]['conv_at'] = date('Y-m-d H:i:s', $best);
            }
        }
    }
    public function rebuild($allByConv) {
        $snapshot = $this->store;            // pre-wipe snapshot (the parity fix)
        $this->store = array();
        foreach ($allByConv as $conv => $msgs) {
            $existing = array();
            foreach ($snapshot as $v) {
                if ($v['conv'] === $conv) {
                    $existing[$v['started'] . '|' . $v['first']] = array('converted_at' => $v['conv_at']);
                }
            }
            $leads = build_leads($msgs, $existing);
            $newkeys = array();
            foreach ($leads as $L) {
                $nk = $L['started'] . '|' . $L['first'];
                $newkeys[$nk] = true;
                $this->store[$conv . "\x00" . $nk] = array('conv' => $conv, 'started' => $L['started'],
                    'first' => $L['first'], 'is_conv' => 0, 'conv_at' => null);
            }
        }
        $this->run_conversions();
    }
    public function counts() {
        $c = array();
        foreach ($this->store as $v) { $c[$v['conv']] = (isset($c[$v['conv']]) ? $c[$v['conv']] : 0) + 1; }
        return $c;
    }
    public function natural_key_dupes() {
        $seen = array(); $dupes = 0;
        foreach ($this->store as $v) {
            $k = $v['conv'] . '|' . $v['started'] . '|' . $v['first'];
            if (isset($seen[$k])) { $dupes++; }
            $seen[$k] = true;
        }
        return $dupes;
    }
}

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . var_export($actual, true) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got " . var_export($actual, true) . "\n";
        exit(1);
    }
}

// --- generate the dataset ----------------------------------------------------
$templateNames = array_keys($TEMPLATES);
$all = array(); $bookings = array(); $expected = array(); $isChained = array();
$i = 0;
while (count($all) < TARGET_MESSAGES) {
    $name = $templateNames[$i % count($templateNames)];
    $tpl = $TEMPLATES[$name];
    $conv = sprintf('C%06d', $i);
    foreach (parse_template_messages($conv, $tpl['msgs']) as $m) { $all[] = $m; }
    $b = array();
    foreach ($tpl['bookings'] as $bk) { $b[] = ts($bk); }
    sort($b);
    $bookings[$conv] = $b;
    $expected[$conv] = $tpl['expected'];
    $isChained[$conv] = $tpl['chained'];
    $i++;
}
echo "Generated " . $i . " conversations / " . count($all) . " messages; chunk size " . CHUNK_SIZE . "\n";

// arrival order = global timestamp order (so chunks split conversations apart)
usort($all, function($a, $b) {
    $ta = ts($a['at']); $tb = ts($b['at']);
    if ($ta !== $tb) { return $ta < $tb ? -1 : 1; }
    $c = strcmp($a['conv'], $b['conv']);
    return $c !== 0 ? $c : strcmp($a['id'], $b['id']);
});

// --- CHUNKED CONTINUE --------------------------------------------------------
$pipe = new Pipeline($bookings);
$arrived = array();
$overSplitEvents = 0;
$total = count($all);
for ($start = 0; $start < $total; $start += CHUNK_SIZE) {
    $chunk = array_slice($all, $start, CHUNK_SIZE);
    $dirty = array();
    foreach ($chunk as $m) {
        if (!isset($arrived[$m['conv']])) { $arrived[$m['conv']] = array(); }
        $arrived[$m['conv']][] = $m;
        $dirty[$m['conv']] = true;
    }
    foreach (array_keys($dirty) as $conv) { $pipe->process_leads($conv, $arrived[$conv]); }
    $pipe->run_conversions();
    // invariant A checked continuously: never more leads than the template's final expected
    foreach ($pipe->counts() as $conv => $cnt) {
        if ($cnt > $expected[$conv]) { $overSplitEvents++; }
    }
}
$continue = $pipe->counts();

// --- FULL REBUILD on the same data (starts from continue state, like prod) ---
$allByConv = array();
foreach ($all as $m) { $allByConv[$m['conv']][] = $m; }
$reb = new Pipeline($bookings);
$reb->store = $pipe->store;
$reb->rebuild($allByConv);
$rebuild = $reb->counts();

// --- evaluate ----------------------------------------------------------------
$overVsRebuild = 0;      // continue produced MORE than rebuild (the forbidden case)
$underVsRebuild = 0;     // continue produced fewer (the known chained gap)
$underNotChained = 0;    // an under-split that is NOT the chained template (would be a real bug)
$simpleMismatch = 0;     // non-chained conversation disagreeing with expected
foreach ($expected as $conv => $exp) {
    $c = isset($continue[$conv]) ? $continue[$conv] : 0;
    $r = isset($rebuild[$conv]) ? $rebuild[$conv] : 0;
    if ($c > $r) { $overVsRebuild++; }
    if ($c < $r) {
        $underVsRebuild++;
        if (!$isChained[$conv]) { $underNotChained++; }
    }
    if (!$isChained[$conv] && $c !== $exp) { $simpleMismatch++; }
}

echo "\n-- results --\n";
echo "  over-split events during streaming: {$overSplitEvents}\n";
echo "  continue > rebuild (forbidden)    : {$overVsRebuild}\n";
echo "  continue < rebuild (chained gap)  : {$underVsRebuild}\n";
echo "  duplicate natural keys            : " . $pipe->natural_key_dupes() . "\n\n";

// A + B: the duplicate-lead guarantees -- must be perfectly clean.
assert_eq('A: no over-split during streaming', 0, $overSplitEvents);
assert_eq('A: continue never exceeds rebuild', 0, $overVsRebuild);
assert_eq('B: no duplicate natural keys', 0, $pipe->natural_key_dupes());

// C: ordinary conversations converge exactly.
assert_eq('C: every simple conversation matches expected & rebuild', 0, $simpleMismatch);

// D: any under-split is confined to the chained multi-trip type (documented gap).
assert_eq('D: under-splits are only chained 3+ trip convos', 0, $underNotChained);
echo "  NOTE: {$underVsRebuild} chained 3+ trip conversation(s) under-split in\n";
echo "        continue and would need a rebuild to reach full split count.\n";
echo "        This is the documented eventual-consistency gap, not a duplicate.\n";

echo "\nAll assertions passed.\n";
