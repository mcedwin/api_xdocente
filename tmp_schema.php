<?php
$pdo = new PDO('sqlite:' . __DIR__ . '/database/database.sqlite');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $pdo->query("SELECT name, sql FROM sqlite_master WHERE type='table' AND name LIKE 'app_activity%' ORDER BY name");
foreach ($stmt as $row) {
    echo "\n=== " . $row['name'] . " ===\n";
    echo $row['sql'] . "\n";
}
