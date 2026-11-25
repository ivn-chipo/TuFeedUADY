<?php
// Conexión a MySQL con PDO. Los datos se leen de variables de entorno
// y, si no existen, se usan los valores por defecto de XAMPP.

declare(strict_types=1);

require_once __DIR__ . '/utilidades.php';

function obtenerConexion(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host   = getenv('DB_HOST') ?: '127.0.0.1';
    $puerto = getenv('DB_PORT') ?: '3306';
    $base   = getenv('DB_NAME') ?: 'tufeeduady';
    $user   = getenv('DB_USER') ?: 'root';
    $pass   = getenv('DB_PASS') ?: '';

    $dsn = "mysql:host={$host};port={$puerto};dbname={$base};charset=utf8mb4";

    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Consultas preparadas reales en el servidor (contra inyección SQL)
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        // El detalle va al log; al usuario no se le muestran credenciales ni rutas
        error_log('[TuFeedUADY] Error de conexión: ' . $e->getMessage());
        responderError('No se pudo conectar con la base de datos', 500);
    }

    return $pdo;
}
