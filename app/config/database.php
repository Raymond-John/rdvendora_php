<?php
/**
 * Database connection for RD Vendora.
 * Credentials come from .env with local XAMPP fallbacks.
 */
$host     = rdv_env('DB_HOST', 'localhost');
$username = rdv_env('DB_USER', 'root');
$password = rdv_env('DB_PASS', '');
$dbname   = rdv_env('DB_NAME', 'rdvendora_db');
$port     = (int) rdv_env('DB_PORT', 3306);

$connect = mysqli_init();
if (!$connect) {
    die('Database connection failed.');
}

$connect->options(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, 1);

if (!$connect->real_connect($host, $username, $password, $dbname, $port)) {
    error_log('Database connection failed: ' . mysqli_connect_error());
    if (rdv_env('APP_DEBUG', false)) {
        die('Connection failed: ' . mysqli_connect_error());
    }
    die('Database connection failed.');
}

$connect->set_charset(rdv_env('DB_CHARSET', 'utf8mb4'));
$conn = $connect;

if (!function_exists('rdv_db_table_exists')) {
    function rdv_db_table_exists(mysqli $db, $table) {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $table);
        if ($table === '') {
            return false;
        }
        try {
            $res = $db->query("SHOW TABLES LIKE '" . $db->real_escape_string($table) . "'");
            return $res && $res->num_rows > 0;
        } catch (Throwable $e) {
            error_log('rdv_db_table_exists: ' . $e->getMessage());
            return false;
        }
    }
}
