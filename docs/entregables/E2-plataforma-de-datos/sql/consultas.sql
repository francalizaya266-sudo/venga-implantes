-- Consultas de negocio sobre la base del consultorio (MySQL 8). Cada una dice qué pregunta responde y con qué
-- usuario de seguridad.sql se corre: consultorio_app lee las tablas; consultorio_reportes solo ve las vistas y los
-- procedimientos. correr-consultas.sh las corre todas y guarda el resultado en evidencias/consultas-resultado.txt.
-- Montos en soles (S/). "Hoy" es la fecha de Lima (UTC−5), igual que en la aplicación.

-- C01. ¿Cuántos pacientes tiene cada cuenta (celular)? Una cuenta puede tener a la familia a cargo.
-- Usuario: consultorio_app
SELECT pacientes_en_la_cuenta, COUNT(*) AS cuentas
FROM (
    SELECT a.id, COUNT(p.id) AS pacientes_en_la_cuenta
    FROM accounts a
    LEFT JOIN patients p ON p.account_id = a.id AND p.deleted_at IS NULL
    WHERE a.deleted_at IS NULL
    GROUP BY a.id
) AS por_cuenta
GROUP BY pacientes_en_la_cuenta
ORDER BY pacientes_en_la_cuenta;

-- C02. ¿Cómo le fue a cada doctor en los últimos 90 días? Citas atendidas, inasistencias y su porcentaje.
-- Usuario: consultorio_app
SELECT d.name AS doctor,
       COUNT(*) AS citas,
       SUM(a.status = 'completed') AS atendidas,
       SUM(a.status = 'no_show') AS no_asistio,
       SUM(a.status = 'cancelled') AS canceladas,
       ROUND(100 * SUM(a.status = 'no_show') / NULLIF(SUM(a.status IN ('completed', 'no_show')), 0), 1) AS pct_inasistencia
