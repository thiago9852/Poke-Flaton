<?php
// Query the sqlite database and print user caught_pokemon
try {
    $dbPath = __DIR__ . '/../var/data.db';
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $db->query('SELECT id, username, caught_pokemon, regional FROM user');
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "USERS:\n";
    foreach ($users as $user) {
        echo "ID: {$user['id']}, Username: {$user['username']}, Regional: {$user['regional']}\n";
        echo "Caught: {$user['caught_pokemon']}\n\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
