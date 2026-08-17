<?php
// bootstrap.php
define('DB_HOST', 'localhost');
define('DB_NAME', 'RD Vendora');
define('DB_USER', 'root');
define('DB_PASS', '');
define('GOOGLE_CLIENT_ID', 'YOUR_CLIENT_ID.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', 'YOUR_CLIENT_SECRET');
define('GOOGLE_REDIRECT_URI', 'http://localhost/RD%20RD Vendora/google-callback.php');

session_start();

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}