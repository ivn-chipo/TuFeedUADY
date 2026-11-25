# Guía de inicio e integración de TuFeedUADY

Este documento describe el flujo actual de inicio de sesión, registro y preferencias de feeds, y cómo ejecutar el proyecto en desarrollo.

## Flujo de páginas

1. El `index.html` de la raíz redirige a `src/login.html`.
2. Desde el login se puede iniciar sesión con correo o nickname, o abrir el registro con **Regístrate**.
3. El registro solicita nickname, campus, correo y contraseña. Al completarlo correctamente, el servidor crea la cuenta e inicia la sesión.
4. Login y registro llevan a `src/index.html`, la página principal.
5. En la página principal, **Preferencias de feeds** abre `src/feeds.html`. Ahí se pueden agregar, buscar, editar y eliminar feeds RSS.
6. La sesión se mantiene mediante la sesión PHP del servidor. **Cerrar sesión** la termina y vuelve al login.

El formulario de registro no incluye los intereses que tenía el prototipo: el esquema y el endpoint actuales no guardan ese dato. Las preferencias persistidas son feeds con tema y URL RSS. La pantalla de feeds administra esas URLs, pero todavía no descarga ni presenta los artículos RSS.

## Archivos principales

| Archivo | Responsabilidad |
|---|---|
| `index.html` | Entrada de la raíz; redirige al login. |
| `src/login.html` | Interfaz de inicio de sesión y enlace al registro. |
| `src/CSS/login.css` | Estilos y animación del login. |
| `src/JS/login.js` | Envía las credenciales a la API de autenticación. |
| `src/preferences.html` | Formulario de registro de usuarios. |
| `src/CSS/preferences.css` | Estilos y animación del registro. |
| `src/JS/preferences.js` | Envía los datos de registro a la API. |
| `src/index.html` | Página principal; muestra el estado de sesión y enlaza las preferencias. Conserva los contenedores vacíos del carrusel y de las noticias de ejemplo. |
| `src/JS/sesion.js` | Comprueba la sesión de la página principal y gestiona el cierre de sesión. |
| `src/JS/index.js` | Animación del carrusel de la página principal. |
| `src/feeds.html` | Interfaz para administrar feeds preferidos. |
| `src/CSS/feeds.css` | Estilos de la administración de feeds. |
| `src/JS/feeds.js` | Consume el CRUD de feeds y controla búsqueda, edición y eliminación. |
| `src/includes/auth.php` | Endpoints de registro, login, consulta de sesión y logout. |
| `src/includes/crud_preferencias.php` | Endpoints CRUD de feeds del usuario autenticado. |
| `src/includes/conexion.php` | Conexión PDO a MySQL. |
| `src/includes/utilidades.php` | Sesiones, lectura de solicitudes y respuestas JSON compartidas. |
| `docs/database/schema.sql` | Esquema de MySQL, procedimientos, vista y triggers. |
| `docs/database/README.md` | Detalles de la base de datos, endpoints y configuración. |

Las páginas usan rutas relativas dentro de `src`, por lo que hay que servir la carpeta del proyecto desde Apache. No abras los archivos directamente con `file://` ni uses Live Server: esos métodos no ejecutan los endpoints PHP.

## Iniciar en desarrollo con XAMPP

### 1. Colocar el proyecto bajo Apache

Apache sirve normalmente los proyectos desde `C:\xampp\htdocs`. Clona o coloca el repositorio en una carpeta ahí, por ejemplo:

```text
C:\xampp\htdocs\TuFeedUADY\
```

Si el repositorio queda fuera de `htdocs`, configura un `Alias` o un `DocumentRoot` de Apache para servirlo.

### 2. Iniciar servicios

En el panel de XAMPP, inicia **Apache** y **MySQL**. Se requiere PHP 8.1 o superior con las extensiones PDO MySQL y `mbstring` habilitadas.

### 3. Crear la base de datos

Entra a `http://localhost/phpmyadmin`, abre la pestaña **Importar** y selecciona `docs/database/schema.sql`.

> **Atención:** el script elimina y vuelve a crear la base `tufeeduady`. Úsalo solo en desarrollo y únicamente si puedes borrar los datos existentes.

La conexión usa por defecto `127.0.0.1:3306`, base `tufeeduady`, usuario `root` y contraseña vacía, como una instalación XAMPP típica. Si tu instalación tiene otros datos, configura `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER` y `DB_PASS` para PHP; los detalles están en `src/includes/conexion.php`.

### 4. Abrir la aplicación

Si la carpeta del proyecto se llama `TuFeedUADY`, abre:

```text
http://localhost/TuFeedUADY/
```

La página de la raíz redirige a `src/login.html`. Si usaste otro nombre de carpeta, reemplaza `TuFeedUADY` en la URL por ese nombre.

### 5. Probar el recorrido

1. En el login, selecciona **Regístrate** y crea una cuenta con un campus válido.
2. El registro inicia sesión automáticamente y lleva a la página principal.
3. Abre **Preferencias de feeds**, agrega un tema y una URL RSS `http` o `https`, y prueba buscar, editar y eliminar.
4. Usa **Cerrar sesión** y verifica que vuelva al login. Vuelve a iniciar sesión con correo o nickname.

Si una solicitud falla, los formularios muestran el mensaje JSON del servidor. Si no puede conectarse, confirma que Apache y MySQL estén activos, que el esquema se haya importado y que PHP pueda conectarse a la base de datos.

## Límites actuales

- El login y el registro usan los endpoints PHP y la sesión del servidor; no guardan la contraseña en `localStorage`.
- Los campos de feeds permiten mantener sus temas y URLs, pero no importan todavía los artículos RSS a la página principal.
- La carga de avatar y la exportación de feeds tienen endpoints disponibles, pero aún no están conectadas a controles de interfaz.
- La página principal conserva el carrusel y los contenedores visuales de noticias, sin noticias prefabricadas.
