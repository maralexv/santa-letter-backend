<?php
declare(strict_types=1);
require __DIR__ . '/src/bootstrap.php';

[$from, $to] = year_range();
$stmt = db()->prepare(
    'SELECT u.UserID, u.UserName, n.NoteText
     FROM notes n
     INNER JOIN users u ON n.User_id = u.UserID
     WHERE n.Time >= ? AND n.Time < ?
     ORDER BY u.UserName ASC, u.UserID ASC, n.NoteID ASC'
);
$stmt->bind_param('ss', $from, $to);
$stmt->execute();

// Group the wishes per person. Names containing "@" are shown masked (see mask_name()).
$people = [];
foreach ($stmt->get_result() as $row) {
    $wish = (string) $row['NoteText'];      // NoteText is nullable in the database
    if (trim($wish) === '') {
        continue;
    }
    $id = (int) $row['UserID'];
    if (!isset($people[$id])) {
        $people[$id] = ['name' => mask_name($row['UserName']), 'wishes' => []];
    }
    $people[$id]['wishes'][] = $wish;
}

$pageTitle = 'All wishes';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php require __DIR__ . '/partials/head.php'; ?>
</head>
<body>
<main class="wrap">
    <header class="hero">
        <h1>All Christmas wishes for this year</h1>
    </header>

<?php if (!$people): ?>
    <p class="card muted center">Nothing on record yet...</p>
<?php endif; ?>

<?php foreach ($people as $person): ?>
    <section class="card">
        <h2 class="person"><?= e($person['name']) ?></h2>
        <ul class="notes">
<?php foreach ($person['wishes'] as $wish): ?>
            <li class="note"><p><?= e($wish) ?></p></li>
<?php endforeach; ?>
        </ul>
    </section>
<?php endforeach; ?>
</main>

<footer class="site-footer">
    <a class="btn secondary" href="index.php">Back to my list</a>
    <p>Copyright scarvesfamily&copy; <?= date('Y') ?></p>
</footer>
</body>
</html>
