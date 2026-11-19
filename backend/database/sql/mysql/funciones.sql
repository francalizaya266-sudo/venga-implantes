-- Funciones del consultorio (MySQL 8.0.13 o más nuevo).
-- Las crea la migración 2026_11_18_210454_create_reporting_views_functions_and_procedures y las vuelve a crear
-- `php artisan bd:objetos` (también después de restaurar una copia). Para correrlo a mano:
--   mysql consultorio < funciones.sql
-- Mismas reglas que app/Services/Billing y app/Models/Patient.php: lo anulado nunca cuenta.

DELIMITER $$

DROP FUNCTION IF EXISTS fn_ahora_lima$$
CREATE FUNCTION fn_ahora_lima() RETURNS DATETIME
    NOT DETERMINISTIC NO SQL
    COMMENT 'Fecha y hora de Lima (UTC-5, sin horario de verano), como las guarda la aplicacion (APP_TIMEZONE=America/Lima)'
BEGIN
    RETURN CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', '-05:00');
END$$

DROP FUNCTION IF EXISTS fn_hoy_lima$$
CREATE FUNCTION fn_hoy_lima() RETURNS DATE
    NOT DETERMINISTIC NO SQL
    COMMENT 'El dia de hoy en Lima'
BEGIN
    RETURN DATE(fn_ahora_lima());
END$$

DROP FUNCTION IF EXISTS fn_saldo_paciente$$
CREATE FUNCTION fn_saldo_paciente(p_paciente_id BIGINT UNSIGNED) RETURNS DECIMAL(12, 2)
    NOT DETERMINISTIC READS SQL DATA
    COMMENT 'Lo que debe el paciente (positivo) o su saldo a favor (negativo), como Patient::balance()'
BEGIN
    DECLARE v_cargado DECIMAL(12, 2);
    DECLARE v_pagado DECIMAL(12, 2);

    SELECT COALESCE(SUM(amount - discount), 0) INTO v_cargado
    FROM charges
    WHERE patient_id = p_paciente_id AND voided_at IS NULL;

    SELECT COALESCE(SUM(amount), 0) INTO v_pagado
    FROM payments
    WHERE patient_id = p_paciente_id AND voided_at IS NULL;

    RETURN v_cargado - v_pagado;
END$$

-- Los nombres que ve el consultorio, iguales a los de los enum de PHP (las pruebas comparan cada caso).

DROP FUNCTION IF EXISTS fn_estado_cita$$
CREATE FUNCTION fn_estado_cita(p_estado VARCHAR(20)) RETURNS VARCHAR(30)
    DETERMINISTIC NO SQL
    COMMENT 'Como AppointmentStatus::getLabel()'
BEGIN
    RETURN CASE p_estado
        WHEN 'pending' THEN 'Pendiente'
        WHEN 'confirmed' THEN 'Confirmada'
        WHEN 'checked_in' THEN 'En sala'
        WHEN 'in_progress' THEN 'En atención'
        WHEN 'completed' THEN 'Atendida'
        WHEN 'no_show' THEN 'No asistió'
        WHEN 'cancelled' THEN 'Cancelada'
        ELSE p_estado
    END;
END$$

DROP FUNCTION IF EXISTS fn_medio_pago$$
CREATE FUNCTION fn_medio_pago(p_medio VARCHAR(20)) RETURNS VARCHAR(30)
    DETERMINISTIC NO SQL
    COMMENT 'Como PaymentMethod::getLabel()'
BEGIN
    RETURN CASE p_medio
        WHEN 'cash' THEN 'Efectivo'
        WHEN 'card' THEN 'Tarjeta'
        WHEN 'yape' THEN 'Yape'
        WHEN 'plin' THEN 'Plin'
        WHEN 'transfer' THEN 'Transferencia'
        WHEN 'other' THEN 'Otro'
        ELSE p_medio
    END;
END$$

DROP FUNCTION IF EXISTS fn_categoria_material$$
CREATE FUNCTION fn_categoria_material(p_categoria VARCHAR(30)) RETURNS VARCHAR(40)
    DETERMINISTIC NO SQL
    COMMENT 'Como SupplyCategory::getLabel()'
BEGIN
    RETURN CASE p_categoria
        WHEN 'anesthesia' THEN 'Anestésicos'
        WHEN 'restorative' THEN 'Resinas y restauración'
        WHEN 'endodontics' THEN 'Endodoncia'
        WHEN 'prevention' THEN 'Prevención y limpieza'
        WHEN 'surgery' THEN 'Cirugía'
        WHEN 'implants' THEN 'Implantes'
        WHEN 'orthodontics' THEN 'Ortodoncia'
        WHEN 'biosafety' THEN 'Bioseguridad'
        WHEN 'laboratory' THEN 'Laboratorio'
        WHEN 'other' THEN 'Otros'
        ELSE p_categoria
    END;
END$$

DELIMITER ;
