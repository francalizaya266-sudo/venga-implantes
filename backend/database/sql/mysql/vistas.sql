-- Vistas del consultorio (MySQL 8). Necesitan las funciones de funciones.sql.
-- Las crea la migración 2026_11_18_210454_create_reporting_views_functions_and_procedures y las vuelve a crear
-- `php artisan bd:objetos`. Se pueden consultar con el usuario de reportes (seguridad.sql), que no lee las tablas.

-- Saldo de cada paciente: lo cargado menos lo pagado, sin lo anulado (Patient::balance y Patient::owing).
-- Positivo: debe. Negativo: tiene saldo a favor. Sin los pacientes borrados (como Patient::query()).
CREATE OR REPLACE SQL SECURITY DEFINER VIEW v_saldo_paciente AS
SELECT
    p.id AS paciente_id,
    TRIM(CONCAT(p.first_name, ' ', p.last_name)) AS paciente,
    p.document_type AS tipo_documento,
    p.document_number AS documento,
    COALESCE(c.cargado, 0) AS cargado,
    COALESCE(g.pagado, 0) AS pagado,
    COALESCE(c.cargado, 0) - COALESCE(g.pagado, 0) AS saldo,
    p.last_visit_at AS ultima_visita
FROM patients p
LEFT JOIN (
    SELECT patient_id, SUM(amount - discount) AS cargado
    FROM charges
    WHERE voided_at IS NULL
    GROUP BY patient_id
) c ON c.patient_id = p.id
LEFT JOIN (
    SELECT patient_id, SUM(amount) AS pagado
    FROM payments
    WHERE voided_at IS NULL
    GROUP BY patient_id
) g ON g.patient_id = p.id
WHERE p.deleted_at IS NULL;

-- Las citas de hoy (hora de Lima), sin las canceladas, como el panel "Agenda de hoy" (TodayAppointments).
-- El día se calcula con UTC_TIMESTAMP() y no con fn_hoy_lima(): una función propia se evalúa fila por fila y
-- MySQL no usaría el índice appointments_starts_on_index; UTC_TIMESTAMP() es una constante en cada consulta.
CREATE OR REPLACE SQL SECURITY DEFINER VIEW v_agenda_del_dia AS
SELECT
    a.id AS cita_id,
    TIME_FORMAT(a.starts_at, '%H:%i') AS hora,
    TIME_FORMAT(a.ends_at, '%H:%i') AS termina,
    d.name AS doctor,
    TRIM(CONCAT(p.first_name, ' ', p.last_name)) AS paciente,
    ac.phone AS celular,
    t.name AS tratamiento,
    fn_estado_cita(a.status) AS estado,
    a.is_new_patient AS paciente_nuevo,
    a.is_overbooked AS sobreturno,
    fn_saldo_paciente(a.patient_id) AS saldo_paciente,
    a.starts_at AS inicio,
    a.doctor_id