FROM appointments a
JOIN doctors d ON d.id = a.doctor_id
WHERE a.deleted_at IS NULL
  AND a.starts_at >= DATE(CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', '-05:00')) - INTERVAL 90 DAY
  AND a.starts_at < DATE(CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', '-05:00'))
GROUP BY d.id, d.name
ORDER BY citas DESC;

-- C03. ¿Qué tratamientos se reservan más y por dónde llegan (web o recepción)?
-- Usuario: consultorio_app
SELECT t.name AS tratamiento,
       COUNT(*) AS citas,
       SUM(a.booked_via = 'web') AS por_la_web,
       SUM(a.booked_via = 'reception') AS por_recepcion,
       ROUND(100 * SUM(a.booked_via = 'web') / COUNT(*), 1) AS pct_web
FROM appointments a
JOIN treatments t ON t.id = a.treatment_id
WHERE a.deleted_at IS NULL AND a.status <> 'cancelled'
GROUP BY t.id, t.name
ORDER BY citas DESC;

-- C04. ¿Cuánto se cobró cada mes y con qué medio de pago? (últimos 6 meses, sin pagos anulados)
-- Usuario: consultorio_app
SELECT DATE_FORMAT(paid_at, '%Y-%m') AS mes,
       SUM(amount) AS cobrado,
       SUM(IF(method = 'cash', amount, 0)) AS efectivo,
       SUM(IF(method = 'yape', amount, 0)) AS yape,
       SUM(IF(method = 'plin', amount, 0)) AS plin,
       SUM(IF(method = 'card', amount, 0)) AS tarjeta,
       SUM(IF(method = 'transfer', amount, 0)) AS transferencia
FROM payments
WHERE voided_at IS NULL
  AND paid_at >= DATE_FORMAT(CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', '-05:00'), '%Y-%m-01') - INTERVAL 5 MONTH
GROUP BY mes
ORDER BY mes;

-- C05. ¿Quiénes son los 10 pacientes que más deben? Saldo = cargos válidos − pagos válidos, como en
-- app/Services/Billing (la vista v_saldo_paciente de C06 da lo mismo).
-- Usuario: consultorio_app
SELECT p.id AS paciente_id,
       CONCAT(p.first_name, ' ', p.last_name) AS paciente,
       COALESCE(c.cargado, 0) AS cargado,
       COALESCE(g.pagado, 0) AS pagado,
       COALESCE(c.cargado, 0) - COALESCE(g.pagado, 0) AS saldo
FROM patients p
LEFT JOIN (SELECT patient_id, SUM(amount - discount) AS cargado FROM charges WHERE voided_at IS NULL GROUP BY patient_id) c
       ON c.patient_id = p.id
LEFT JOIN (SELECT patient_id, SUM(amount) AS pagado FROM payments WHERE voided_at IS NULL GROUP BY patient_id) g
       ON g.patient_id = p.id
WHERE p.deleted_at IS NULL
HAVING saldo > 0
ORDER BY saldo DESC, paciente_id
LIMIT 10;

-- C06. ¿Cuánto dinero está por cobrar en total? (vista v_saldo_paciente)
-- Usuario: consultorio_reportes
SELECT COUNT(*) AS pacientes_que_deben,
       SUM(saldo) AS por_cobrar,
       ROUND(AVG(saldo), 2) AS deuda_promedio,
       MAX(saldo) AS deuda_mayor
FROM v_saldo_paciente
WHERE saldo > 0;

-- C07. ¿Qué tan antigua es la deuda? Lo que falta pagar de cada cargo, por tramos de días desde que se cargó
-- (vista v_cargos_pendientes, que reparte los pagos del más antiguo al más nuevo).
-- Usuario: consultorio_reportes
SELECT CASE
           WHEN DATEDIFF(fn_hoy_lima(), cargado_el) <= 30 THEN '1. Hasta 30 días'
           WHEN DATEDIFF(fn_hoy_lima(), cargado_el) <= 60 THEN '2. De 31 a 60 días'
           WHEN DATEDIFF(fn_hoy_lima(), cargado_el) <= 90 THEN '3. De 61 a 90 días'
           ELSE '4. Más de 90 días'
       END AS antiguedad,
       COUNT(*) AS cargos,
       COUNT(DISTINCT paciente_id) AS pacientes,
       SUM(por_pagar) AS por_pagar
FROM v_cargos_pendientes
GROUP BY antiguedad
ORDER BY antiguedad;

-- C08. ¿Cómo está la agenda de hoy? (vista v_agenda_del_dia: sin canceladas, con el saldo de cada paciente)
-- Usuario: consultorio_reportes
SELECT hora, termina, doctor, paciente, tratamiento, estado, paciente_nuevo, saldo_paciente
FROM v_agenda_del_dia;

-- C09. ¿Qué materiales costaron más en los últimos 3 meses? (vista v_consumo_materiales)
-- Usuario: consultorio_reportes
SELECT material, categoria, unidad, SUM(usado) AS usado, SUM(costo) AS costo
FROM v_consumo_materiales
WHERE mes >= DATE_FORMAT(fn_hoy_lima() - INTERVAL 2 MONTH, '%Y-%m')
GROUP BY material_id, material, categoria, unidad
ORDER BY costo DESC
LIMIT 8;

-- C10. ¿Qué materiales hay que pedir? Existencias en el mínimo o debajo, con su proveedor.
-- Usuario: consultorio_app
SELECT s.name AS material, s.unit AS unidad, s.stock AS hay, s.min_stock AS minimo,
       COALESCE(sp.name, 'Sin proveedor') AS proveedor
FROM supplies s
LEFT JOIN suppliers sp ON sp.id = s.supplier_id
WHERE s.is_active = 1 AND s.stock <= s.min_stock
ORDER BY s.stock - s.min_stock, s.name;

-- C11. ¿Cada cuánto vuelven los pacientes? Días entre una atención y la siguiente (función de ventana LAG),
-- según el tratamiento de la segunda.
-- Usuario: consultorio_app
WITH visitas AS (
    SELECT a.patient_id, a.treatment_id, a.starts_at,
           LAG(a.starts_at) OVER (PARTITION BY a.patient_id ORDER BY a.starts_at, a.id) AS anterior
    FROM appointments a
    WHERE a.deleted_at IS NULL AND a.status = 'completed'
)
SELECT t.name AS tratamiento,
       COUNT(*) AS regresos,
       ROUND(AVG(DATEDIFF(v.starts_at, v.anterior)), 1) AS dias_promedio,
       MIN(DATEDIFF(v.starts_at, v.anterior)) AS dias_minimo,
       MAX(DATEDIFF(v.starts_at, v.anterior)) AS dias_maximo
FROM visitas v
JOIN treatments t ON t.id = v.treatment_id
WHERE v.anterior IS NOT NULL
GROUP BY t.id, t.name
ORDER BY regresos DESC;

-- C12. ¿A quiénes hay que volver a llamar? Pacientes atendidos alguna vez, sin atención en los últimos 6 meses
-- y sin cita futura (CTE y NOT EXISTS).
-- Usuario: consultorio_app
WITH ultima AS (
    SELECT patient_id, MAX(starts_at) AS ultima_atencion, COUNT(*) AS atenciones
    FROM appointments
    WHERE deleted_at IS NULL AND status = 'completed'
    GROUP BY patient_id
)
SELECT COUNT(*) AS pacientes_por_recuperar,
       MIN(u.ultima_atencion) AS la_mas_antigua,
       ROUND(AVG(u.atenciones), 1) AS atenciones_promedio
FROM ultima u
JOIN patients p ON p.id = u.patient_id AND p.deleted_at IS NULL
WHERE u.ultima_atencion < CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', '-05:00') - INTERVAL 6 MONTH
  AND NOT EXISTS (
      SELECT 1 FROM appointments f
      WHERE f.patient_id = u.patient_id AND f.deleted_at IS NULL
        AND f.status IN ('pending', 'confirmed') AND f.starts_at >= CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', '-05:00')
  );

-- C13. ¿Qué cambió en el sistema esta semana y quién lo hizo? (bitácora audit_logs de la aplicación)
-- Usuario: consultorio_app
SELECT REPLACE(l.auditable_type, 'App\\Models\\', '') AS que,
       l.event AS evento,
       COALESCE(u.name, 'Sistema o paciente') AS quien,
       COUNT(*) AS cambios
FROM audit_logs l
LEFT JOIN users u ON u.id = l.user_id
WHERE l.created_at >= CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', '-05:00') - INTERVAL 7 DAY
GROUP BY que, evento, quien
ORDER BY cambios DESC
LIMIT 10;

-- C14. Cierre de caja del 12/11/2026 (procedimiento sp_cierre_caja: resumen y cobrado por medio de pago).
-- Usuario: consultorio_reportes
CALL sp_cierre_caja('2026-11-12', NULL);

-- C15. Reporte de ingresos de octubre de 2026 (procedimiento sp_reporte_mensual_ingresos: resumen, por doctor y
-- por medio de pago).
-- Usuario: consultorio_reportes
CALL sp_reporte_mensual_ingresos(2026, 10);
