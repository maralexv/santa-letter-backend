<?php
declare(strict_types=1);

// GET    -> who is signed in (also hands out the CSRF token)
// POST   -> sign in with a name; creates the user on first use
// DELETE -> sign out
define('SANTA_API', true);
require __DIR__ . '/../src/bootstrap.php';

/** Look a user up by name. The database collation decides what counts as the same name. */
function select_user(string $name): ?array
{
    $stmt = db()->prepare('SELECT UserID, UserName FROM users WHERE UserName = ? ORDER BY UserID LIMIT 1');
    $stmt->bind_param('s', $name);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ?: null;
}

/** @return array{0:int,1:string,2:bool} id, stored name, whether it was just created */
function find_or_create_user(string $name): array
{
    $row = select_user($name);
    if ($row) {
        return [(int) $row['UserID'], $row['UserName'], false];
    }
    try {
        $stmt = db()->prepare('INSERT INTO users (UserName) VALUES (?)');
        $stmt->bind_param('s', $name);
        $stmt->execute();
        return [(int) db()->insert_id, $name, true];
    } catch (mysqli_sql_exception $e) {
        // 1062 = duplicate key: another request created the same name a moment ago.
        $row = $e->getCode() === 1062 ? select_user($name) : null;
        if (!$row) {
            throw $e;
        }
        return [(int) $row['UserID'], $row['UserName'], false];
    }
}

start_session();

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        json_out([
            'user' => isset($_SESSION['uid']) ? ['name' => $_SESSION['name']] : null,
            'csrf' => csrf_token(),
        ]);

    case 'POST':
        require_csrf();
        $name = clean_name(json_body()['name'] ?? null);
        if (!valid_name($name)) {
            fail(422, 'Please type your name (up to ' . config()['limits']['name'] . ' characters).');
        }
        [$id, $storedName, $isNew] = find_or_create_user($name);
        $csrf = csrf_token();
        session_regenerate_id(true);
        $_SESSION['uid'] = $id;
        $_SESSION['name'] = $storedName;
        json_out(['user' => ['name' => $storedName], 'isNew' => $isNew, 'csrf' => $csrf]);

    case 'DELETE':
        require_csrf();
        $_SESSION = [];
        session_regenerate_id(true);
        json_out(['ok' => true, 'csrf' => csrf_token()]);

    default:
        header('Allow: GET, POST, DELETE');
        fail(405, 'Method not allowed.');
}
