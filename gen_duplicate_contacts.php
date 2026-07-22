<?php
/**
 * One-off: export contact numbers shared by more than one active booking to
 * an .xlsx on disk. Run once with `php gen_duplicate_contacts.php`, then this
 * file can be deleted — it is not wired into the app.
 */

require __DIR__ . '/vendor/autoload.php';

// --- read DB creds from .env ---------------------------------------------
$env = array();
foreach (file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) { continue; }
    list($k, $v) = explode('=', $line, 2);
    $env[trim($k)] = trim($v);
}

$pdo = new PDO(
    'mysql:host=' . $env['DB_HOST'] . ';dbname=' . $env['DB_DATABASE'] . ';charset=utf8mb4',
    $env['DB_USERNAME'],
    $env['DB_PASSWORD'],
    array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
);

// --- duplicated numbers (shared by >1 active booking) + display columns ---
$sql = "
    SELECT
        dup.dedup_key                              AS dedup_key,
        dup.booking_count                          AS DupBookingCount,
        ccp.CountryCode                            AS CallingCode,
        gl.Mobile                                  AS ContactNum,
        TRIM(CONCAT_WS(' ', gl.Name, gl.LastName)) AS GuestName,
        gl.Email                                   AS Email,
        b.BookingNumber                            AS BookingNumber,
        b.InsertDate                               AS BookingDate,
        a.Name                                     AS AgentName,
        cat.Name                                   AS Destination
    FROM (
        SELECT gl.dedup_key AS dedup_key, COUNT(DISTINCT gl.BookingID) AS booking_count
        FROM guest_list gl
        JOIN booking b ON b.BookingID = gl.BookingID
        WHERE gl.Status = 'Y' AND b.Status != 'N' AND b.CancelStatus = 'N'
            AND gl.dedup_key IS NOT NULL AND gl.dedup_key <> ''
            AND gl.dedup_key REGEXP '^[0-9]{7,}$'   -- real phone keys only; drop -, na, tba, xxx
        GROUP BY gl.dedup_key
        HAVING COUNT(DISTINCT gl.BookingID) > 1
    ) dup
    JOIN guest_list gl  ON gl.dedup_key    = dup.dedup_key AND gl.Status = 'Y'
    JOIN booking     b  ON b.BookingID      = gl.BookingID AND b.Status != 'N' AND b.CancelStatus = 'N'
    LEFT JOIN admin        a   ON a.AdminID        = b.SalesAgent
    LEFT JOIN category     cat ON cat.CategoryID   = b.Destination
    LEFT JOIN country_code ccp ON ccp.CountryCodeID = gl.CountryCodeID
    ORDER BY dup.booking_count DESC, dup.dedup_key ASC, b.InsertDate DESC
";
$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// Mirror guest_contact_format_display(): calling code + local (drop a leading 0).
$fmt = function ($cc, $local) {
    $local = trim((string) $local);
    $cc    = trim((string) $cc);
    if ($local === '') { return ''; }
    if ($cc === '')    { return $local; }
    if ($local[0] === '0') { $local = substr($local, 1); }
    return $local === '' ? $cc : $cc . ' ' . $local;
};

// --- build the sheet ------------------------------------------------------
$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Duplicate Contacts');
$spreadsheet->getProperties()->setCreator('HolidayGoGoGo');

$headers = array(
    'A' => 'CONTACT NUMBER',
    'B' => 'TIMES USED (BOOKINGS)',
    'C' => 'GUEST NAME',
    'D' => 'EMAIL',
    'E' => 'BOOKING NUMBER',
    'F' => 'BOOKING DATE',
    'G' => 'SALES AGENT',
    'H' => 'DESTINATION',
);
foreach ($headers as $col => $label) {
    $sheet->setCellValue($col . '1', $label);
}
$sheet->getStyle('A1:H1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_BLACK);
$sheet->getStyle('A1:H1')->getFont()->getColor()->setARGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE);
$sheet->getStyle('A1:H1')->getFont()->setBold(true);

$str = \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING;
$row = 2;
foreach ($rows as $r) {
    $bdate = (!empty($r['BookingDate']) && substr($r['BookingDate'], 0, 10) !== '0000-00-00')
        ? date('d M Y', strtotime($r['BookingDate'])) : '';
    $sheet->setCellValueExplicit('A' . $row, $fmt($r['CallingCode'], $r['ContactNum']), $str);
    $sheet->setCellValue('B' . $row, (int) $r['DupBookingCount']);
    $sheet->setCellValueExplicit('C' . $row, (string) $r['GuestName'],     $str);
    $sheet->setCellValueExplicit('D' . $row, (string) $r['Email'],         $str);
    $sheet->setCellValueExplicit('E' . $row, (string) $r['BookingNumber'], $str);
    $sheet->setCellValueExplicit('F' . $row, $bdate,                       $str);
    $sheet->setCellValueExplicit('G' . $row, (string) $r['AgentName'],     $str);
    $sheet->setCellValueExplicit('H' . $row, (string) $r['Destination'],   $str);
    $row++;
}
if (empty($rows)) {
    $sheet->mergeCells('A2:H2');
    $sheet->getCell('A2')->setValue('No Duplicate Contact Numbers Found');
}
foreach (array_keys($headers) as $col) {
    $sheet->getColumnDimension($col)->setWidth(28);
}

$out = __DIR__ . '/DUPLICATE_CONTACTS_' . date('Ymd') . '.xlsx';
(new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($out);

$numbers = array();
foreach ($rows as $r) { $numbers[$r['dedup_key']] = true; }
echo "Wrote " . $out . "\n";
echo "Duplicated numbers: " . count($numbers) . " | guest rows: " . count($rows) . "\n";
