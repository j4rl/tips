<?php
// Kopiera denna fil till config.php och fyll i värdena

$DB_HOST = '127.0.0.1';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'tipsdb';

// Bas-URL för appen, t.ex. "/tips" om du lägger appen i C:\xampp\htdocs\tips
// Lämna tom sträng om appen ligger direkt i docroot
$BASE_PATH = '/tips';

// Filuppladdning (bilder till frågor)
$UPLOAD_DIR = __DIR__ . '/admin/uploads';
$UPLOAD_URL = $BASE_PATH . '/admin/uploads';

// Sessions/Timezone
date_default_timezone_set('Europe/Stockholm');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$mysqli = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_error) {
    die('DB-anslutning misslyckades: ' . $mysqli->connect_error);
}
$mysqli->set_charset('utf8mb4');

function base_url(string $path = ''): string {
    global $BASE_PATH;
    if ($BASE_PATH === '' || $BASE_PATH === '/') {
        return $path;
    }
    if ($path === '') return $BASE_PATH;
    return rtrim($BASE_PATH, '/') . '/' . ltrim($path, '/');
}

