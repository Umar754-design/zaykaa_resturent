<?php
// ===================================================
// Database Configuration & Connection - Zayka Platform
// ===================================================

if (ob_get_level() === 0) {
    ob_start();
}

// Load environment variables from the project-root .env file (never commit it)
if (!function_exists('zayka_load_env')) {
    function zayka_load_env($file) {
        if (!is_readable($file)) {
            return;
        }
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
                continue;
            }
            [$key, $value] = array_map('trim', explode('=', $line, 2));
            $value = trim($value, "\"'");
            if (getenv($key) === false) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
            }
        }
    }
}
zayka_load_env(dirname(__DIR__) . '/.env');

if (!function_exists('env')) {
    function env($key, $default = null) {
        $value = getenv($key);
        return ($value === false || $value === '') ? $default : $value;
    }
}

$isLocal = (!isset($_SERVER['SERVER_NAME']) || in_array($_SERVER['SERVER_NAME'], ['localhost', '127.0.0.1', '::1'])) || php_sapi_name() === 'cli';

define('APP_IS_LOCAL', $isLocal);
define('APP_DEBUG', filter_var(env('APP_DEBUG', $isLocal ? 'true' : 'false'), FILTER_VALIDATE_BOOLEAN));
define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_PORT', (int) env('DB_PORT', 3306));
define('DB_NAME', env('DB_NAME', 'zayka_db'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', ''));
define('DB_CHARSET', 'utf8mb4');

/**
 * Returns the PDO database connection singleton.
 * Automatically checks and initializes the database with schema and seed data if not yet created.
 */
function get_db_connection() {
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    $dbName = DB_NAME;

    try {
        // Connect to server (without specifying database initially)
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // If primary credentials fail locally, attempt XAMPP root fallback (never in production)
            if (DB_USER !== 'root' && APP_IS_LOCAL) {
                $pdo = new PDO("mysql:host=localhost;port=3306;charset=utf8mb4", 'root', '', $options);
                $dbName = 'zayka_db';
            } else {
                throw $e;
            }
        }

        // Check if database exists
        $stmt = $pdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '" . $dbName . "'");
        $dbExists = $stmt->fetchColumn();

        if (!$dbExists) {
            init_database($pdo, $dbName);
        } else {
            $pdo->exec("USE `" . $dbName . "`");
            // Verify if tables exist
            $tableCheck = $pdo->query("SHOW TABLES LIKE 'users'")->fetchColumn();
            if (!$tableCheck) {
                init_database($pdo, $dbName);
            } else {
                // Ensure serving_size column exists safely
                try {
                    $colCheck = $pdo->query("SHOW COLUMNS FROM `menu_items` LIKE 'serving_size'")->fetchColumn();
                    if (!$colCheck) {
                        $pdo->exec("ALTER TABLE `menu_items` ADD COLUMN `serving_size` VARCHAR(100) DEFAULT NULL AFTER `discount_price`");
                    }
                    $orderColCheck = $pdo->query("SHOW COLUMNS FROM `order_items` LIKE 'serving_size'")->fetchColumn();
                    if (!$orderColCheck) {
                        $pdo->exec("ALTER TABLE `order_items` ADD COLUMN `serving_size` VARCHAR(100) DEFAULT NULL AFTER `item_name`");
                    }
                } catch (Exception $ignored) {
                    // Ignore if tables are not initialized yet
                }
            }
        }

        return $pdo;
    } catch (PDOException $e) {
        error_log('Zayka DB connection error: ' . $e->getMessage());
        if (!APP_DEBUG) {
            http_response_code(500);
            die("<div style='font-family:sans-serif;padding:30px;'><h2>Service temporarily unavailable</h2><p>Please try again in a few minutes.</p></div>");
        }
        // Detailed error in development mode
        die("<div style='font-family:sans-serif;padding:30px;background:#fee;color:#900;border:1px solid #fcc;margin:20px;border-radius:8px;'>
            <h2>Database Connection Error</h2>
            <p>Could not connect to MySQL server on <strong>" . DB_HOST . ":" . DB_PORT . "</strong>.</p>
            <p>Error details: " . htmlspecialchars($e->getMessage()) . "</p>
            <p><em>Please ensure MySQL is running in your XAMPP Control Panel.</em></p>
        </div>");
    }
}

/**
 * Helper to initialize database schema and seed data
 */
function init_database($pdo, $targetDb = null) {
    $schemaFile = dirname(__DIR__) . '/database/schema.sql';
    $seedFile = dirname(__DIR__) . '/database/seed.sql';
    $db = $targetDb ?: DB_NAME;

    if (file_exists($schemaFile)) {
        $sql = file_get_contents($schemaFile);
        $pdo->exec($sql);
    }

    $pdo->exec("USE `" . $db . "`");

    if (file_exists($seedFile)) {
        $seedSql = file_get_contents($seedFile);
        $pdo->exec($seedSql);
    }
}

// Initialize and export global $pdo variable for existing or procedural scripts
$pdo = get_db_connection();
