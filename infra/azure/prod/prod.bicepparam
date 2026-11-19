// Lo más simple es usar infra/desplegar.sh, que define estas variables por ti.
// Los valores sensibles se leen de variables de entorno: nada secreto queda en el repositorio.
// Antes de desplegar, en la misma terminal (Cloud Shell, Git Bash o WSL):
//   export SSH_PUBLIC_KEY="$(cat ~/.ssh/vega.pub)"
//   export ADMIN_SOURCE_IPS="tu.ip.del.navegador/32,$(curl -s https://ifconfig.me)/32"
//   export GITHUB_REPO=francalizaya266-sudo/venga-implantes
//   export OPERATOR_OBJECT_ID="$(az ad signed-in-user show --query id -o tsv)"
//   export MYSQL_ADMIN_PASSWORD='...'   # sácala de 1Password
//   export ALERT_EMAIL='correo-del-equipo@...'
//   export DEPLOY_VMS=true              # false crea solo la red, los servicios y el firewall
//   export AUTO_SHUTDOWN=2300           # opcional: apaga vm-proxy y vm-app cada noche para ahorrar crédito
using './main.bicep'

param sshPublicKey = readEnvironmentVariable('SSH_PUBLIC_KEY')
param adminSourceIps = readEnvironmentVariable('ADMIN_SOURCE_IPS', '')
param githubRepo = readEnvironmentVariable('GITHUB_REPO', '')
param operatorObjectId = readEnvironmentVariable('OPERATOR_OBJECT_ID')
param mysqlAdminPassword = readEnvironmentVariable('MYSQL_ADMIN_PASSWORD')
param alertEmail = readEnvironmentVariable('ALERT_EMAIL')
param deployVms = bool(readEnvironmentVariable('DEPLOY_VMS', 'true'))
param autoShutdownTime = readEnvironmentVariable('AUTO_SHUTDOWN', '')
param allowSetupSsh = bool(readEnvironmentVariable('SETUP_SSH', 'true'))
