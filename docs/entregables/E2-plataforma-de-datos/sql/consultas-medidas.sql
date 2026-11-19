-- Las 6 consultas de las pantallas y reportes más usados, tal como las arma Laravel (con sus valores), para medir
-- los índices de la migración 2026_11_18_210452_add_indexes_for_frequent_queries con EXPLAIN y EXPLAIN ANALYZE
-- antes y después. Resultado: evidencias/explain-antes.txt y evidencias/explain-despues.txt.
-- Q1 Agenda de hoy (widget "Agenda de hoy", DoctorDay, Tray, WalkIn: whereDate('starts_at', today()))
select * from `appointments` where date(`starts_at`) = '2026-11-18' and `status` != 'cancelled' and `appointments`.`deleted_at` is null;
-- Q2 Saldos por cobrar (OwingBalances: Patient::owing()->withBalance())
select `patients`.*, ((select coalesce(sum(c.amount - c.discount), 0) from charges c where c.patient_id = patients.id and c.voided_at is null) - (select coalesce(sum(p.amount), 0) from payments p where p.patient_id = patients.id and p.voided_at is null)) as balance_due from `patients` where ((select coalesce(sum(c.amount - c.discount), 0) from charges c where c.patient_id = patients.id and c.voided_at is null) - (select coalesce(sum(p.amount), 0) from payments p where p.patient_id = patients.id and p.voided_at is null)) > 0 and `patients`.`deleted_at` is null;
-- Q3 Realizado del mes (EarningsReport::produced)
select sum(amount - discount) as `aggregate` from `charges` where `voided_at` is null and `charged_at` between '2026-10-01 00:00:00' and '2026-10-31 23:59:59';
-- Q4 Realizado por doctor (EarningsReport::byDoctor)
select doctor_id, sum(amount - discount) as total from `charges` where `voided_at` is null and `charged_at` between '2026-10-01 00:00:00' and '2026-10-31 23:59:59' group by `doctor_id`;
-- Q5 Costo de materiales del mes (EarningsReport::materialsCost)
select sum(-quantity * coalesce(unit_cost, 0)) as `aggregate` from `stock_movements` where `type` in ('use', 'return', 'write_off') and `created_at` between '2026-11-01 00:00:00' and '2026-11-30 23:59:59';
-- Q6 Quién cobró ese día (CashClosing::receivers)
select `name`, `id` from `users` where `id` in (select `received_by_user_id` from `payments` where date(`paid_at`) = '2026-11-16' and `received_by_user_id` is not null) order by `name` asc;
