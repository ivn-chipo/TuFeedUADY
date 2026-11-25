<?php
// Altas, bajas, cambios y consultas de los feeds del usuario con sesión.
//   GET    crud_preferencias.php           todos los feeds
//   GET    crud_preferencias.php?q=texto   búsqueda por palabra clave
//   GET    crud_preferencias.php?id=5      un feed
//   POST   tema, url_rss                   alta
//   PUT    id, tema, url_rss               cambio
//   DELETE id (en la URL o en el cuerpo)   baja

declare(strict_types=1);

require_once __DIR__ . '/conexion.php';

$metodo    = requerirMetodo('GET', 'POST', 'PUT', 'DELETE');
$usuarioId = requerirSesion();
$pdo       = obtenerConexion();

switch ($metodo) {
    case 'GET':
        consultar($pdo, $usuarioId);
        break;
    case 'POST':
        crear($pdo, $usuarioId, leerEntrada());
        break;
    case 'PUT':
        actualizar($pdo, $usuarioId, leerEntrada());
        break;
    case 'DELETE':
        eliminar($pdo, $usuarioId, leerEntrada());
        break;
}

function consultar(PDO $pdo, int $usuarioId): never
{
    if (isset($_GET['id'])) {
        $feedId = campoId($_GET, 'id');
        if ($feedId === null) {
            responderError('El id no es válido', 422);
        }
        responderOk(limpiar(buscarFeed($pdo, $usuarioId, $feedId)));
    }

    $palabra = campoTexto($_GET, 'q');
    if (mb_strlen($palabra) > 100) {
        responderError('La búsqueda no puede pasar de 100 caracteres', 422);
    }

    // Se escapan los comodines de LIKE para que % y _ se busquen como texto
    $stmt = $pdo->prepare('CALL sp_buscar_feeds(:usuario, :palabra)');
    $stmt->execute([':usuario' => $usuarioId, ':palabra' => addcslashes($palabra, '%_\\')]);
    $feeds = $stmt->fetchAll();
    $stmt->closeCursor();

    responderOk(limpiar($feeds), count($feeds) . ' feed(s) encontrado(s)');
}

function crear(PDO $pdo, int $usuarioId, array $datos): never
{
    [$tema, $url] = validarFeed($datos);

    try {
        $stmt = $pdo->prepare('INSERT INTO preferencias_feeds (usuario_id, tema, url_rss) VALUES (:usuario, :tema, :url)');
        $stmt->execute([':usuario' => $usuarioId, ':tema' => $tema, ':url' => $url]);
    } catch (PDOException $e) {
        responderSiDuplicado($e);
        throw $e;
    }

    $feed = buscarFeed($pdo, $usuarioId, (int) $pdo->lastInsertId());
    responderOk(limpiar($feed), 'Feed agregado', 201);
}

function actualizar(PDO $pdo, int $usuarioId, array $datos): never
{
    $feedId = campoId($datos, 'id');
    if ($feedId === null) {
        responderError('El id no es válido', 422);
    }
    [$tema, $url] = validarFeed($datos);

    // Confirma que el feed exista y sea del usuario antes de modificarlo
    buscarFeed($pdo, $usuarioId, $feedId);

    try {
        $stmt = $pdo->prepare('UPDATE preferencias_feeds SET tema = :tema, url_rss = :url WHERE id = :id AND usuario_id = :usuario');
        $stmt->execute([':tema' => $tema, ':url' => $url, ':id' => $feedId, ':usuario' => $usuarioId]);
    } catch (PDOException $e) {
        responderSiDuplicado($e);
        throw $e;
    }

    responderOk(limpiar(buscarFeed($pdo, $usuarioId, $feedId)), 'Feed actualizado');
}

function eliminar(PDO $pdo, int $usuarioId, array $datos): never
{
    $feedId = campoId($_GET, 'id') ?? campoId($datos, 'id');
    if ($feedId === null) {
        responderError('El id no es válido', 422);
    }

    $stmt = $pdo->prepare('DELETE FROM preferencias_feeds WHERE id = :id AND usuario_id = :usuario');
    $stmt->execute([':id' => $feedId, ':usuario' => $usuarioId]);

    if ($stmt->rowCount() === 0) {
        responderError('No se encontró el feed', 404);
    }
    responderOk(['id' => $feedId], 'Feed eliminado');
}

// Valida tema y URL; devuelve [tema, url] o responde 422
function validarFeed(array $datos): array
{
    $tema = campoTexto($datos, 'tema');
    $url  = campoTexto($datos, 'url_rss');

    $errores = [];
    if ($tema === '' || mb_strlen($tema) > 60) {
        $errores['tema'] = 'Debe tener de 1 a 60 caracteres';
    }
    // Solo http/https, para no aceptar esquemas como javascript:
    $esquema = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    if (strlen($url) > 500 || filter_var($url, FILTER_VALIDATE_URL) === false || !in_array($esquema, ['http', 'https'], true)) {
        $errores['url_rss'] = 'Debe ser una URL http o https válida de máximo 500 caracteres';
    }
    if ($errores) {
        responderJson(['ok' => false, 'error' => 'Datos inválidos', 'campos' => $errores], 422);
    }

    return [$tema, $url];
}

function buscarFeed(PDO $pdo, int $usuarioId, int $feedId): array
{
    $stmt = $pdo->prepare('SELECT id, tema, url_rss, creado_en FROM preferencias_feeds WHERE id = :id AND usuario_id = :usuario');
    $stmt->execute([':id' => $feedId, ':usuario' => $usuarioId]);
    $feed = $stmt->fetch();

    if ($feed === false) {
        responderError('No se encontró el feed', 404);
    }
    return $feed;
}

function responderSiDuplicado(PDOException $e): void
{
    if (($e->errorInfo[1] ?? null) === 1062) {
        responderJson(['ok' => false, 'error' => 'Ya tienes guardado ese feed', 'campos' => ['url_rss' => 'Ya está guardado']], 409);
    }
}
