<?php
declare(strict_types=1);

/**
 * One-off migration. The old code ran htmlspecialchars() on user names and notes BEFORE
 * saving them, so the database holds "Tom &amp; Jerry" and "it&#039;s". The new code stores
 * text as typed and escapes it only when displaying, so existing rows must be decoded once.
 *
 *   php db/decode_legacy_notes.php            dry run: shows what would change, writes nothing
 *   php db/decode_legacy_notes.php --apply    does it, once
 *
 * Run it ONCE, after a database backup, right when you deploy the new code. Running it a
 * second time (or after people have typed new notes) would decode text twice, so --apply
 * writes a marker file and refuses to run again.
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}
require __DIR__ . '/../src/bootstrap.php';

$apply = in_array('--apply', $argv, true);
$marker = __DIR__ . '/.legacy_decoded';
if ($apply && is_file($marker)) {
    fwrite(STDERR, "Already applied (marker file $marker exists). Nothing done.\n");
    exit(1);
}

function decode(string $s): string
{
    return htmlspecialchars_decode($s, ENT_QUOTES | ENT_HTML401);
}

$db = db();
$targets = [
    'users' => ['UserID', 'UserName'],
    'notes' => ['NoteID', 'NoteText'],
];

$db->begin_transaction();
$total = 0;
foreach ($targets as $table => [$idCol, $textCol]) {
    $changed = 0;
    $update = $db->prepare("UPDATE `$table` SET `$textCol` = ? WHERE `$idCol` = ?");
    $rows = $db->query("SELECT `$idCol`, `$textCol` FROM `$table`");
    foreach ($rows as $row) {
        $decoded = decode($row[$textCol]);
        if ($decoded === $row[$textCol]) {
            continue;
        }
        $changed++;
        printf("%s #%s: %s  ->  %s\n", $table, $row[$idCol], json_encode($row[$textCol], JSON_UNESCAPED_UNICODE), json_encode($decoded, JSON_UNESCAPED_UNICODE));
        if ($apply) {
            $id = (int) $row[$idCol];
            $update->bind_param('si', $decoded, $id);
            $update->execute();
        }
    }
    echo "$table: $changed row(s) " . ($apply ? 'updated' : 'would change') . "\n";
    $total += $changed;
}

if ($apply) {
    $db->commit();
    file_put_contents($marker, date('c') . "\n");
    echo "Done ($total rows). Marker written: $marker\n";
} else {
    $db->rollback();
    echo "Dry run only. Re-run with --apply to write the changes.\n";
}
