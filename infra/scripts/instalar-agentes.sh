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

echo "==> Agente de Wazuh ($AGENT_NAME -> $WAZUH_MANAGER)"
curl -s https://packages.wazuh.com/key/GPG-KEY-WAZUH | gpg --no-default-keyring --keyring gnupg-ring:/usr/share/keyrings/wazuh.gpg --import
chmod 644 /usr/share/keyrings/wazuh.gpg
echo "deb [signed-by=/usr/share/keyrings/wazuh.gpg] https://packages.wazuh.com/${WAZUH_MAJOR}/apt/ stable main" > /etc/apt/sources.list.d/wazuh.list
apt-get update -q
WAZUH_MANAGER="$WAZUH_MANAGER" WAZUH_AGENT_NAME="$AGENT_NAME" apt-get install -y wazuh-agent

# Logs propios del proyecto que Wazuh debe leer, si existen en esta VM.
add_log() {
  local file="$1" format="$2"
  if ! grep -q "$file" /var/ossec/etc/ossec.conf; then
    sed -i "s#</ossec_config>#  <localfile>\n    <log_format>${format}</log_format>\n    <location>${file}</location>\n  </localfile>\n</ossec_config>#" /var/ossec/etc/ossec.conf
  fi
}
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
systemctl enable --now wazuh-agent

if [ -n "${DD_API_KEY:-}" ]; then
  echo "==> Agente de Datadog"
  DD_API_KEY="$DD_API_KEY" DD_SITE="${DD_SITE:-datadoghq.com}" \
    bash -c "$(curl -L https://install.datadoghq.com/scripts/install_script_agent7.sh)"
fi

echo "Listo."
