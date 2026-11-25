<?php
// Subida y descarga de archivos del usuario con sesión.
//   POST upload_avatar.php                          (multipart) campo "avatar"
//   GET  upload_avatar.php?accion=descargar_avatar  descarga la foto de perfil
//   GET  upload_avatar.php?accion=exportar_feeds    descarga sus feeds en .json

declare(strict_types=1);

require_once __DIR__ . '/conexion.php';

const TAMANO_MAXIMO = 2 * 1024 * 1024; // 2 MB
const DIMENSION_MAXIMA = 4000;         // px por lado
const TIPOS_PERMITIDOS = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
];
const RUTA_PUBLICA = 'img/uploads'; // relativa a src/, es lo que se guarda en la BD

$metodo    = requerirMetodo('GET', 'POST');
$usuarioId = requerirSesion();
$pdo       = obtenerConexion();

if ($metodo === 'POST') {
    subirAvatar($pdo, $usuarioId);
}

$accion = is_string($_GET['accion'] ?? null) ? $_GET['accion'] : '';
switch ($accion) {
    case 'descargar_avatar':
        descargarAvatar($pdo, $usuarioId);
        break;
    case 'exportar_feeds':
        exportarFeeds($pdo, $usuarioId);
        break;
    default:
        responderError('Acción no válida. Usa descargar_avatar o exportar_feeds', 404);
}

function carpetaUploads(): string
{
    return dirname(__DIR__) . '/' . RUTA_PUBLICA;
}

function subirAvatar(PDO $pdo, int $usuarioId): never
{
    $archivo = $_FILES['avatar'] ?? null;

    // Si el archivo supera post_max_size, PHP deja $_FILES vacío
    if (!is_array($archivo) || !is_int($archivo['error'] ?? null)) {
        responderError('Envía una imagen en el campo "avatar" (máximo 2 MB)', 422);
    }

    switch ($archivo['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            responderError('La imagen no puede pesar más de 2 MB', 413);
        case UPLOAD_ERR_NO_FILE:
            responderError('No se seleccionó ninguna imagen', 422);
        default:
            responderError('No se pudo recibir la imagen, intenta de nuevo', 400);
    }

    if (!is_uploaded_file($archivo['tmp_name'])) {
        responderError('Archivo no válido', 400);
    }
    if ($archivo['size'] <= 0 || $archivo['size'] > TAMANO_MAXIMO) {
        responderError('La imagen no puede pesar más de 2 MB', 413);
    }

    // El tipo se revisa con el contenido real del archivo, no con la
    // extensión ni con el tipo que manda el navegador
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($archivo['tmp_name']);
    if (!isset(TIPOS_PERMITIDOS[$mime])) {
        responderError('Solo se permiten imágenes JPG, PNG o WebP', 415);
    }

    $dimensiones = @getimagesize($archivo['tmp_name']);
    if ($dimensiones === false) {
        responderError('El archivo no es una imagen válida', 415);
    }
    if ($dimensiones[0] > DIMENSION_MAXIMA || $dimensiones[1] > DIMENSION_MAXIMA) {
        responderError('La imagen no puede medir más de ' . DIMENSION_MAXIMA . ' px por lado', 422);
    }

    $carpeta = carpetaUploads();
    if (!is_dir($carpeta) && !mkdir($carpeta, 0755, true) && !is_dir($carpeta)) {
        throw new RuntimeException('No se pudo crear la carpeta ' . $carpeta);
    }

    // Nombre aleatorio: nunca se usa el nombre original del archivo
    $nombre = bin2hex(random_bytes(16)) . '.' . TIPOS_PERMITIDOS[$mime];
    $destino = $carpeta . '/' . $nombre;
    if (!move_uploaded_file($archivo['tmp_name'], $destino)) {
        throw new RuntimeException('No se pudo mover la imagen a ' . $destino);
    }
    chmod($destino, 0644);

    $anterior = obtenerFotoPerfil($pdo, $usuarioId);

    try {
        $stmt = $pdo->prepare('UPDATE usuarios SET foto_perfil = :foto WHERE id = :id');
        $stmt->execute([':foto' => RUTA_PUBLICA . '/' . $nombre, ':id' => $usuarioId]);
    } catch (Throwable $e) {
        unlink($destino);
        throw $e;
    }

    if ($anterior !== null && is_file($anterior)) {
        unlink($anterior);
    }

    responderOk(['foto_perfil' => RUTA_PUBLICA . '/' . $nombre], 'Foto de perfil actualizada', 201);
}

function descargarAvatar(PDO $pdo, int $usuarioId): never
{
    $ruta = obtenerFotoPerfil($pdo, $usuarioId);
    if ($ruta === null || !is_file($ruta)) {
        responderError('No tienes foto de perfil', 404);
    }

    $extension = pathinfo($ruta, PATHINFO_EXTENSION);
    enviarArchivo(
        (string) file_get_contents($ruta),
        (string) array_search($extension, TIPOS_PERMITIDOS, true),
        'avatar-' . obtenerNickname($pdo, $usuarioId) . '.' . $extension
    );
}

function exportarFeeds(PDO $pdo, int $usuarioId): never
{
    $stmt = $pdo->prepare('SELECT tema, url_rss, creado_en FROM preferencias_feeds WHERE usuario_id = :id ORDER BY tema, creado_en');
    $stmt->execute([':id' => $usuarioId]);

    $nickname = obtenerNickname($pdo, $usuarioId);
    $contenido = json_encode([
        'usuario'      => $nickname,
        'exportado_en' => date('c'),
        'feeds'        => $stmt->fetchAll(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    enviarArchivo((string) $contenido, 'application/json', 'feeds-' . $nickname . '-' . date('Ymd') . '.json');
}

// Manda el contenido como archivo descargable
function enviarArchivo(string $contenido, string $tipo, string $nombre): never
{
    http_response_code(200);
    header('Content-Type: ' . $tipo);
    header('Content-Disposition: attachment; filename="' . $nombre . '"');
    header('Content-Length: ' . strlen($contenido));
    header('Cache-Control: private, no-store');
    echo $contenido;
    exit;
}

// Ruta absoluta de la foto de perfil actual o null si no tiene
function obtenerFotoPerfil(PDO $pdo, int $usuarioId): ?string
{
    $stmt = $pdo->prepare('SELECT foto_perfil FROM usuarios WHERE id = :id');
    $stmt->execute([':id' => $usuarioId]);
    $foto = $stmt->fetchColumn();

    if (!is_string($foto) || $foto === '') {
        return null;
    }
    // basename() evita salir de la carpeta de uploads
    return carpetaUploads() . '/' . basename($foto);
}

function obtenerNickname(PDO $pdo, int $usuarioId): string
{
    $stmt = $pdo->prepare('SELECT nickname FROM usuarios WHERE id = :id');
    $stmt->execute([':id' => $usuarioId]);
    return (string) $stmt->fetchColumn();
}
