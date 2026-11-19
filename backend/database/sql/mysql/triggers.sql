-- Auditoría de pagos en la base de datos (MySQL 8). Necesita funciones.sql y la tabla payment_audit_logs.
-- Los crea la migración 2026_11_18_210454_create_reporting_views_functions_and_procedures y los vuelve a crear
-- `php artisan bd:objetos` (hace falta después de restaurar una copia: DROP TABLE payments se lleva los triggers).
--
-- La aplicación ya deja en audit_logs cada pago que se crea o se anula (trait Auditable), y nunca cambia el
-- monto, el medio, la fecha ni el paciente de un pago, ni lo borra (PaymentPolicy::delete devuelve false).
-- Estos triggers anotan justo eso que la aplicación no hace: si aparece aquí, alguien tocó la base por fuera
-- (phpMyAdmin, la consola, un script). Por eso no repiten lo que ya guarda audit_logs.
-- Quién: USER() da el usuario y el equipo de la conexión. CURRENT_USER() no sirve aquí: dentro de un trigger
-- devuelve al dueño del trigger, no a quien hizo el cambio.

DELIMITER $$

DROP TRIGGER IF EXISTS trg_pagos_auditar_cambio$$
CREATE TRIGGER trg_pagos_auditar_cambio
AFTER UPDATE ON payments
FOR EACH ROW
BEGIN
    DECLARE v_antes JSON DEFAULT JSON_OBJECT();
    DECLARE v_despues JSON DEFAULT JSON_OBJECT();

    IF NOT (OLD.patient_id <=> NEW.patient_id) THEN
        SET v_antes = JSON_SET(v_antes, '$.patient_id', OLD.patient_id),
            v_despues = JSON_SET(v_despues, '$.patient_id', NEW.patient_id);
    END IF;
    IF NOT (OLD.appointment_id <=> NEW.appointment_id) THEN
        SET v_antes = JSON_SET(v_antes, '$.appointment_id', OLD.appointment_id),
            v_despues = JSON_SET(v_despues, '$.appointment_id', NEW.appointment_id);
    END IF;
    IF NOT (OLD.doctor_id <=> NEW.doctor_id) THEN
        SET v_antes = JSON_SET(v_antes, '$.doctor_id', OLD.doctor_id),
            v_despues = JSON_SET(v_despues, '$.doctor_id', NEW.doctor_id);
    END IF;
    IF NOT (OLD.treatment_id <=> NEW.treatment_id) THEN
        SET v_antes = JSON_SET(v_antes, '$.treatment_id', OLD.treatment_id),
            v_despues = JSON_SET(v_despues, '$.treatment_id', NEW.treatment_id);
    END IF;
    IF NOT (OLD.amount <=> NEW.amount) THEN
        SET v_antes = JSON_SET(v_antes, '$.amount', OLD.amount),
            v_despues = JSON_SET(v_despues, '$.amount', NEW.amount);
    END IF;
    IF NOT (OLD.method <=> NEW.method) THEN
        SET v_antes = JSON_SET(v_antes, '$.method', OLD.method),
            v_despues = JSON_SET(v_despues, '$.method', NEW.method);
    END IF;
    IF NOT (OLD.reference <=> NEW.reference) THEN
        SET v_antes = JSON_SET(v_antes, '$.reference', OLD.reference),
            v_despues = JSON_SET(v_despues, '$.reference', NEW.reference);
    END IF;
    IF NOT (OLD.paid_at <=> NEW.paid_at) THEN
        SET v_antes = JSON_SET(v_antes, '$.paid_at', CAST(OLD.paid_at AS CHAR)),
            v_despues = JSON_SET(v_despues, '$.paid_at', CAST(NEW.paid_at AS CHAR));
    END IF;
    IF NOT (OLD.received_by_user_id <=> NEW.received_by_user_id) THEN
        SET v_antes = JSON_SET(v_antes, '$.received_by_user_id', OLD.received_by_user_id),
            v_despues = JSON_SET(v_despues, '$.received_by_user_id', NEW.received_by_user_id);
    END IF;
    IF NOT (OLD.notes <=> NEW.notes) THEN
        SET v_antes = JSON_SET(v_antes, '$.notes', OLD.notes),
            v_despues = JSON_SET(v_despues, '$.notes', NEW.notes);
    END IF;

    -- Anular un pago que estaba vigente es lo único que hace la aplicación (y ya queda en audit_logs).
    -- Reabrir uno anulado, o cambiar quién o por qué se anuló, no lo hace nunca.
    IF OLD.voided_at IS NOT NULL THEN
        IF NOT (OLD.voided_at <=> NEW.voided_at) THEN
            SET v_antes = JSON_SET(v_antes, '$.voided_at', CAST(OLD.voided_at AS CHAR)),
                v_despues = JSON_SET(v_despues, '$.voided_at', CAST(NEW.voided_at AS CHAR));
        END IF;
        IF NOT (OLD.voided_by_user_id <=> NEW.voided_by_user_id) THEN
            SET v_antes = JSON_SET(v_antes, '$.voided_by_user_id', OLD.voided_by_user_id),
                v_despues = JSON_SET(v_despues, '$.voided_by_user_id', NEW.voided_by_user_id);
        END IF;
        IF NOT (OLD.void_reason <=> NEW.void_reason) THEN
            SET v_antes = JSON_SET(v_antes, '$.void_reason', OLD.void_reason),
                v_despues = JSON_SET(v_despues, '$.void_reason', NEW.void_reason);
        END IF;
    END IF;

    IF JSON_LENGTH(v_antes) > 0 THEN
        INSERT INTO payment_audit_logs (payment_id, event, old_values, new_values, database_user, changed_at)
        VALUES (OLD.id, 'updated', v_antes, v_despues, USER(), fn_ahora_lima());
    END IF;
END$$

DROP TRIGGER IF EXISTS trg_pagos_auditar_borrado$$
CREATE TRIGGER trg_pagos_auditar_borrado
AFTER DELETE ON payments
FOR EACH ROW
BEGIN
    INSERT INTO payment_audit_logs (payment_id, event, old_values, new_values, database_user, changed_at)
    VALUES (
        OLD.id,
        'deleted',
        JSON_OBJECT(
            'patient_id', OLD.patient_id,
            'appointment_id', OLD.appointment_id,
            'doctor_id', OLD.doctor_id,
            'treatment_id', OLD.treatment_id,
            'amount', OLD.amount,
            'method', OLD.method,
            'reference', OLD.reference,
            'paid_at', CAST(OLD.paid_at AS CHAR),
            'received_by_user_id', OLD.received_by_user_id,
            'notes', OLD.notes,
            'voided_at', CAST(OLD.voided_at AS CHAR),
            'voided_by_user_id', OLD.voided_by_user_id,
            'void_reason', OLD.void_reason,
            'created_at', CAST(OLD.created_at AS CHAR)
        ),
        NULL,
        USER(),
        fn_ahora_lima()
    );
END$$

DELIMITER ;
