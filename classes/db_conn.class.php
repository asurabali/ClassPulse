<?php

/**
 * ClassPulse - Database Connection Class
 * Secure PDO-based database connection with environment variable support.
 */
class db_conn {

    /**
     * Establishes and returns a PDO database connection.
     * Reads from environment variables if available, falls back to defaults.
     */
    public function connect() {
        $host = getenv('DB_HOST')   ?: 'localhost';
        $user = getenv('DB_USER')   ?: 'root';
        $pass = getenv('DB_PASS')   ?: '';
        $db   = getenv('DB_NAME')   ?: 'attendance';
        $port = getenv('DB_PORT')   ?: '3306';

        static $conn = null;

        if ($conn !== null) {
            return $conn;
        }

        try {
            $dsn  = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
            $opts = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $conn = new PDO($dsn, $user, $pass, $opts);
        } catch (PDOException $e) {
            // In production, log the error instead of echoing it
            error_log('DB Connection Error: ' . $e->getMessage());
            http_response_code(500);
            die(json_encode(['status' => 'error', 'msg' => 'Database connection failed. Please try again later.']));
        }

        return $conn;
    }
}

$conn = new db_conn();
