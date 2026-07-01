<?php
/**
 * Run with: php tests/helpers/FaqAccessGuardTest.php
 *
 * Locks the access-control contract for the FAQ module after it moved off the
 * old "OWNER only" gate onto assignable permission codes:
 *
 *   - VIEW  (listing + Internal page): OWNER (level 10) OR the 'FV' code
 *     ("FAQ VIEW ACCESS").
 *   - EDIT  (create / update / delete + bulk import/export): OWNER (level 10)
 *     OR the 'FE' code ("FAQ EDIT ACCESS"). The Excel/PDF/Export/Import bulk
 *     tools moved off the old OWNER-only gate onto FE so an assigned editor can
 *     run them.
 *
 * OWNER always passes both (bypass) so an owner can never lock themselves out.
 *
 * These are source-level guards so they run without a DB/session. They assert:
 *   - Can_View()  checks level === 10 and the 'FV' access_control code
 *   - Can_Edit()  checks level === 10 and the 'FE' access_control code
 *   - index() and Page() are gated by Can_View()
 *   - Create(), Update(), Delete(), Download(), Download_Pdf(),
 *     Export_Template() and Import() are each gated by Can_Edit()
 *   - the ACCESS_CONTROL constant declares the FV and FE codes so the Admin
 *     multi-select can assign them
 * Bug shape it guards against: a future edit dropping the gate from one of the
 * mutating actions (e.g. leaving Delete unguarded) and silently re-opening
 * write access, or removing a permission code so it can no longer be assigned.
 */

$controller_path = __DIR__ . '/../../application/controllers/Faq.php';
$constants_path  = __DIR__ . '/../../application/config/constants.php';
foreach (array($controller_path, $constants_path) as $p) {
    if (!is_file($p)) {
        echo "FAIL  cannot locate {$p}\n";
        exit(1);
    }
}
$source    = file_get_contents($controller_path);
$constants = file_get_contents($constants_path);

$failures = 0;
function check($label, $cond) {
    global $failures;
    if ($cond) {
        echo "PASS  {$label}\n";
    } else {
        $failures++;
        echo "FAIL  {$label}\n";
    }
}

// Extract a function body by name (balanced-brace scan from its opening {).
function body_of($source, $name) {
    if (!preg_match('/function\s+' . preg_quote($name, '/') . '\s*\([^)]*\)\s*\{/', $source, $m, PREG_OFFSET_CAPTURE)) {
        return null;
    }
    $start = $m[0][1] + strlen($m[0][0]) - 1; // index of the opening brace
    $depth = 0;
    for ($i = $start, $n = strlen($source); $i < $n; $i++) {
        if ($source[$i] === '{') { $depth++; }
        elseif ($source[$i] === '}') { $depth--; if ($depth === 0) { return substr($source, $start, $i - $start + 1); } }
    }
    return null;
}

// Can_View(): OWNER bypass + 'FV' code.
$view_body = body_of($source, 'Can_View');
check('Can_View() exists', $view_body !== null);
check('Can_View() checks session level === 10 (owner bypass)',
    $view_body !== null && (bool)preg_match('/level[^;]*===\s*10/s', $view_body));
check("Can_View() checks the 'FV' access_control code",
    $view_body !== null && strpos($view_body, "'FV'") !== false && strpos($view_body, 'access_control') !== false);

// Can_Edit(): OWNER bypass + 'FE' code.
$edit_body = body_of($source, 'Can_Edit');
check('Can_Edit() exists', $edit_body !== null);
check('Can_Edit() checks session level === 10 (owner bypass)',
    $edit_body !== null && (bool)preg_match('/level[^;]*===\s*10/s', $edit_body));
check("Can_Edit() checks the 'FE' access_control code",
    $edit_body !== null && strpos($edit_body, "'FE'") !== false && strpos($edit_body, 'access_control') !== false);

// View actions gated by Can_View(). Internal() is the grouped "all FAQs on one
// page" view; it exposes the same internal content as Page(), so it must sit
// behind the same FV/owner gate.
foreach (array('index', 'Page', 'Internal') as $method) {
    $body = body_of($source, $method);
    check("{$method}() is gated by Can_View()", $body !== null && strpos($body, 'Can_View()') !== false);
}

// Mutating actions + bulk import/export gated by Can_Edit().
foreach (array('Create', 'Update', 'Delete', 'Download', 'Download_Pdf', 'Export_Template', 'Import') as $method) {
    $body = body_of($source, $method);
    check("{$method}() is gated by Can_Edit()", $body !== null && strpos($body, 'Can_Edit()') !== false);
}

// Permission codes must be declarable in the Admin multi-select.
check("ACCESS_CONTROL declares 'FV' => 'FAQ VIEW ACCESS'",
    (bool)preg_match("/'FV'\s*=>\s*'FAQ VIEW ACCESS'/", $constants));
check("ACCESS_CONTROL declares 'FE' => 'FAQ EDIT ACCESS'",
    (bool)preg_match("/'FE'\s*=>\s*'FAQ EDIT ACCESS'/", $constants));

if ($failures === 0) {
    echo "\nAll FaqAccessGuard assertions passed.\n";
    exit(0);
}
echo "\n{$failures} assertion(s) failed.\n";
exit(1);