FROM appointments a
JOIN patients p ON p.id = a.patient_id
JOIN doctors d ON d.id = a.doctor_id
JOIN treatments t ON t.id = a.treatment_id
LEFT JOIN accounts ac ON ac.id = p.account_id
WHERE DATE(a.starts_at) = DATE(CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', '-05:00'))
    AND a.deleted_at IS NULL
    AND a.status <> 'cancelled'
ORDER BY a.starts_at, a.id;

-- Lo que falta pagar de cada cargo (BillingService::unpaidCharges): los pagos cubren primero lo más antiguo
-- (por fecha y luego por id). Con S = lo cargado hasta ese cargo (sin contar los cargos negativos) y P = lo
-- pagado por el paciente, a cada cargo le falta min(neto, max(0, S - P)). Si P es negativo (devolvió más de lo
-- que pagó), el primer cargo carga esa diferencia, igual que en PHP.
CREATE OR REPLACE SQL SECURITY DEFINER VIEW v_cargos_pendientes AS
SELECT
    x.cargo_id,
    x.paciente_id,
    x.cargado_el,
    x.concepto,
    x.neto,
    x.por_pagar
FROM (
    SELECT
        f.cargo_id,
        f.paciente_id,
        f.cargado_el,
        f.concepto,
        f.neto,
        CASE
            WHEN f.orden = 1 AND f.pagado < 0 THEN f.neto - f.pagado
            ELSE LEAST(f.neto, GREATEST(0, f.acumulado - f.pagado))
        END AS por_pagar
    FROM (
        SELECT
            c.id AS cargo_id,
            c.patient_id AS paciente_id,
            c.charged_at AS cargado_el,
            c.description AS concepto,
            c.amount - c.discount AS neto,
            ROW_NUMBER() OVER w AS orden,
            SUM(GREATEST(0, c.amount - c.discount)) OVER (w ROWS UNBOUNDED PRECEDING) AS acumulado,
            COALESCE(g.pagado, 0) AS pagado
        FROM charges c
        LEFT JOIN (
            SELECT patient_id, SUM(amount) AS pagado
            FROM payments
            WHERE voided_at IS NULL
            GROUP BY patient_id
        ) g ON g.patient_id = c.patient_id
        WHERE c.voided_at IS NULL
        WINDOW w AS (PARTITION BY c.patient_id ORDER BY c.charged_at, c.id)
    ) f
) x
WHERE x.por_pagar > 0;

-- Deuda vencida: las cuotas del cronograma que vencieron antes de hoy y siguen sin pagarse (la pestaña
-- "Atrasadas" de Cuotas por cobrar, BillingService::installmentsIn(['late'])). Una cuota ya cargada debe lo que
-- falta de su cargo; una que aún no se carga debe su monto menos el descuento. Sin planes cancelados ni borrados.
CREATE OR REPLACE SQL SECURITY DEFINER VIEW v_deuda_vencida AS
SELECT
    i.id AS cuota_id,
    pl.id AS plan_id,
    p.id AS paciente_id,
    TRIM(CONCAT(p.first_name, ' ', p.last_name)) AS paciente,
    ac.phone AS celular,
    COALESCE(NULLIF(pl.title, ''), 'Plan de tratamiento') AS plan,
    CASE
        WHEN pl.payment_mode = 'upfront' THEN 'Pago único'
        WHEN i.number = 0 THEN 'Inicial'
        ELSE CONCAT('Cuota ', i.number, ' de ', (
            SELECT COUNT(*) FROM treatment_plan_installments n
            WHERE n.treatment_plan_id = i.treatment_plan_id AND n.number > 0
        ))
    END AS cuota,
    i.due_on AS vencio,
    DATEDIFF(DATE(CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', '-05:00')), i.due_on) AS dias_de_atraso,
    CASE
        WHEN i.charge_id IS NULL THEN i.amount - i.discount
        ELSE COALESCE(cp.por_pagar, 0)
    END AS por_pagar,
    i.reminded_at AS avisado_el
FROM treatment_plan_installments i
JOIN treatment_plans pl ON pl.id = i.treatment_plan_id
JOIN patients p ON p.id = pl.patient_id
LEFT JOIN accounts ac ON ac.id = p.account_id
LEFT JOIN v_cargos_pendientes cp ON cp.cargo_id = i.charge_id
WHERE pl.deleted_at IS NULL
    AND pl.status <> 'cancelled'
    AND i.due_on < DATE(CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', '-05:00'))
    AND (i.charge_id IS NULL OR cp.por_pagar > 0);

-- Consumo de materiales por mes: lo usado en atenciones (menos lo devuelto al reabrir una) y lo dado de baja,
-- con su costo. El costo del mes es el mismo de EarningsReport::materialsCost (sin el tope en cero).
CREATE OR REPLACE SQL SECURITY DEFINER VIEW v_consumo_materiales AS
SELECT
    DATE_FORMAT(m.created_at, '%Y-%m') AS mes,
    s.id AS material_id,
    s.name AS material,
    fn_categoria_material(s.category) AS categoria,
    s.unit AS unidad,
    SUM(CASE WHEN m.type IN ('use', 'return') THEN -m.quantity ELSE 0 END) AS usado,
    SUM(CASE WHEN m.type = 'write_off' THEN -m.quantity ELSE 0 END) AS dado_de_baja,
    CAST(SUM(-m.quantity * COALESCE(m.unit_cost, 0)) AS DECIMAL(12, 2)) AS costo,
    COUNT(*) AS movimientos
FROM stock_movements m
JOIN supplies s ON s.id = m.supply_id
WHERE m.type IN ('use', 'return', 'write_off')
GROUP BY DATE_FORMAT(m.created_at, '%Y-%m'), s.id, s.name, s.category, s.unit;
