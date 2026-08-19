<?php
/**
 * Apply database/schema.sql to the connected MySQL database.
 * Safe on a fresh Hostinger DB: CREATE TABLE IF NOT EXISTS + INSERT IGNORE only.
 */
if (!function_exists('rdv_schema_sql_path')) {
    function rdv_schema_sql_path() {
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'schema.sql';
    }
}

if (!function_exists('rdv_split_sql_statements')) {
    function rdv_split_sql_statements($sql) {
        $sql = str_replace("\r\n", "\n", (string) $sql);
        $sql = preg_replace('/^\s*--.*$/m', '', $sql);
        $statements = [];
        $buffer = '';
        $quote = '';
        $length = strlen($sql);
        for ($i = 0; $i < $length; $i++) {
            $ch = $sql[$i];
            $next = ($i + 1 < $length) ? $sql[$i + 1] : '';
            if ($quote === '' && $ch === '/' && $next === '*') {
                $end = strpos($sql, '*/', $i + 2);
                if ($end === false) {
                    break;
                }
                $i = $end + 1;
                continue;
            }
            if ($quote !== '') {
                $buffer .= $ch;
                if ($ch === '\\' && $quote !== '`') {
                    $buffer .= $next;
                    $i++;
                } elseif ($ch === $quote) {
                    $quote = '';
                }
                continue;
            }
            if ($ch === "'" || $ch === '"' || $ch === '`') {
                $quote = $ch;
                $buffer .= $ch;
                continue;
            }
            if ($ch === ';') {
                $trimmed = trim($buffer);
                if ($trimmed !== '') {
                    $statements[] = $trimmed;
                }
                $buffer = '';
                continue;
            }
            $buffer .= $ch;
        }
        $trimmed = trim($buffer);
        if ($trimmed !== '') {
            $statements[] = $trimmed;
        }
        return $statements;
    }
}

if (!function_exists('rdv_install_schema')) {
    function rdv_install_schema(mysqli $conn) {
        $path = rdv_schema_sql_path();
        if (!is_readable($path)) {
            return ['ok' => false, 'message' => 'schema.sql was not found on the server. Push database/schema.sql to GitHub, wait for Hostinger to deploy, then try again.'];
        }
        $sql = file_get_contents($path);
        if ($sql === false || trim($sql) === '') {
            return ['ok' => false, 'message' => 'schema.sql could not be read.'];
        }
        @set_time_limit(120);
        $statements = rdv_split_sql_statements($sql);
        $ran = 0;
        foreach ($statements as $statement) {
            try {
                if (!$conn->query($statement)) {
                    error_log('Schema install failed: ' . $conn->error . ' | ' . substr($statement, 0, 180));
                    return ['ok' => false, 'message' => 'SQL failed: ' . $conn->error];
                }
                $ran++;
            } catch (Throwable $e) {
                error_log('Schema install exception: ' . $e->getMessage() . ' | ' . substr($statement, 0, 180));
                return ['ok' => false, 'message' => 'SQL failed: ' . $e->getMessage()];
            }
        }
        if (!function_exists('rdv_db_table_exists') || !rdv_db_table_exists($conn, 'users')) {
            return ['ok' => false, 'message' => 'Install ran ' . $ran . ' statements but the users table is still missing. Check storage/logs/php_errors.log.'];
        }
        return ['ok' => true, 'message' => 'Database tables were created (' . $ran . ' statements). You can create the first super admin now.'];
    }
}
