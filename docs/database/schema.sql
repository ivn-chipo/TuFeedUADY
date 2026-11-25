-- =====================================================================
-- TuFeedUADY - Esquema de la base de datos (MySQL 8.0+)
-- Importar con:  mysql -u root -p < docs/database/schema.sql
-- o desde phpMyAdmin > Importar.
--
-- ATENCIÓN: el script BORRA y vuelve a crear la base de datos
-- `tufeeduady`, solo usarlo en desarrollo.
-- =====================================================================

-- Las notas de "IF EXISTS" / "IF NOT EXISTS" se silencian para que la
-- importación no reporte advertencias.
SET @sql_notes_previo = @@SESSION.sql_notes;
SET SESSION sql_notes = 0;

DROP DATABASE IF EXISTS tufeeduady;
CREATE DATABASE tufeeduady
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE tufeeduady;

-- ---------------------------------------------------------------------
-- 1. TABLAS
-- ---------------------------------------------------------------------

-- Catálogo de campus de la UADY
CREATE TABLE campus (
    id      TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    clave   VARCHAR(10)      NOT NULL,
    nombre  VARCHAR(120)     NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_campus_clave (clave)
) ENGINE = InnoDB;

CREATE TABLE usuarios (
    id             INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    nickname       VARCHAR(30)      NOT NULL,
    correo         VARCHAR(120)     NOT NULL,
    password_hash  VARCHAR(255)     NOT NULL,
    foto_perfil    VARCHAR(255)     NULL DEFAULT NULL,
    campus_id      TINYINT UNSIGNED NOT NULL,
    creado_en      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_usuarios_nickname (nickname),
    UNIQUE KEY uq_usuarios_correo (correo),
    KEY idx_usuarios_campus (campus_id),
    CONSTRAINT fk_usuarios_campus
        FOREIGN KEY (campus_id) REFERENCES campus (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE = InnoDB;

CREATE TABLE preferencias_feeds (
    id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    usuario_id  INT UNSIGNED  NOT NULL,
    tema        VARCHAR(60)   NOT NULL,
    url_rss     VARCHAR(500)  NOT NULL,
    creado_en   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_preferencias_usuario_url (usuario_id, url_rss),
    KEY idx_preferencias_tema (tema),
    CONSTRAINT fk_preferencias_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE = InnoDB;

-- Bitácora que llenan los disparadores (sin llaves foráneas para
-- conservar el registro aunque el usuario ya no exista)
CREATE TABLE log_auditoria (
    id           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    tabla        VARCHAR(30)   NOT NULL,
    accion       ENUM('INSERT', 'DELETE') NOT NULL,
    registro_id  INT UNSIGNED  NOT NULL,
    usuario_id   INT UNSIGNED  NOT NULL,
    detalle      VARCHAR(255)  NULL DEFAULT NULL,
    fecha        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_log_tabla_fecha (tabla, fecha)
) ENGINE = InnoDB;

-- ---------------------------------------------------------------------
-- 2. VISTA
-- ---------------------------------------------------------------------

-- Usuario junto con el nombre de su campus y cuántos feeds tiene.
-- No expone password_hash.
CREATE VIEW vista_usuarios_campus AS
SELECT
    u.id,
    u.nickname,
    u.correo,
    u.foto_perfil,
    c.clave  AS campus_clave,
    c.nombre AS campus_nombre,
    COUNT(p.id) AS total_feeds,
    u.creado_en
FROM usuarios u
INNER JOIN campus c ON c.id = u.campus_id
LEFT JOIN preferencias_feeds p ON p.usuario_id = u.id
GROUP BY u.id, u.nickname, u.correo, u.foto_perfil, c.clave, c.nombre, u.creado_en;

-- ---------------------------------------------------------------------
-- 3. PROCEDIMIENTOS ALMACENADOS
-- ---------------------------------------------------------------------

DELIMITER $$

-- Registra un usuario a partir de la clave de su campus.
-- La contraseña llega ya encriptada con password_hash() desde PHP.
CREATE PROCEDURE sp_registrar_usuario (
    IN  p_nickname       VARCHAR(30),
    IN  p_correo         VARCHAR(120),
    IN  p_password_hash  VARCHAR(255),
    IN  p_campus_clave   VARCHAR(10),
    OUT p_usuario_id     INT UNSIGNED
)
BEGIN
    DECLARE v_campus_id TINYINT UNSIGNED DEFAULT NULL;

    SELECT id INTO v_campus_id
    FROM campus
    WHERE clave = p_campus_clave
    LIMIT 1;

    IF v_campus_id IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'El campus indicado no existe';
    END IF;

    INSERT INTO usuarios (nickname, correo, password_hash, campus_id)
    VALUES (p_nickname, p_correo, p_password_hash, v_campus_id);

    SET p_usuario_id = LAST_INSERT_ID();
END$$

-- Busca entre los feeds de un usuario por palabra clave (tema o URL).
-- Si la palabra viene vacía devuelve todos sus feeds.
CREATE PROCEDURE sp_buscar_feeds (
    IN p_usuario_id  INT UNSIGNED,
    IN p_palabra     VARCHAR(100)
)
BEGIN
    SELECT id, tema, url_rss, creado_en
    FROM preferencias_feeds
    WHERE usuario_id = p_usuario_id
      AND (
            p_palabra IS NULL
         OR p_palabra = ''
         OR tema    LIKE CONCAT('%', p_palabra, '%')
         OR url_rss LIKE CONCAT('%', p_palabra, '%')
      )
    ORDER BY tema, creado_en DESC;
END$$

-- ---------------------------------------------------------------------
-- 4. DISPARADORES (auditoría)
-- MySQL solo permite un evento por disparador, por eso hay uno para
-- INSERT y otro para DELETE en cada tabla.
-- ---------------------------------------------------------------------

CREATE TRIGGER tr_auditoria_usuarios_insert
AFTER INSERT ON usuarios
FOR EACH ROW
BEGIN
    INSERT INTO log_auditoria (tabla, accion, registro_id, usuario_id, detalle)
    VALUES ('usuarios', 'INSERT', NEW.id, NEW.id,
            CONCAT('Registro del usuario ', NEW.nickname));
END$$

CREATE TRIGGER tr_auditoria_usuarios_delete
AFTER DELETE ON usuarios
FOR EACH ROW
BEGIN
    INSERT INTO log_auditoria (tabla, accion, registro_id, usuario_id, detalle)
    VALUES ('usuarios', 'DELETE', OLD.id, OLD.id,
            CONCAT('Baja del usuario ', OLD.nickname));
END$$

CREATE TRIGGER tr_auditoria_preferencias_insert
AFTER INSERT ON preferencias_feeds
FOR EACH ROW
BEGIN
    INSERT INTO log_auditoria (tabla, accion, registro_id, usuario_id, detalle)
    VALUES ('preferencias_feeds', 'INSERT', NEW.id, NEW.usuario_id,
            LEFT(CONCAT('Alta del feed ', NEW.tema, ': ', NEW.url_rss), 255));
END$$

-- Nota: MySQL no ejecuta disparadores en los borrados hechos por
-- ON DELETE CASCADE, así que este solo registra las bajas directas.
CREATE TRIGGER tr_auditoria_preferencias_delete
AFTER DELETE ON preferencias_feeds
FOR EACH ROW
BEGIN
    INSERT INTO log_auditoria (tabla, accion, registro_id, usuario_id, detalle)
    VALUES ('preferencias_feeds', 'DELETE', OLD.id, OLD.usuario_id,
            LEFT(CONCAT('Baja del feed ', OLD.tema, ': ', OLD.url_rss), 255));
END$$

DELIMITER ;

-- ---------------------------------------------------------------------
-- 5. DATOS INICIALES
-- Las claves coinciden con los valores del formulario de registro
-- (src/preferences.html).
-- ---------------------------------------------------------------------

INSERT INTO campus (clave, nombre) VALUES
    ('CCEI',  'Campus de Ciencias Exactas e Ingenierías'),
    ('CAHAD', 'Campus de Arquitectura, Hábitat, Arte y Diseño'),
    ('CCSE',  'Campus de Ciencias de la Salud'),
    ('Otros', 'Otro campus UADY');

SET SESSION sql_notes = @sql_notes_previo;
