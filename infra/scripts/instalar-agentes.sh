#!/usr/bin/env bash
# Instala en una VM Ubuntu el agente de Wazuh (SIEM) y, si se da la clave, el de Datadog (métricas).
#
# Uso (en cada VM):
#   sudo WAZUH_MANAGER=10.10.50.10 AGENT_NAME=vm-app DD_API_KEY=xxxx DD_SITE=us5.datadoghq.com bash instalar-agentes.sh
#
# DD_SITE es el sitio de tu cuenta de Datadog (aparece en la dirección del navegador al entrar).
# La clave de Datadog sale de 1Password; no la escribas en archivos del repositorio.
set -euo pipefail

: "${WAZUH_MANAGER:?Indica WAZUH_MANAGER, por ejemplo 10.10.50.10}"
AGENT_NAME="${AGENT_NAME:-$(hostname)}"
WAZUH_MAJOR="${WAZUH_MAJOR:-4.x}"

# El agente no puede ser más nuevo que el servidor (vm-wazuh tiene la 4.12.0): se fija la misma versión.
WAZUH_AGENT_VERSION="${WAZUH_AGENT_VERSION:-4.12.0-1}"

# En vm-wazuh no: el agente y el servidor son paquetes incompatibles y el agente desinstalaría al servidor.
# El servidor ya se vigila a sí mismo (agente 000).
if dpkg -s wazuh-manager >/dev/null 2>&1; then
  echo "Esta VM es el servidor de Wazuh: no se instala el agente."
  exit 0
fi

echo "==> Agente de Wazuh $WAZUH_AGENT_VERSION ($AGENT_NAME -> $WAZUH_MANAGER)"
curl -s https://packages.wazuh.com/key/GPG-KEY-WAZUH | gpg --no-default-keyring --keyring gnupg-ring:/usr/share/keyrings/wazuh.gpg --import
chmod 644 /usr/share/keyrings/wazuh.gpg
echo "deb [signed-by=/usr/share/keyrings/wazuh.gpg] https://packages.wazuh.com/${WAZUH_MAJOR}/apt/ stable main" > /etc/apt/sources.list.d/wazuh.list
apt-get update -q
# Un agente de otra versión se quita del todo: bajar de versión deja a dpkg preguntando por archivos de configuración.
instalada="$(dpkg-query -W -f='${Version}' wazuh-agent 2>/dev/null || true)"
if [ -n "$instalada" ] && [ "$instalada" != "$WAZUH_AGENT_VERSION" ]; then
  apt-mark unhold wazuh-agent >/dev/null 2>&1 || true
  systemctl stop wazuh-agent 2>/dev/null || true
  dpkg --purge --force-all wazuh-agent >/dev/null 2>&1 || true
  rm -rf /var/ossec
fi
WAZUH_MANAGER="$WAZUH_MANAGER" WAZUH_AGENT_NAME="$AGENT_NAME" DEBIAN_FRONTEND=noninteractive   apt-get install -y --allow-downgrades -o Dpkg::Options::=--force-confnew "wazuh-agent=$WAZUH_AGENT_VERSION"
apt-mark hold wazuh-agent >/dev/null
# Si ya había un agente, deja apuntado el servidor correcto.
sed -i "s#<address>.*</address>#<address>${WAZUH_MANAGER}</address>#" /var/ossec/etc/ossec.conf

# Logs propios del proyecto que Wazuh debe leer, si existen en esta VM.
add_log() {
  local file="$1" format="$2"
  if ! grep -q "$file" /var/ossec/etc/ossec.conf; then
    sed -i "s#</ossec_config>#  <localfile>\n    <log_format>${format}</log_format>\n    <location>${file}</location>\n  </localfile>\n</ossec_config>#" /var/ossec/etc/ossec.conf
  fi
}
[ -d /var/www/vega ] && add_log "/var/www/vega/shared/storage/logs/eventos-*.log" json
[ -d /var/www/vega ] && add_log "/var/www/vega/shared/storage/logs/laravel-*.log" syslog
[ -d /etc/nginx ] && add_log "/var/log/nginx/vega.access.log" apache
[ -f /etc/opencanaryd/opencanary.conf ] && add_log "/var/log/opencanary.log" json
[ -f /var/log/modsec_audit.log ] && add_log "/var/log/modsec_audit.log" syslog
# Firewall (vm-fw): bloqueos de nftables (van al log del kernel) y alertas de Suricata.
if [ -f /etc/nftables.conf ]; then
  add_log "/var/log/kern.log" syslog
  add_log "/var/log/suricata/eve.json" json
fi

systemctl daemon-reload
systemctl enable wazuh-agent
systemctl restart wazuh-agent

if [ -n "${DD_API_KEY:-}" ]; then
  echo "==> Agente de Datadog"
  DD_API_KEY="$DD_API_KEY" DD_SITE="${DD_SITE:-datadoghq.com}" \
    bash -c "$(curl -L https://install.datadoghq.com/scripts/install_script_agent7.sh)"
fi

echo "Listo."
