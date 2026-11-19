-- Usuarios de la base de datos del consultorio, cada uno con lo mínimo que necesita (MySQL 8.0.20 o más nuevo).
--
-- Lo corre el administrador del servidor (en Azure, el administrador del servidor flexible de MySQL). La primera vez
-- se corre dos veces: antes de migrar, para crear consultorio_despliegue (que es quien migra), y después de migrar,
-- para dar a reportes las vistas que crean las migraciones. Se puede volver a correr cuando se quiera: cambia las
-- claves y vuelve a dar los mismos permisos. La base se llama `consultorio` (.env.example); si se llama distinto,
-- cambia `consultorio` en los GRANT.
--
-- Las claves NO van en este archivo (el repositorio es público). Se pasan al correrlo:
--   ( echo "SET @clave_app='…', @clave_despliegue='…', @clave_reportes='…', @clave_respaldo='…';"
--     cat database/sql/seguridad.sql ) | mysql -h <servidor> -u <administrador> -p consultorio
-- Si falta una clave, su CREATE USER queda como "PREPARE … FROM NULL", MySQL da error y la consola se detiene ahí.
--
-- Quién usa cada usuario:
--   consultorio_app         La aplicación (Laravel) en el día a día: DB_USERNAME en el .env de vm-app.
--   consultorio_despliegue  Las migraciones al publicar una versión, `respaldo:restaurar` y `bd:objetos`.
--   consultorio_reportes    Reportes de solo lectura (Excel, Power BI, la consola): vistas y procedimientos.
--   consultorio_respaldo    Copias con mysqldump desde fuera, además de la copia diaria de la aplicación.

-- 1. La aplicación: lee y escribe datos, nada más. No puede crear, cambiar ni borrar tablas, ni crear usuarios,
--    así que un fallo en la aplicación no puede llevarse la estructura de la base.
SET @sql = CONCAT('CREATE USER IF NOT EXISTS ''consultorio_app''@''%'' IDENTIFIED BY ', QUOTE(@clave_app));
PREPARE sentencia FROM @sql;
EXECUTE sentencia;
DEALLOCATE PREPARE sentencia;
SET @sql = CONCAT('ALTER USER ''consultorio_app''@''%'' IDENTIFIED BY ', QUOTE(@clave_app));
PREPARE sentencia FROM @sql;
EXECUTE sentencia;
DEALLOCATE PREPARE sentencia;
REVOKE ALL PRIVILEGES, GRANT OPTION FROM 'consultorio_app'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE ON `consultorio`.* TO 'consultorio_app'@'%';

-- 2. Despliegue: cambia la estructura (migraciones), restaura copias y crea vistas, funciones, procedimientos y
--    triggers. No puede crear usuarios ni dar permisos (sin GRANT OPTION), ni tocar otras bases del servidor.
--    Para crear funciones y triggers con el registro binario activo, el servidor necesita
--    log_bin_trust_function_creators = ON (en Azure: Parámetros del servidor).
SET @sql = CONCAT('CREATE USER IF NOT EXISTS ''consultorio_despliegue''@''%'' IDENTIFIED BY ', QUOTE(@clave_despliegue));
PREPARE sentencia FROM @sql;
EXECUTE sentencia;
DEALLOCATE PREPARE sentencia;
SET @sql = CONCAT('ALTER USER ''consultorio_despliegue''@''%'' IDENTIFIED BY ', QUOTE(@clave_despliegue));
PREPARE sentencia FROM @sql;
EXECUTE sentencia;
DEALLOCATE PREPARE sentencia;
REVOKE ALL PRIVILEGES, GRANT OPTION FROM 'consultorio_despliegue'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, DROP, INDEX, REFERENCES, CREATE VIEW, SHOW VIEW,
    CREATE ROUTINE, ALTER ROUTINE, EXECUTE, TRIGGER, LOCK TABLES, CREATE TEMPORARY TABLES
    ON `consultorio`.* TO 'consultorio_despliegue'@'%';

-- 3. Reportes: solo las vistas y los procedimientos de reportes. No ve ninguna tabla, así que no llega a la
--    historia clínica, los antecedentes ni las recetas (datos de salud, sensibles según la Ley N.º 29733). Las
--    vistas y los procedimientos corren con los permisos de quien los creó (SQL SECURITY DEFINER) y solo leen.
--    Tras 5 claves equivocadas seguidas, la cuenta se bloquea un día.
SET @sql = CONCAT('CREATE USER IF NOT EXISTS ''consultorio_reportes''@''%'' IDENTIFIED BY ', QUOTE(@clave_reportes),
    ' FAILED_LOGIN_ATTEMPTS 5 PASSWORD_LOCK_TIME 1');
PREPARE sentencia FROM @sql;
EXECUTE sentencia;
DEALLOCATE PREPARE sentencia;
SET @sql = CONCAT('ALTER USER ''consultorio_reportes''@''%'' IDENTIFIED BY ', QUOTE(@clave_reportes),
    ' FAILED_LOGIN_ATTEMPTS 5 PASSWORD_LOCK_TIME 1');
