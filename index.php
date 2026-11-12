<?php
declare(strict_types=1);
require __DIR__ . '/src/bootstrap.php';

$maxNote = (int) config()['limits']['note'];
$maxName = (int) config()['limits']['name'];
$pageTitle = 'Santa Letter';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php require __DIR__ . '/partials/head.php'; ?>
    <script src="<?= e(asset('app.js')) ?>" defer></script>
</head>
<body>
<main class="wrap">
    <header class="hero">
        <h1>Dear Santa,</h1>
        <p>Write down what you would like for Christmas.</p>
    </header>

    <section id="login" class="card" hidden>
        <form id="login-form">
            <label for="name">Type in your name please:</label>
            <div class="row">
                <input id="name" name="name" type="text" maxlength="<?= $maxName ?>"
                       autocomplete="given-name" placeholder="Your name" required>
                <button class="btn" type="submit">Continue</button>
            </div>
        </form>
    </section>

    <section id="app" class="card" hidden>
        <p class="who"><span id="greeting"></span><strong id="who"></strong>
            <button id="logout" class="link" type="button">not you?</button></p>

        <form id="note-form">
            <label for="note">Add to your Christmas wish list:</label>
            <textarea id="note" rows="3" maxlength="<?= $maxNote ?>"
                      placeholder="Type here and add to your Christmas wish list..."></textarea>
            <button id="add" class="btn" type="submit" disabled>Add wish</button>
        </form>

        <h2>Your Christmas wish list for this year</h2>
        <ul id="notes" class="notes"></ul>
        <p id="empty" class="muted" hidden>Nothing here yet. Add your first wish above.</p>
    </section>

    <p id="message" class="message" role="status" aria-live="polite"></p>
</main>

<footer class="site-footer">
    <a class="btn secondary" href="results.php">See everyone's wishes</a>
    <p>Copyright scarvesfamily&copy; <?= date('Y') ?></p>
</footer>
</body>
</html>
