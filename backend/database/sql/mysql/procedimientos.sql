-- Procedimientos almacenados del consultorio (MySQL 8). Necesitan funciones.sql y vistas.sql.
-- Los crea la migración 2026_11_18_210454_create_reporting_views_functions_and_procedures y los vuelve a crear
-- `php artisan bd:objetos`. Mismas reglas que app/Services/Billing/CashClosing.php y
-- app/Services/Reports/EarningsReport.php: lo anulado no suma.

DELIMITER $$

-- Cierre de caja de un día: CALL sp_cierre_caja('2026-11-16', NULL);
-- Con p_recibido_por (id de users), solo lo que cobró y gastó esa persona, como el filtro "Persona" del panel.
-- Devuelve dos resultados:
--   1. Resumen: lo cobrado, el efectivo que debería haber, lo que faltó cobrar y, si ya se cerró, lo contado.
--   2. Por medio de pago: el efectivo siempre (es lo que se cuenta) y los demás medios con algún pago.
DROP PROCEDURE IF EXISTS sp_cierre_caja$$
CREATE PROCEDURE sp_cierre_caja(IN p_fecha DATE, IN p_recibido_por BIGINT UNSIGNED)
    READS SQL DATA
    COMMENT 'Cierre de caja del dia, como CashClosing'
BEGIN
    DECLARE v_desde DATETIME;
    DECLARE v_hasta DATETIME;

    IF p_fecha IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Falta la fecha del cierre';
    END IF;

    SET v_desde = CAST(p_fecha AS DATETIME);
    SET v_hasta = v_desde + INTERVAL 1 DAY;

    SELECT
        p_fecha AS fecha,
        pg.pagos,
        pg.cobrado,
        pg.efectivo,
        gs.gastos,
        gs.gastos_en_efectivo,
        pg.efectivo - gs.gastos_en_efectivo AS efectivo_en_caja,
        pe.pendiente,
        pg.cobrado + pe.pendiente AS esperado,
        an.anulados,
        an.monto_anulado,
        dc.id IS NOT NULL AS cerrado,
        dc.opening_cash AS fondo,
        dc.expected_cash AS efectivo_esperado_al_cerrar,
        dc.counted_cash AS efectivo_contado,
        dc.counted_cash - dc.expected_cash AS diferencia
    FROM (
        SELECT
            COUNT(*) AS pagos,
            COALESCE(SUM(amount), 0) AS cobrado,
            COALESCE(SUM(CASE WHEN method = 'cash' THEN amount ELSE 0 END), 0) AS efectivo
        FROM payments
        WHERE voided_at IS NULL
            AND paid_at >= v_desde AND paid_at < v_hasta
            AND (p_recibido_por IS NULL OR received_by_user_id = p_recibido_por)
    ) pg
    CROSS JOIN (
        SELECT
            COALESCE(SUM(amount), 0) AS gastos,
            COALESCE(SUM(CASE WHEN method = 'cash' THEN amount ELSE 0 END), 0) AS gastos_en_efectivo
        FROM expenses
        WHERE voided_at IS NULL
            AND spent_at >= v_desde AND spent_at < v_hasta
            AND (p_recibido_por IS NULL OR paid_by_user_id = p_recibido_por)
    ) gs
    CROSS JOIN (
        -- Lo que se cargó ese día y no se pagó (CashClosing::pending): los pagos cubren primero lo más antiguo,
        -- así que de ese día falta lo que debe hoy menos lo cargado después, hasta lo cargado ese día.
        SELECT COALESCE(SUM(falta), 0) AS pendiente
        FROM (
            SELECT LEAST(dia.neto, GREATEST(0, dia.saldo - COALESCE(despues.neto, 0))) AS falta
            FROM (
                SELECT c.patient_id, SUM(c.amount - c.discount) AS neto, fn_saldo_paciente(c.patient_id) AS saldo
                FROM charges c
                JOIN patients pa ON pa.id = c.patient_id AND pa.deleted_at IS NULL
                WHERE c.voided_at IS NULL AND c.charged_at >= v_desde AND c.charged_at < v_hasta
                GROUP BY c.patient_id
            ) dia
            LEFT JOIN (
                SELECT patient_id, SUM(amount - discount) AS neto
                FROM charges
                WHERE voided_at IS NULL AND charged_at >= v_hasta
                GROUP BY patient_id
            ) despues ON despues.patient_id = dia.patient_id
            WHERE dia.saldo > 0
        ) f
        WHERE falta > 0
    ) pe
    CROSS JOIN (
        SELECT COUNT(*) AS anulados, COALESCE(SUM(amount), 0) AS monto_anulado
        FROM payments
        WHERE voided_at IS NOT NULL
            AND paid_at >= v_desde AND paid_at < v_hasta
            AND (p_recibido_por IS NULL OR received_by_user_id = p_recibido_por)
    ) an
    LEFT JOIN daily_closings dc ON dc.date = p_fecha;

    SELECT
        m.medio,
        fn_medio_pago(m.medio) AS medio_de_pago,
        COUNT(p.id) AS pagos,
        COALESCE(SUM(p.amount), 0) AS cobrado
    FROM (
        SELECT 'cash' AS medio, 1 AS orden
        UNION ALL SELECT 'card', 2
        UNION ALL SELECT 'yape', 3
        UNION ALL SELECT 'plin', 4
        UNION ALL SELECT 'transfer', 5
        UNION ALL SELECT 'other', 6
    ) m
    LEFT JOIN payments p ON p.method = m.medio
        AND p.voided_at IS NULL
        AND p.paid_at >= v_desde AND p.paid_at < v_hasta
        AND (p_recibido_por IS NULL OR p.received_by_user_id = p_recibido_por)
    GROUP BY m.medio, m.orden
    HAVING m.medio = 'cash' OR COUNT(p.id) > 0
    ORDER BY m.orden;
