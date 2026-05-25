<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Allowlist of GHL tag strings that the "Active Leads by Tag" TC LEAD card
 * groups under each business dimension (destination / language / race).
 *
 * Match is case-insensitive but otherwise exact against the tag values stored
 * in ghl_conversations.tags_json. Each entry is rendered as one row in the
 * card's table, in the order it appears here. To add a new tag, append it to
 * the relevant list — no migration needed.
 *
 * Why an allowlist (and not "every distinct tag"): the live ghl_conversations
 * table also carries device-id tags, contact-list tags, status tags, internal
 * marketing batch tags, etc. Surfacing all of those would drown the actual
 * destination/language/race signal.
 *
 * Language is intentionally empty for now — no language tag has been seeded
 * into GHL yet. Once the team starts tagging leads with language codes
 * (e.g. "bm", "en", "cn"), add them here.
 */
function ghl_tag_categories()
{
    return array(
        'destination' => array(
            // Malaysia — islands & beaches
            'redang', 'tioman', 'perhentian', 'rawa', 'lang tengah',
            'pulau kapas', 'tenggol', 'aur', 'coral view', 'paya beach',
            'pangkor', 'kapalai', 'mabul', 'mataking', 'sabang', 'sipadan',
            // Malaysia — Sabah / Sarawak / East Malaysia
            'sabah', 'kk kundasang', 'kundasang', 'semporna',
            'sarawak', 'miri', 'sibu', 'labuan',
            // Malaysia — Peninsular
            'langkawi', 'penang', 'ipoh', 'cameron', 'melaka',
            'johor', 'desaru', 'kuantan', 'royal belum', 'belum houseboat',
            'kenyir houseboat', 'lake kenyir', 'club med cherating',
            'sky mirror', 'lost world', 'genting highlands', 'gentingcruise',
            'a famosa', 'port dickson',
            'malaysia', 'malaysia tour', 'msia multicity', 'local tour',
            // Thailand
            'thailand', 'bangkok', 'phuket', 'krabi', 'chiangmai',
            'koh samui', 'koh lipe', 'hatyai', 'betong',
            // China & HK / Macao / Taiwan
            'china', 'shanghai', 'beijing', 'chengdu', 'guangzhou',
            'chongqing', 'xi\'an', 'harbin', 'zhangjiajie', 'huangshan',
            'guilin', 'xiamen', 'qingdao', 'yunnan', 'guizhou', 'shandong',
            'jiangxi', 'jiangnan', 'gansu', 'tibet', 'xinjiang', 'silk road',
            'hong kong', 'macao', 'taiwan', 'taipei', 'hainan',
            // Vietnam
            'vietnam', 'danang', 'hanoi', 'ho chi minh', 'phu quoc',
            'nha trang', 'dalat', 'sapa',
            // Korea / Japan
            'korea', 'japan', 'hokkaido', 'osaka',
            // Indonesia
            'indonesia', 'bali', 'bintan', 'batam', 'bintan & batam',
            'labuan bajo', 'jakarta', 'surabaya', 'medan', 'yogyakarta',
            'lombok',
            // Rest of Asia / Oceania / Long-haul
            'maldives', 'sri lanka', 'philippines', 'cambodia', 'singapore',
            'australia', 'sydney',
            'europe', 'italy', 'france', 'switzerland', 'spain', 'egypt',
            // Product types that read as a "destination" on the booking sheet
            'cruise', 'flycruise',
        ),
        'language' => array(
            // Seed with the Malaysia trio once the team starts tagging:
            //   'bm', 'en', 'cn'
            // Left empty so the section renders "No data" until populated.
        ),
        'race' => array(
            'muslim', 'muslim china', 'muslim korea', 'muslim kunming',
        ),
    );
}

/**
 * Bucket a flat list of lead+tags rows into per-category, per-tag counts.
 *
 * Inputs:
 *   $category_tags: ['destination' => ['redang', ...], 'language' => [...], 'race' => [...]]
 *   $lead_tags_rows: list of ['id' => int|string, 'tags_json' => string]
 *     — each row is one unconverted lead with the raw JSON-array string from
 *       ghl_conversations.tags_json. NULL/blank tags_json rows must be filtered
 *       out by the caller's SQL so we don't pay the json_decode cost here.
 *
 * Output:
 *   ['destination' => [['tag' => 'redang', 'count' => 12], ...],
 *    'language'    => [...],
 *    'race'        => [...]]
 *
 *   - Only tags with count > 0 are included.
 *   - Rows are sorted by count DESC then tag ASC for stable display.
 *   - Each category is capped at GHL_TAG_TOP_N rows so the card stays scannable.
 *
 * Invariants enforced (mirrors ActiveLeadsByTagTest):
 *   - Match is case-insensitive against the trimmed tag string.
 *   - Exact-string match — "redang052026" does NOT count toward "redang".
 *   - One lead carrying the same tag twice still counts ONCE per tag.
 *   - A lead carrying tags in multiple categories (e.g. "tioman" + "muslim")
 *     counts independently in each category — the three tables are disjoint
 *     views over the same lead set.
 */
if (!defined('GHL_TAG_TOP_N')) {
    define('GHL_TAG_TOP_N', 10);
}

function ghl_aggregate_active_leads_by_tag(array $category_tags, array $lead_tags_rows)
{
    // Lowercase-keyed lookup: tag -> [category, canonical_label]
    $lookup = array();
    $counts = array();
    foreach ($category_tags as $category => $tags) {
        $counts[$category] = array();
        foreach ($tags as $tag) {
            $key = strtolower(trim($tag));
            if ($key === '') {
                continue;
            }
            $lookup[$key] = array('category' => $category, 'canonical' => $tag);
            $counts[$category][$tag] = 0;
        }
    }

    foreach ($lead_tags_rows as $row) {
        if (!isset($row['tags_json']) || $row['tags_json'] === null || $row['tags_json'] === '') {
            continue;
        }
        $tags = json_decode($row['tags_json'], true);
        if (!is_array($tags)) {
            continue;
        }
        // De-dup within this lead so a duplicate tag doesn't double-count.
        $seen_keys = array();
        foreach ($tags as $tag) {
            if (!is_string($tag)) {
                continue;
            }
            $key = strtolower(trim($tag));
            if ($key === '' || isset($seen_keys[$key]) || !isset($lookup[$key])) {
                continue;
            }
            $seen_keys[$key] = true;
            $hit = $lookup[$key];
            $counts[$hit['category']][$hit['canonical']]++;
        }
    }

    $output = array();
    foreach ($category_tags as $category => $_) {
        $rows_out = array();
        foreach ($counts[$category] as $tag => $count) {
            if ($count > 0) {
                $rows_out[] = array('tag' => $tag, 'count' => $count);
            }
        }
        usort($rows_out, function ($a, $b) {
            if ($a['count'] !== $b['count']) {
                return $b['count'] - $a['count'];
            }
            return strcmp($a['tag'], $b['tag']);
        });
        $output[$category] = array_slice($rows_out, 0, GHL_TAG_TOP_N);
    }
    return $output;
}