PREPARE sentencia FROM @sql;
EXECUTE sentencia;
DEALLOCATE PREPARE sentencia;
REVOKE ALL PRIVILEGES, GRANT OPTION FROM 'consultorio_reportes'@'%';
-- Las vistas las crean las migraciones, que corre consultorio_despliegue: la primera vez este archivo se corre
-- antes de migrar (para crear ese usuario) y todavía no hay vistas. MySQL no deja dar permisos sobre una vista que
-- no existe, así que se dan solo sobre las que ya están; después de la primera migración se corre otra vez.
DELIMITER $$
DROP PROCEDURE IF EXISTS tmp_permisos_reportes$$
CREATE PROCEDURE tmp_permisos_reportes()
BEGIN
    DECLARE v_vistas VARCHAR(200) DEFAULT 'v_saldo_paciente,v_agenda_del_dia,v_cargos_pendientes,v_deuda_vencida,v_consumo_materiales';
    DECLARE v_vista VARCHAR(64);
    DECLARE v_faltan VARCHAR(200) DEFAULT '';

    WHILE v_vistas <> '' DO
        SET v_vista = SUBSTRING_INDEX(v_vistas, ',', 1);
        SET v_vistas = IF(LOCATE(',', v_vistas) > 0, SUBSTRING(v_vistas, LOCATE(',', v_vistas) + 1), '');

        IF EXISTS (SELECT 1 FROM information_schema.views WHERE table_schema = 'consultorio' AND table_name = v_vista) THEN
            SET @sql = CONCAT('GRANT SELECT ON `consultorio`.`', v_vista, '` TO ''consultorio_reportes''@''%''');
            PREPARE sentencia FROM @sql;
            EXECUTE sentencia;
            DEALLOCATE PREPARE sentencia;
        ELSE
            SET v_faltan = CONCAT_WS(', ', NULLIF(v_faltan, ''), v_vista);
        END IF;
    END WHILE;

    SELECT IF(v_faltan = '', 'Permisos de reportes dados sobre las 5 vistas.',
        CONCAT('Faltan las vistas ', v_faltan, ': corre las migraciones y vuelve a correr seguridad.sql.')) AS vistas_de_reportes;
END$$
DELIMITER ;
CALL tmp_permisos_reportes();
DROP PROCEDURE tmp_permisos_reportes;
-- EXECUTE va sobre toda la base y no sobre cada procedimiento: `bd:objetos` (y cada restauración) hace DROP y
-- CREATE de las funciones y procedimientos, y MySQL borra los permisos dados sobre un procedimiento al borrarlo.
-- Todas las funciones y procedimientos de database/sql/mysql solo leen; uno que escriba obligaría a revisar esto.
GRANT EXECUTE ON `consultorio`.* TO 'consultorio_reportes'@'%';

-- 4. Respaldo: lee todo para copiarlo con
--      mysqldump --single-transaction --no-tablespaces --routines --triggers --set-gtid-purged=OFF consultorio
--    SELECT para los datos, SHOW VIEW para las vistas, TRIGGER para los triggers y SHOW_ROUTINE para leer el código
--    de las funciones y procedimientos (sin él, mysqldump se detiene: "insufficient privileges to SHOW CREATE
--    FUNCTION"). SHOW_ROUTINE es de todo el servidor, pero solo deja leer código, no datos. No puede escribir nada.
--    Si el servidor no deja dar SHOW_ROUTINE, se copia sin --routines: las funciones y procedimientos están en el
--    repositorio y `php artisan bd:objetos` los vuelve a crear.
SET @sql = CONCAT('CREATE USER IF NOT EXISTS ''consultorio_respaldo''@''%'' IDENTIFIED BY ', QUOTE(@clave_respaldo));
PREPARE sentencia FROM @sql;
EXECUTE sentencia;
DEALLOCATE PREPARE sentencia;
SET @sql = CONCAT('ALTER USER ''consultorio_respaldo''@''%'' IDENTIFIED BY ', QUOTE(@clave_respaldo));
PREPARE sentencia FROM @sql;
EXECUTE sentencia;
DEALLOCATE PREPARE sentencia;
REVOKE ALL PRIVILEGES, GRANT OPTION FROM 'consultorio_respaldo'@'%';
GRANT SELECT, SHOW VIEW, TRIGGER ON `consultorio`.* TO 'consultorio_respaldo'@'%';
GRANT SHOW_ROUTINE ON *.* TO 'consultorio_respaldo'@'%';

SET @sql = NULL, @clave_app = NULL, @clave_despliegue = NULL, @clave_reportes = NULL, @clave_respaldo = NULL;

SHOW GRANTS FOR 'consultorio_app'@'%';
SHOW GRANTS FOR 'consultorio_despliegue'@'%';
SHOW GRANTS FOR 'consultorio_reportes'@'%';
SHOW GRANTS FOR 'consultorio_respaldo'@'%';
