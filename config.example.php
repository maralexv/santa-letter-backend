<?php
// Copy this file to config.php (or point the SANTA_CONFIG environment variable at a
// copy stored OUTSIDE the web root) and fill in the values. config.php is git-ignored.
return [
    'db' => [
        'host' => 'localhost',
        'user' => 'CHANGE_ME',
        'pass' => 'CHANGE_ME',
        'name' => 'notes',
        // Use the character set your tables really use. See README, "Character set".
        'charset' => 'utf8mb4',
    ],
    'limits' => [
        // Must not exceed the column sizes in your database.
        'name' => 50,
        'note' => 1000,
    ],
];
