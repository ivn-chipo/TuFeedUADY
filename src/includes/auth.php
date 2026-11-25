<?php
// Registro, inicio y cierre de sesión de usuarios.
//   POST auth.php?accion=registro  nickname, correo, password, campus
//   POST auth.php?accion=login     usuario (nickname o correo), password
//   POST auth.php?accion=logout
//   GET  auth.php?accion=sesion

declare(strict_types=1);

require_once __DIR__ . '/conexion.php';

iniciarSesion();

$accion = is_string($_GET['accion'] ?? null) ? $_GET['accion'] : '';

switch ($accion) {
    case 'registro':
        requerirMetodo('POST');
        registrar(leerEntrada());
        break;
    case 'login':
        requerirMetodo('POST');
        iniciarSesionUsuario(leerEntrada());
        break;
    case 'logout':
        requerirMetodo('POST');
        cerrarSesion();
        break;
    case 'sesion':
        requerirMetodo('GET');
        responderOk(buscarUsuario(requerirSesion()));
        break;
    default:
        responderError('Acción no válida. Usa registro, login, logout o sesion', 404);
}

function registrar(array $datos): never
{
    $nickname = campoTexto($datos, 'nickname');
    $correo   = campoTexto($datos, 'correo');
    $password = is_string($datos['password'] ?? null) ? $datos['password'] : '';
    $campus   = campoTexto($datos, 'campus');

    $errores = [];
    if (!preg_match('/^[A-Za-z0-9_.-]{3,30}$/', $nickname)) {
        $errores['nickname'] = 'Debe tener de 3 a 30 caracteres: letras, números, punto, guion o guion bajo';
    }
    if (strlen($correo) > 120 || filter_var($correo, FILTER_VALIDATE_EMAIL) === false) {
        $errores['correo'] = 'El correo no es válido';
    }
    // bcrypt solo toma en cuenta los primeros 72 bytes
    if (mb_strlen($password) < 8 || strlen($password) > 72) {
        $errores['password'] = 'Debe tener de 8 a 72 caracteres';
    }
    if ($campus === '' || strlen($campus) > 10) {
        $errores['campus'] = 'Selecciona un campus';
    }
    if ($errores) {
        responderJson(['ok' => false, 'error' => 'Datos inválidos', 'campos' => $errores], 422);
    }

    $pdo = obtenerConexion();
    try {
        $stmt = $pdo->prepare('CALL sp_registrar_usuario(:nickname, :correo, :hash, :campus, @nuevo_id)');
        $stmt->execute([
            ':nickname' => $nickname,
            ':correo'   => $correo,
            ':hash'     => password_hash($password, PASSWORD_DEFAULT),
            ':campus'   => $campus,
        ]);
        $stmt->closeCursor();
    } catch (PDOException $e) {
        $codigoMysql = $e->errorInfo[1] ?? null;
        if ($codigoMysql === 1062) { // llave única duplicada
            $campo = str_contains($e->getMessage(), 'uq_usuarios_nickname') ? 'nickname' : 'correo';
            responderJson(['ok' => false, 'error' => 'Ya existe una cuenta con ese ' . $campo, 'campos' => [$campo => 'Ya está registrado']], 409);
        }
        if ($codigoMysql === 1644) { // SIGNAL del procedimiento
            responderJson(['ok' => false, 'error' => 'Datos inválidos', 'campos' => ['campus' => 'El campus indicado no existe']], 422);
        }
        throw $e;
    }

    $usuarioId = (int) $pdo->query('SELECT @nuevo_id')->fetchColumn();

    session_regenerate_id(true);
    $_SESSION['usuario_id'] = $usuarioId;

    responderOk(buscarUsuario($usuarioId), 'Cuenta creada', 201);
}

function iniciarSesionUsuario(array $datos): never
{
    $usuario  = campoTexto($datos, 'usuario');
    $password = is_string($datos['password'] ?? null) ? $datos['password'] : '';

    if ($usuario === '' || $password === '') {
        responderError('Escribe tu usuario y contraseña', 422);
    }

    $pdo = obtenerConexion();
    $stmt = $pdo->prepare('SELECT id, password_hash FROM usuarios WHERE nickname = :nickname OR correo = :correo LIMIT 1');
    $stmt->execute([':nickname' => $usuario, ':correo' => $usuario]);
    $fila = $stmt->fetch();

    // El mismo mensaje para usuario inexistente y contraseña incorrecta
    if ($fila === false || !password_verify($password, $fila['password_hash'])) {
        responderError('Usuario o contraseña incorrectos', 401);
    }

    $usuarioId = (int) $fila['id'];

    if (password_needs_rehash($fila['password_hash'], PASSWORD_DEFAULT)) {
        $pdo->prepare('UPDATE usuarios SET password_hash = :hash WHERE id = :id')
            ->execute([':hash' => password_hash($password, PASSWORD_DEFAULT), ':id' => $usuarioId]);
    }

    // Nuevo id de sesión para evitar fijación de sesión
    session_regenerate_id(true);
    $_SESSION['usuario_id'] = $usuarioId;

    responderOk(buscarUsuario($usuarioId), 'Sesión iniciada');
}

function cerrarSesion(): never
{
    $_SESSION = [];
    $parametros = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires'  => time() - 3600,
        'path'     => $parametros['path'],
        'secure'   => $parametros['secure'],
        'httponly' => $parametros['httponly'],
        'samesite' => $parametros['samesite'],
    ]);
    session_destroy();

    responderOk(null, 'Sesión cerrada');
}

function buscarUsuario(int $usuarioId): array
{
    $stmt = obtenerConexion()->prepare(
        'SELECT id, nickname, correo, foto_perfil, campus_clave, campus_nombre, total_feeds
         FROM vista_usuarios_campus WHERE id = :id'
    );
    $stmt->execute([':id' => $usuarioId]);
    $usuario = $stmt->fetch();

    if ($usuario === false) {
        // La cuenta ya no existe: se descarta la sesión
        $_SESSION = [];
        responderError('Debes iniciar sesión', 401);
    }

    return limpiar($usuario);
}
