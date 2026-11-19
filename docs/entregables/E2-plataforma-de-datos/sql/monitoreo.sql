-- Monitoreo de la base del consultorio (MySQL 8). Lo corre el administrador del servidor: lee information_schema,
-- performance_schema y sys, que los usuarios de seguridad.sql no ven. En Azure, lo mismo está en el portal
-- (Supervisión → Métricas y Registros del servidor); estas consultas sirven igual desde la consola.
--   mysql -h <servidor> -u <administrador> -p consultorio --table < docs/entregables/E2-plataforma-de-datos/sql/monitoreo.sql

-- M1. Tamaño de cada tabla: filas estimadas, datos e índices en MB (las 12 más grandes).
SELECT table_name AS tabla,
       table_rows AS filas_estimadas,
       ROUND(data_length / 1048576, 2) AS datos_mb,
       ROUND(index_length / 1048576, 2) AS indices_mb,
       ROUND((data_length + index_length) / 1048576, 2) AS total_mb
FROM information_schema.tables
WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'
ORDER BY data_length + index_length DESC
LIMIT 12;

-- M2. Tamaño de toda la base.
SELECT COUNT(*) AS tablas,
       ROUND(SUM(data_length) / 1048576, 2) AS datos_mb,
       ROUND(SUM(index_length) / 1048576, 2) AS indices_mb,
       ROUND(SUM(data_length + index_length) / 1048576, 2) AS total_mb
FROM information_schema.tables
WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE';

-- M3. Registro de consultas lentas: si está activo, desde cuántos segundos anota y dónde.
SELECT @@global.slow_query_log AS registro_activo,
       @@global.long_query_time AS segundos,
       @@global.log_queries_not_using_indexes AS sin_indice,
       @@global.log_output AS salida;

-- M4. Las 10 consultas que más tiempo sumaron desde que arrancó el servidor (performance_schema agrupa las
-- consultas iguales con distintos valores en un "digest").
SELECT LEFT(digest_text, 90) AS consulta,
       count_star AS veces,
       ROUND(sum_timer_wait / 1e12, 3) AS segundos_en_total,
       ROUND(avg_timer_wait / 1e9, 3) AS ms_promedio,
       sum_rows_examined AS filas_leidas,
       sum_no_index_used AS veces_sin_indice
FROM performance_schema.events_statements_summary_by_digest
WHERE schema_name = DATABASE() AND digest_text NOT LIKE 'EXPLAIN%'
ORDER BY sum_timer_wait DESC
LIMIT 10;

-- M5. Tablas leídas completas (sin índice) más veces: candidatas a un índice nuevo.
SELECT object_name AS tabla, rows_full_scanned AS filas_leidas_sin_indice, latency AS tiempo
FROM sys.schema_tables_with_full_table_scans
WHERE object_schema = DATABASE()
ORDER BY rows_full_scanned DESC
LIMIT 10;

-- M6. Índices que no se usaron desde que arrancó el servidor (no se borran sin mirar: puede que la consulta que
-- los usa todavía no haya corrido).
SELECT object_name AS tabla, index_name AS indice
FROM sys.schema_unused_indexes
WHERE object_schema = DATABASE()
ORDER BY object_name, index_name;

-- M7. Conexiones abiertas por usuario: la aplicación debe entrar como consultorio_app y nada más.
SELECT user AS usuario, host AS desde, COUNT(*) AS conexiones, SUM(command = 'Sleep') AS en_espera,
       MAX(time) AS segundos_la_mas_larga
FROM information_schema.processlist
WHERE db = DATABASE() OR user LIKE 'consultorio\_%'
GROUP BY user, host
ORDER BY conexiones DESC;

-- M8. Cambios a pagos hechos por fuera de la aplicación en los últimos 30 días (trigger de auditoría). Debe
-- estar vacío: cualquier fila aquí es un cambio directo en la base que hay que explicar.
SELECT changed_at AS cuando, database_user AS quien, event AS que, payment_id AS pago,
       LEFT(CAST(old_values AS CHAR), 80) AS antes, LEFT(CAST(new_values AS CHAR), 80) AS despues
FROM payment_audit_logs
WHERE changed_at >= fn_ahora_lima() - INTERVAL 30 DAY
ORDER BY changed_at DESC
LIMIT 20;
