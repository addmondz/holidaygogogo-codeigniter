<?php
/**
 * Run with: php tests/helpers/FaqOwnerOnlyGuardTest.php
 *
 * Locks the access-control contract for the FAQ module:
 *   only OWNER (level 10) may create / edit / delete a FAQ; every other role
 *   has read-only access (listing + Internal page).
 *
 * This is a source-level guard so it runs without a DB/session. It asserts:
 *   - Is_Owner() compares the session level against 10
 *   - Create(), Update() and Delete() each call Is_Owner() before mutating
 * Bug shape it guards against: a future edit dropping the gate from one of the
 * three mutating actions (e.g. leaving Delete unguarded) and silently
 * re-opening write access to non-owners.
 */

$controller_path = __DIR__ . '/../../application/controllers/Faq.php';
if (!is_file($controller_path)) {
    echo "FAIL  cannot locate Faq.php at {$controller_path}\n";
    exit(1);
}
$source = file_get_contents($controller_path);

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

check('Is_Owner() checks session level === 10',
    (bool)preg_match('/Is_Owner\s*\(\s*\)\s*\{[^}]*level[^}]*===\s*10/s', $source));

foreach (array('Create', 'Update', 'Delete') as $method) {
    $body = body_of($source, $method);
    check("{$method}() is gated by Is_Owner()", $body !== null && strpos($body, 'Is_Owner()') !== false);
}

if ($failures === 0) {
    echo "\nAll FaqOwnerOnlyGuard assertions passed.\n";
    exit(0);
}
echo "\n{$failures} assertion(s) failed.\n";
exit(1);
