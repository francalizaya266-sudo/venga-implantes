// Lo más simple es usar infra/desplegar.sh, que define estas variables por ti.
// Antes de desplegar, en la misma terminal:
//   export SSH_PUBLIC_KEY="$(cat ~/.ssh/id_ed25519.pub)"
//   export ADMIN_SOURCE_IPS="tu.ip.del.navegador/32,$(curl -s https://ifconfig.me)/32"
//   export SITE_DOMAIN=vegaimplantes.me
using './main.bicep'

param sshPublicKey = readEnvironmentVariable('SSH_PUBLIC_KEY')
param adminSourceIps = readEnvironmentVariable('ADMIN_SOURCE_IPS')
param deployVms = bool(readEnvironmentVariable('DEPLOY_VMS', 'true'))
param siteDomain = readEnvironmentVariable('SITE_DOMAIN')
param labVm = readEnvironmentVariable('LAB_VM', 'recepcion')
