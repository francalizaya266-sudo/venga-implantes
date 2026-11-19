#!/usr/bin/env bash
# Comprueba que cada usuario de seguridad.sql puede lo que necesita y nada más. Lo corre el job "mysql" de CI.
# Uso (las claves son las mismas que se usaron con seguridad.sql):
#   MYSQL_HOST=127.0.0.1 MYSQL_PORT=3306 CLAVE_APP=… CLAVE_DESPLIEGUE=… CLAVE_REPORTES=… CLAVE_RESPALDO=… \
#     bash database/sql/probar-seguridad.sh
# Necesita la consola de MySQL (mysql y mysqldump) y la base ya migrada. Sale con error si algo no es lo esperado.
set -u

host=${MYSQL_HOST:-127.0.0.1}
port=${MYSQL_PORT:-3306}
base=${MYSQL_DATABASE:-consultorio}
fallas=0
cnf=$(mktemp)
trap 'rm -f "$cnf"' EXIT

# La clave va en un archivo temporal y no en la línea de comandos, donde la vería cualquiera con `ps`.
credenciales() {
    printf '[client]\nuser=%s\npassword=%s\nhost=%s\nport=%s\n' "$1" "$2" "$host" "$port" > "$cnf"
}

como() {
    credenciales "$1" "$2"
    mysql --defaults-extra-file="$cnf" "$base" -N -e "$3" 2>&1 >/dev/null
}

puede() {
    local error
    if error=$(como "$1" "$2" "$3"); then
        echo "OK     $1 puede: $3"
    else
        echo "FALLA  $1 debería poder: $3 -> $error"
        fallas=$((fallas + 1))
    fi
}

no_puede() {
    local error
    if error=$(como "$1" "$2" "$3"); then
        echo "FALLA  $1 no debería poder: $3"
        fallas=$((fallas + 1))
    else
        echo "OK     $1 no puede: $3 -> ${error##*: }"
    fi
}

app=consultorio_app
puede "$app" "$CLAVE_APP" "SELECT COUNT(*) FROM patients"
puede "$app" "$CLAVE_APP" "UPDATE settings SET updated_at = updated_at WHERE 1 = 0"
no_puede "$app" "$CLAVE_APP" "CREATE TABLE prueba_seguridad (id INT)"
no_puede "$app" "$CLAVE_APP" "DROP TABLE payment_audit_logs"
no_puede "$app" "$CLAVE_APP" "DROP TRIGGER trg_pagos_auditar_cambio"
no_puede "$app" "$CLAVE_APP" "CREATE USER 'intruso'@'%' IDENTIFIED BY 'intruso-123'"

reportes=consultorio_reportes
puede "$reportes" "$CLAVE_REPORTES" "SELECT COUNT(*) FROM v_saldo_paciente"
puede "$reportes" "$CLAVE_REPORTES" "SELECT COUNT(*) FROM v_deuda_vencida"
puede "$reportes" "$CLAVE_REPORTES" "CALL sp_reporte_mensual_ingresos(2026, 10)"
puede "$reportes" "$CLAVE_REPORTES" "CALL sp_cierre_caja(fn_hoy_lima(), NULL)"
no_puede "$reportes" "$CLAVE_REPORTES" "SELECT COUNT(*) FROM patients"
no_puede "$reportes" "$CLAVE_REPORTES" "SELECT COUNT(*) FROM medical_backgrounds"
no_puede "$reportes" "$CLAVE_REPORTES" "SELECT COUNT(*) FROM payments"
no_puede "$reportes" "$CLAVE_REPORTES" "DELETE FROM payments"

respaldo=consultorio_respaldo
credenciales "$respaldo" "$CLAVE_RESPALDO"
copia=$(mysqldump --defaults-extra-file="$cnf" --single-transaction --no-tablespaces --routines --triggers --set-gtid-purged=OFF "$base" 2>&1)
if [ $? -eq 0 ] && grep -q 'CREATE TABLE `payments`' <<< "$copia" && grep -q 'TRIGGER `trg_pagos_auditar_cambio`' <<< "$copia" \
    && grep -q 'PROCEDURE `sp_cierre_caja`' <<< "$copia" && grep -q 'VIEW `v_saldo_paciente`' <<< "$copia"; then
    echo "OK     $respaldo puede: mysqldump con tablas, vistas, procedimientos y triggers ($(wc -c <<< "$copia") bytes)"
else
    echo "FALLA  $respaldo debería poder: mysqldump completo -> $(head -c 300 <<< "$copia")"
    fallas=$((fallas + 1))
fi
no_puede "$respaldo" "$CLAVE_RESPALDO" "UPDATE settings SET updated_at = updated_at"
no_puede "$respaldo" "$CLAVE_RESPALDO" "DROP TABLE settings"

despliegue=consultorio_despliegue
puede "$despliegue" "$CLAVE_DESPLIEGUE" "CREATE TABLE prueba_seguridad (id INT); DROP TABLE prueba_seguridad"
puede "$despliegue" "$CLAVE_DESPLIEGUE" "CREATE OR REPLACE VIEW prueba_seguridad AS SELECT 1 AS uno; DROP VIEW prueba_seguridad"
no_puede "$despliegue" "$CLAVE_DESPLIEGUE" "CREATE USER 'intruso'@'%' IDENTIFIED BY 'intruso-123'"
no_puede "$despliegue" "$CLAVE_DESPLIEGUE" "CREATE DATABASE otra_base"
no_puede "$despliegue" "$CLAVE_DESPLIEGUE" "GRANT SELECT ON $base.* TO 'consultorio_reportes'@'%'"

if [ "$fallas" -gt 0 ]; then
    echo "$fallas comprobaciones no dieron lo esperado."
    exit 1
fi

echo "Todo como se esperaba."
