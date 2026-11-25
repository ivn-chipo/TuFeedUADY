<?php
// Funciones compartidas por todos los endpoints: respuestas JSON,
// manejo de sesión, lectura de datos de entrada y sanitización.

declare(strict_types=1);

// Los errores se registran en el log del servidor pero nunca se imprimen,
// para no romper el JSON ni mostrar detalles internos al usuario.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

date_default_timezone_set('America/Merida');

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// Cualquier excepción no atrapada termina en una respuesta JSON genérica
set_exception_handler(function (Throwable $e): void {
    error_log('[TuFeedUADY] ' . $e);
    responderError('Error interno del servidor', 500);
});

function responderJson(array $cuerpo, int $codigo = 200): never
{
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($cuerpo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function responderOk(mixed $datos = null, string $mensaje = 'OK', int $codigo = 200): never
{
    responderJson(['ok' => true, 'mensaje' => $mensaje, 'datos' => $datos], $codigo);
}

function responderError(string $error, int $codigo = 400): never
{
    responderJson(['ok' => false, 'error' => $error], $codigo);
}

// Corta la petición si el método HTTP no es uno de los permitidos
function requerirMetodo(string ...$permitidos): string
{
    $metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (!in_array($metodo, $permitidos, true)) {
        header('Allow: ' . implode(', ', $permitidos));
        responderError('Método no permitido', 405);
    }
    return $metodo;
}

// Devuelve los datos enviados, ya sea como JSON o como formulario.
// PUT y DELETE no llenan $_POST, por eso se lee php://input.
function leerEntrada(): array
{
    $tipo = $_SERVER['CONTENT_TYPE'] ?? '';
    $metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if (str_contains($tipo, 'application/json')) {
        $datos = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($datos)) {
            responderError('El cuerpo de la petición no es un JSON válido');
        }
        return $datos;
    }

    if ($metodo === 'POST') {
        return $_POST;
    }

    parse_str((string) file_get_contents('php://input'), $datos);
    return $datos;
}

// Obtiene un campo de texto sin espacios extra; '' si no viene o no es texto
function campoTexto(array $datos, string $nombre): string
{
    $valor = $datos[$nombre] ?? '';
    return is_scalar($valor) ? trim((string) $valor) : '';
}

// Obtiene un id entero positivo o null
function campoId(array $datos, string $nombre): ?int
{
    $valor = filter_var($datos[$nombre] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $valor === false ? null : $valor;
}

// Escapa recursivamente los textos que se devuelven al frontend (protección XSS)
function limpiar(mixed $valor): mixed
{
    if (is_array($valor)) {
        return array_map('limpiar', $valor);
    }
    if (is_string($valor)) {
        return htmlspecialchars($valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
    return $valor;
}

function iniciarSesion(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Devuelve el id del usuario con sesión activa o responde 401
function requerirSesion(): int
{
    iniciarSesion();
    $usuarioId = $_SESSION['usuario_id'] ?? null;
    if (!is_int($usuarioId)) {
        responderError('Debes iniciar sesión', 401);
    }
    return $usuarioId;
}