END$$

-- Ingresos de un mes: CALL sp_reporte_mensual_ingresos(2026, 10);
-- Devuelve tres resultados, como la página Ingresos (EarningsReport) con el mes elegido:
--   1. Resumen: cobrado, realizado, gastos, materiales, lo que queda y lo que deben hoy todos los pacientes.
--   2. Por doctor: lo realizado y lo cobrado de cada uno ("Sin doctor asignado" si no hay doctor).
--   3. Por medio de pago: lo cobrado, cuántos pagos y qué parte del total.
DROP PROCEDURE IF EXISTS sp_reporte_mensual_ingresos$$
CREATE PROCEDURE sp_reporte_mensual_ingresos(IN p_anio SMALLINT, IN p_mes TINYINT)
    READS SQL DATA
    COMMENT 'Ingresos de un mes, como EarningsReport'
BEGIN
    DECLARE v_desde DATETIME;
    DECLARE v_hasta DATETIME;
    DECLARE v_cobrado DECIMAL(12, 2);

    IF p_anio IS NULL OR p_mes IS NULL OR p_mes NOT BETWEEN 1 AND 12 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El mes debe estar entre 1 y 12';
    END IF;

    SET v_desde = CAST(MAKEDATE(p_anio, 1) + INTERVAL (p_mes - 1) MONTH AS DATETIME);
    SET v_hasta = v_desde + INTERVAL 1 MONTH;

    SELECT COALESCE(SUM(amount), 0) INTO v_cobrado
    FROM payments
    WHERE voided_at IS NULL AND paid_at >= v_desde AND paid_at < v_hasta;

    SELECT
        DATE_FORMAT(v_desde, '%Y-%m') AS mes,
        v_cobrado AS cobrado,
        pg.pagos,
        rz.realizado,
        gs.gastos,
        mt.materiales,
        v_cobrado - gs.gastos - mt.materiales AS queda,
        sd.por_cobrar_hoy
    FROM (
        SELECT COUNT(*) AS pagos
        FROM payments
        WHERE voided_at IS NULL AND paid_at >= v_desde AND paid_at < v_hasta
    ) pg
    CROSS JOIN (
        SELECT COALESCE(SUM(amount - discount), 0) AS realizado
        FROM charges
        WHERE voided_at IS NULL AND charged_at >= v_desde AND charged_at < v_hasta
    ) rz
    CROSS JOIN (
        SELECT COALESCE(SUM(amount), 0) AS gastos
        FROM expenses
        WHERE voided_at IS NULL AND spent_at >= v_desde AND spent_at < v_hasta
    ) gs
    CROSS JOIN (
        SELECT CAST(GREATEST(0, COALESCE(SUM(-quantity * COALESCE(unit_cost, 0)), 0)) AS DECIMAL(12, 2)) AS materiales
        FROM stock_movements
        WHERE type IN ('use', 'return', 'write_off') AND created_at >= v_desde AND created_at < v_hasta
    ) mt
    CROSS JOIN (
        SELECT COALESCE(SUM(saldo), 0) AS por_cobrar_hoy
        FROM v_saldo_paciente
        WHERE saldo > 0
    ) sd;

    SELECT
        COALESCE(d.name, 'Sin doctor asignado') AS doctor,
        COALESCE(rz.total, 0) AS realizado,
        COALESCE(cb.total, 0) AS cobrado
    FROM (
        SELECT doctor_id FROM charges
        WHERE voided_at IS NULL AND charged_at >= v_desde AND charged_at < v_hasta
        UNION
        SELECT doctor_id FROM payments
        WHERE voided_at IS NULL AND paid_at >= v_desde AND paid_at < v_hasta
    ) ids
    LEFT JOIN (
        SELECT doctor_id, SUM(amount - discount) AS total
        FROM charges
        WHERE voided_at IS NULL AND charged_at >= v_desde AND charged_at < v_hasta
        GROUP BY doctor_id
    ) rz ON rz.doctor_id <=> ids.doctor_id
    LEFT JOIN (
        SELECT doctor_id, SUM(amount) AS total
        FROM payments
        WHERE voided_at IS NULL AND paid_at >= v_desde AND paid_at < v_hasta
        GROUP BY doctor_id
    ) cb ON cb.doctor_id <=> ids.doctor_id
    LEFT JOIN doctors d ON d.id = ids.doctor_id AND d.deleted_at IS NULL
    ORDER BY realizado DESC, cobrado DESC;

    SELECT
        method AS medio,
        fn_medio_pago(method) AS medio_de_pago,
        SUM(amount) AS cobrado,
        COUNT(*) AS pagos,
        CASE WHEN v_cobrado > 0 THEN ROUND(SUM(amount) / v_cobrado * 100, 1) ELSE 0 END AS porcentaje
    FROM payments
    WHERE voided_at IS NULL AND paid_at >= v_desde AND paid_at < v_hasta
    GROUP BY method
    ORDER BY cobrado DESC;
END$$

DELIMITER ;
