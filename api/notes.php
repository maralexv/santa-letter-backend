<?php
declare(strict_types=1);

// All calls act on the signed-in user's own notes; the user id always comes from the session.
// GET    -> this year's notes, newest first
// POST   -> add a note        {"text": "..."}
// DELETE -> remove a note     {"id": 123}
define('SANTA_API', true);
require __DIR__ . '/../src/bootstrap.php';

start_session();
$uid = $_SESSION['uid'] ?? null;
if (!is_int($uid)) {
    fail(401, 'Please type your name first.');
}

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        [$from, $to] = year_range();
        $stmt = db()->prepare(
            'SELECT NoteID, NoteText, Time FROM notes
             WHERE User_id = ? AND Time >= ? AND Time < ?
             ORDER BY NoteID DESC'
        );
        $stmt->bind_param('iss', $uid, $from, $to);
        $stmt->execute();
        $notes = [];
        foreach ($stmt->get_result() as $row) {
            // NoteText is nullable in the database; show such rows as empty so they can still be deleted.
            $notes[] = ['id' => (int) $row['NoteID'], 'text' => (string) $row['NoteText'], 'time' => $row['Time']];
        }
        json_out(['notes' => $notes]);

    case 'POST':
        require_csrf();
        $text = clean_note(json_body()['text'] ?? null);
        if (!valid_note($text)) {
            fail(422, 'Please type a wish (up to ' . config()['limits']['note'] . ' characters).');
        }
        $stmt = db()->prepare('INSERT INTO notes (NoteText, User_id) VALUES (?, ?)');
        $stmt->bind_param('si', $text, $uid);
        $stmt->execute();
        $id = (int) db()->insert_id;

        // Read the row back so the client shows exactly what was stored (including the timestamp).
        $stmt = db()->prepare('SELECT Time FROM notes WHERE NoteID = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        json_out(['note' => ['id' => $id, 'text' => $text, 'time' => $row['Time'] ?? '']], 201);

    case 'DELETE':
        require_csrf();
        $id = json_body()['id'] ?? null;
        if (!is_int($id) || $id < 1) {
            fail(422, 'Invalid note.');
        }
        $stmt = db()->prepare('DELETE FROM notes WHERE NoteID = ? AND User_id = ?');
        $stmt->bind_param('ii', $id, $uid);
        $stmt->execute();
        if ($stmt->affected_rows === 0) {
            fail(404, 'That wish was not found.');
        }
        json_out(['ok' => true]);

    default:
        header('Allow: GET, POST, DELETE');
        fail(405, 'Method not allowed.');
}
