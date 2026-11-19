// Producción, como la vista de despliegue del E1 (figura 13): desde internet solo se llega al firewall (vm-fw); el firewall
// (con Suricata) pasa el tráfico al proxy de la DMZ (nginx + ModSecurity + Let's Encrypt), y el proxy a vm-app.
// MySQL Flexible no tiene acceso público y exige TLS. Los secretos van en Key Vault; los registros, en Azure Monitor.
// Se despliega en el grupo de recursos rg-vega-prod, en dos pasadas:
//
// Todo sale en una sola pasada: el firewall Linux (infra/firewall/produccion.nft) se configura solo con cloud-init,
// y vm-proxy y vm-app esperan a que dé salida a internet antes de instalarse. Con DEPLOY_VMS=false solo se crean
// la red, MySQL, Key Vault, Blob y el firewall.
//
//   az deployment group create -g rg-vega-prod -f infra/azure/prod/main.bicep -p infra/azure/prod/prod.bicepparam

param location string = resourceGroup().location

@description('Prefijo corto para los nombres de los recursos.')
param prefix string = 'vega'

param adminUsername string = 'vegaadmin'

@description('Llave pública SSH del equipo.')
param sshPublicKey string

@description('IP de administración separadas por coma (x.x.x.x/32): tu navegador y Cloud Shell. Son las únicas que llegan al SSH del firewall y, mientras instalas, al de las VM. Vacío cierra esos accesos.')
param adminSourceIps string = ''

@description('Deja entrar por SSH a vm-app y vm-proxy desde las IP de administración, antes de que exista la VPN. false al terminar la fase 4.')
param allowSetupSsh bool = true

@description('Repositorio de GitHub (dueño/nombre) que publica en vm-app sin claves. Vacío = sin despliegue desde GitHub.')
param githubRepo string = ''

@description('Sujeto del token de GitHub con los números de dueño y repositorio (repo:dueño@id/nombre@id:ref:refs/heads/main), el formato que GitHub usa ahora. Lo calcula desplegar.sh.')
param githubOidcSubject string = ''

@description('Id de objeto (Entra ID) de quien carga los secretos en Key Vault: az ad signed-in-user show --query id -o tsv')
param operatorObjectId string

param mysqlAdminLogin string = 'vegaadmin'

@secure()
param mysqlAdminPassword string

@description('Correo que recibe las alertas de Azure Monitor.')
param alertEmail string

@description('Red de la sede de la clínica, conectada por la VPN entre los dos firewalls.')
param clinicAddressSpace string = '10.10.0.0/16'

@description('Desde dónde se administra producción por la VPN: el firewall de la sede, que hace de jump host.')
param clinicMgmtPrefix string = '10.10.0.0/23'

@description('IP de la tarjeta LAN del firewall: siguiente salto de la DMZ y de la subred de la aplicación.')
param firewallLanIp string = '10.20.1.4'

@description('true crea también vm-proxy y vm-app; false solo la red, los servicios y el firewall.')
param deployVms bool = true

@description('B2ats_v2: 2 vCPU y 1 GiB (más 2 GiB de swap). Si queda corta, Standard_B2als_v2 (4 GiB).')
param appVmSize string = 'Standard_B2ats_v2'

param proxyVmSize string = 'Standard_B2ats_v2'

@description('Apagado diario de vm-proxy y vm-app (HHmm, hora de Lima) para ahorrar crédito; vacío = siempre encendidas. Se encienden con az vm start.')
param autoShutdownTime string = ''

var addressSpace = '10.20.0.0/16'
var dmzPrefix = '10.20.3.0/24'
var appPrefix = '10.20.4.0/24'
var mysqlPrefix = '10.20.5.0/24'
var proxyIp = '10.20.3.4'
var appIp = '10.20.4.4'
var suffix = take(uniqueString(resourceGroup().id), 8)
var tags = {
  proyecto: 'vega-implant'
  entorno: 'produccion'
}

// Roles integrados de Azure.
var roles = {
  blobDataContributor: 'ba92f5b4-2d11-453d-a403-e96b0029c9fe'
  keyVaultSecretsUser: '4633458b-17de-408a-b874-0445c86b69e6'
  keyVaultSecretsOfficer: 'b86a8fe4-44ce-4948-aee5-eccb2c155cd7'
}

// ---------- Monitoreo ----------

resource law 'Microsoft.OperationalInsights/workspaces@2023-09-01' = {
  name: 'law-${prefix}'
  location: location
  tags: tags
  properties: {
    sku: {
      name: 'PerGB2018'
    }
    retentionInDays: 30
    workspaceCapping: {
      dailyQuotaGb: json('0.15')
    }
  }
}

resource actionGroup 'Microsoft.Insights/actionGroups@2023-01-01' = {
  name: 'ag-${prefix}'
  location: 'global'
  tags: tags
  properties: {
    groupShortName: 'vega'
    enabled: true
    emailReceivers: [
      {
        name: 'equipo'
        emailAddress: alertEmail
        useCommonAlertSchema: true
      }
    ]
  }
}

// ---------- NSG ----------

// Azure acepta '*' solo como puerto único (destinationPortRange), no dentro de una lista.
func rule(name string, priority int, direction string, access string, protocol string, source string, ports array) object => {
  name: name
  properties: union({
    priority: priority
    direction: direction
    access: access
    protocol: protocol
    sourceAddressPrefix: source
    sourcePortRange: '*'
    destinationAddressPrefix: '*'
  }, contains(ports, '*') ? { destinationPortRange: '*' } : { destinationPortRanges: ports })
}

// Varias IP de origen: Azure solo las acepta como lista, sin etiquetas como "Internet".
func ruleFrom(name string, priority int, sources array, ports array) object => {
  name: name
  properties: {
    priority: priority
    direction: 'Inbound'
    access: 'Allow'
    protocol: 'Tcp'
    sourceAddressPrefixes: sources
    sourcePortRange: '*'
    destinationAddressPrefix: '*'
    destinationPortRanges: ports
  }
}

var adminIps = filter(map(split(adminSourceIps, ','), ip => trim(ip)), ip => !empty(ip))

var adminWanRules = empty(adminIps) ? [] : [
  // 2200: SSH del firewall. 2222 y 2223: SSH a vm-app y vm-proxy mientras no existe la VPN.
  ruleFrom('Permitir-Admin', 120, adminIps, ['2200', '2222', '2223'])
]

var adminSshRules = (!allowSetupSsh || empty(adminIps)) ? [] : [
  ruleFrom('Permitir-SSH-Instalacion', 130, adminIps, ['22'])
]

// La única puerta de internet: la IP pública del firewall.
resource nsgWan 'Microsoft.Network/networkSecurityGroups@2024-05-01' = {
  name: 'nsg-wan'
  location: location
  tags: tags
  properties: {
    securityRules: concat([
      // El firewall los reenvía al proxy. El 80 solo redirige a HTTPS y renueva el certificado.
      rule('Permitir-Web', 100, 'Inbound', 'Allow', 'Tcp', 'Internet', ['80', '443'])
      rule('Permitir-IPsec', 110, 'Inbound', 'Allow', 'Udp', 'Internet', ['500', '4500'])
    ], adminWanRules)
  }
}

resource nsgLan 'Microsoft.Network/networkSecurityGroups@2024-05-01' = {
  name: 'nsg-lan'
  location: location
  tags: tags
  properties: {
    securityRules: [
      // Lo que vuelve de la sede por la VPN sale del firewall por esta tarjeta.
      rule('Permitir-Sede', 100, 'Inbound', 'Allow', '*', clinicAddressSpace, ['*'])
      // La DMZ y la app salen a internet por el firewall: sus paquetes llegan a esta tarjeta con destino internet,
      // y la regla por defecto de Azure (AllowVnetInBound) solo admite destinos internos.
      rule('Permitir-Salida-Por-Firewall', 110, 'Inbound', 'Allow', '*', addressSpace, ['*'])
      // Lo que el firewall reenvía hacia adentro conserva su origen (la IP del paciente o de la sede), y la regla de
      // salida por defecto (AllowVnetOutBound) solo admite orígenes internos.
      {
        name: 'Permitir-Reenvio-Hacia-Adentro'
        properties: {
          priority: 100
          direction: 'Outbound'
          access: 'Allow'
          protocol: '*'
          sourceAddressPrefix: '*'
          sourcePortRange: '*'
          destinationAddressPrefix: addressSpace
          destinationPortRange: '*'
        }
      }
    ]
  }
}

// El firewall hace NAT de destino sin cambiar el origen: el proxy ve la IP real del paciente.
resource nsgDmz 'Microsoft.Network/networkSecurityGroups@2024-05-01' = {
  name: 'nsg-dmz'
  location: location
  tags: tags
  properties: {
    securityRules: concat([
      rule('Permitir-Web', 100, 'Inbound', 'Allow', 'Tcp', 'Internet', ['80', '443'])
      rule('Permitir-Web-Sede', 110, 'Inbound', 'Allow', 'Tcp', clinicAddressSpace, ['80', '443'])
      rule('Permitir-SSH-Jump', 115, 'Inbound', 'Allow', 'Tcp', clinicMgmtPrefix, ['22'])
    ], adminSshRules)
  }
}

// La aplicación solo recibe del proxy (HTTP) y del jump host de la sede (SSH).
resource nsgApp 'Microsoft.Network/networkSecurityGroups@2024-05-01' = {
  name: 'nsg-app'
  location: location
  tags: tags
  properties: {
    securityRules: concat([
      rule('Permitir-Proxy', 100, 'Inbound', 'Allow', 'Tcp', '${proxyIp}/32', ['80'])
      rule('Permitir-SSH-Jump', 110, 'Inbound', 'Allow', 'Tcp', clinicMgmtPrefix, ['22'])
    ], adminSshRules, [
      rule('Bloquear-Resto-HTTP', 200, 'Inbound', 'Deny', 'Tcp', '*', ['80', '443'])
    ])
  }
}

// ---------- Rutas: la DMZ y la aplicación salen por el firewall ----------

resource rtFirewall 'Microsoft.Network/routeTables@2024-05-01' = {
  name: 'rt-via-firewall'
  location: location
  tags: tags
  properties: {
    disableBgpRoutePropagation: true
    routes: [
      {
        name: 'internet-por-firewall'
        properties: {
          addressPrefix: '0.0.0.0/0'
          nextHopType: 'VirtualAppliance'
          nextHopIpAddress: firewallLanIp
        }
      }
      {
        // Mismo prefijo que la red: el tráfico entre la DMZ y la aplicación cruza el firewall.
        name: 'produccion-por-firewall'
        properties: {
          addressPrefix: addressSpace
          nextHopType: 'VirtualAppliance'
          nextHopIpAddress: firewallLanIp
        }
      }
      {
        name: 'sede-por-firewall'
        properties: {
          addressPrefix: clinicAddressSpace
          nextHopType: 'VirtualAppliance'
          nextHopIpAddress: firewallLanIp
        }
      }
      {
        // MySQL responde directo (no tiene tabla de rutas): ir directo evita una ruta asimétrica.
        name: 'mysql-directo'
        properties: {
          addressPrefix: mysqlPrefix
          nextHopType: 'VnetLocal'
        }
      }
    ]
  }
}

// ---------- Red ----------

resource vnet 'Microsoft.Network/virtualNetworks@2024-05-01' = {
  name: 'vnet-${prefix}'
  location: location
  tags: tags
  properties: {
    addressSpace: {
      addressPrefixes: [
        addressSpace
      ]
    }
    subnets: [
      {
        // Tarjeta WAN del firewall, con la IP pública.
        name: 'snet-wan'
        properties: {
          addressPrefix: '10.20.0.0/24'
          networkSecurityGroup: {
            id: nsgWan.id
          }
        }
      }
      {
        // Tarjeta LAN del firewall. Sin tabla de rutas para no crear un bucle.
        name: 'snet-lan'
        properties: {
          addressPrefix: '10.20.1.0/24'
          networkSecurityGroup: {
            id: nsgLan.id
          }
        }
      }
      {
        name: 'snet-dmz'
        properties: {
          addressPrefix: dmzPrefix
          networkSecurityGroup: {
            id: nsgDmz.id
          }
          routeTable: {
            id: rtFirewall.id
          }
        }
      }
      {
        name: 'snet-app'
        properties: {
          addressPrefix: appPrefix
          networkSecurityGroup: {
            id: nsgApp.id
          }
          routeTable: {
            id: rtFirewall.id
          }
        }
      }
      {
        name: 'snet-mysql'
        properties: {
          addressPrefix: mysqlPrefix
          delegations: [
            {
              name: 'mysql'
              properties: {
                serviceName: 'Microsoft.DBforMySQL/flexibleServers'
              }
            }
          ]
        }
      }
    ]
  }
}

// ---------- MySQL privado y con TLS ----------

resource mysqlDns 'Microsoft.Network/privateDnsZones@2024-06-01' = {
  name: '${prefix}.private.mysql.database.azure.com'
  location: 'global'
  tags: tags
}

resource mysqlDnsLink 'Microsoft.Network/privateDnsZones/virtualNetworkLinks@2024-06-01' = {
  parent: mysqlDns
  name: 'link-${vnet.name}'
  location: 'global'
  properties: {
    registrationEnabled: false
    virtualNetwork: {
      id: vnet.id
    }
  }
}

resource mysql 'Microsoft.DBforMySQL/flexibleServers@2023-12-30' = {
  name: '${prefix}-mysql-${suffix}'
  location: location
  tags: tags
  sku: {
    name: 'Standard_B1ms'
    tier: 'Burstable'
  }
  properties: {
    version: '8.0.21'
    administratorLogin: mysqlAdminLogin
    administratorLoginPassword: mysqlAdminPassword
    storage: {
      storageSizeGB: 32
      autoGrow: 'Disabled'
    }
    // Copias automáticas de Azure con restauración a un punto en el tiempo (RPO de minutos).
    backup: {
      backupRetentionDays: 7
      geoRedundantBackup: 'Disabled'
    }
    network: {
      delegatedSubnetResourceId: '${vnet.id}/subnets/snet-mysql'
      privateDnsZoneResourceId: mysqlDns.id
    }
    highAvailability: {
      mode: 'Disabled'
    }
  }
  dependsOn: [
    mysqlDnsLink
  ]
}

resource database 'Microsoft.DBforMySQL/flexibleServers/databases@2023-12-30' = {
  parent: mysql
  name: 'consultorio'
  properties: {
    charset: 'utf8mb4'
    collation: 'utf8mb4_unicode_ci'
  }
}

// consultorio_despliegue crea funciones y triggers (database/sql/mysql) sin SUPER: lo pide seguridad.sql.
resource trustFunctionCreators 'Microsoft.DBforMySQL/flexibleServers/configurations@2023-12-30' = {
  parent: mysql
  name: 'log_bin_trust_function_creators'
  properties: {
    value: 'ON'
    source: 'user-override'
  }
}

// ---------- Key Vault: los secretos del .env ----------

resource vault 'Microsoft.KeyVault/vaults@2023-07-01' = {
  name: 'kv-${prefix}-${suffix}'
  location: location
  tags: tags
  properties: {
    tenantId: subscription().tenantId
    sku: {
      family: 'A'
      name: 'standard'
    }
    enableRbacAuthorization: true
    enableSoftDelete: true
    softDeleteRetentionInDays: 7
  }
}

module operatorWritesSecrets '../modules/role.bicep' = {
  name: 'operador-escribe-secretos'
  params: {
    kind: 'keyvault'
    resourceName: vault.name
    principalId: operatorObjectId
    roleId: roles.keyVaultSecretsOfficer
    principalType: 'User'
  }
}

// ---------- Blob para las copias ----------

resource storage 'Microsoft.Storage/storageAccounts@2023-05-01' = {
  name: 'st${prefix}${suffix}'
  location: location
  tags: tags
  kind: 'StorageV2'
  sku: {
    name: 'Standard_LRS'
  }
  properties: {
    accessTier: 'Cool'
    minimumTlsVersion: 'TLS1_2'
    allowBlobPublicAccess: false
    supportsHttpsTrafficOnly: true
  }
}

resource blobService 'Microsoft.Storage/storageAccounts/blobServices@2023-05-01' = {
  parent: storage
  name: 'default'
  properties: {
    isVersioningEnabled: true
    deleteRetentionPolicy: {
      enabled: true
      days: 14
    }
    containerDeleteRetentionPolicy: {
      enabled: true
      days: 14
    }
  }
}

resource backupContainer 'Microsoft.Storage/storageAccounts/blobServices/containers@2023-05-01' = {
  parent: blobService
  name: 'respaldos'
  properties: {
    publicAccess: 'None'
  }
}

// Paquetes que sube GitHub Actions para que vm-app los publique.
resource deployContainer 'Microsoft.Storage/storageAccounts/blobServices/containers@2023-05-01' = {
  parent: blobService
  name: 'despliegues'
  properties: {
    publicAccess: 'None'
  }
}

// ---------- GitHub Actions publica sin claves (OIDC con identidad administrada) ----------

resource githubIdentity 'Microsoft.ManagedIdentity/userAssignedIdentities@2023-01-31' = if (!empty(githubRepo)) {
  name: 'id-github-despliegue'
  location: location
  tags: tags
}

// Solo los flujos de la rama main de ese repositorio pueden usar esta identidad.
resource githubFederation 'Microsoft.ManagedIdentity/userAssignedIdentities/federatedIdentityCredentials@2023-01-31' = if (!empty(githubRepo)) {
  parent: githubIdentity
  name: 'github-main'
  properties: {
    issuer: 'https://token.actions.githubusercontent.com'
    subject: 'repo:${githubRepo}:ref:refs/heads/main'
    audiences: [
      'api://AzureADTokenExchange'
    ]
  }
}

// El mismo permiso para el formato nuevo del token de GitHub, que identifica dueño y repositorio por sus números.
resource githubFederationIds 'Microsoft.ManagedIdentity/userAssignedIdentities/federatedIdentityCredentials@2023-01-31' = if (!empty(githubRepo) && !empty(githubOidcSubject)) {
  parent: githubIdentity
  name: 'github-main-ids'
  properties: {
    issuer: 'https://token.actions.githubusercontent.com'
    subject: githubOidcSubject
    audiences: [
      'api://AzureADTokenExchange'
    ]
  }
  // Azure no admite crear dos credenciales de la misma identidad a la vez.
  dependsOn: [
    githubFederation
  ]
}

// Ejecutar el script de publicación en vm-app (az vm run-command) y nada más que manejar las VM del grupo.
var virtualMachineContributor = '9980e02c-c2be-4d73-94e8-173b1dc7cf3c'

resource githubRunsCommands 'Microsoft.Authorization/roleAssignments@2022-04-01' = if (!empty(githubRepo)) {
  name: guid(resourceGroup().id, 'id-github-despliegue', virtualMachineContributor)
  properties: {
    roleDefinitionId: subscriptionResourceId('Microsoft.Authorization/roleDefinitions', virtualMachineContributor)
    principalId: githubIdentity!.properties.principalId
    principalType: 'ServicePrincipal'
  }
}

module githubUploadsPackages '../modules/role.bicep' = if (!empty(githubRepo)) {
  name: 'github-sube-paquetes'
  params: {
    kind: 'storage'
    resourceName: storage.name
    principalId: githubIdentity!.properties.principalId
    roleId: roles.blobDataContributor
  }
}

// ---------- VM (segunda pasada) ----------

// ---------- Firewall (vm-fw) ----------

module firewall '../modules/firewall-vm.bicep' = {
  name: 'vm-fw'
  params: {
    role: 'produccion'
    location: location
    wanSubnetId: '${vnet.id}/subnets/snet-wan'
    lanSubnetId: '${vnet.id}/subnets/snet-lan'
    wanIp: '10.20.0.4'
    lanIp: firewallLanIp
    lanGateway: '10.20.1.1'
    internalPrefixes: [
      dmzPrefix
      appPrefix
    ]
    nftRules: loadTextContent('../../firewall/produccion.nft')
    adminUsername: adminUsername
    sshPublicKey: sshPublicKey
    tags: tags
  }
}

module proxy '../modules/linux-vm.bicep' = if (deployVms) {
  name: 'vm-proxy'
  params: {
    name: 'vm-proxy'
    location: location
    subnetId: '${vnet.id}/subnets/snet-dmz'
    privateIp: proxyIp
    size: proxyVmSize
    adminUsername: adminUsername
    sshPublicKey: sshPublicKey
    customData: loadTextContent('../../cloud-init/proxy.yaml')
    osDiskSizeGB: 30
    osDiskType: 'StandardSSD_LRS'
    autoShutdownTime: autoShutdownTime
    tags: tags
  }
  dependsOn: [
    firewall
  ]
}

module app '../modules/linux-vm.bicep' = if (deployVms) {
  name: 'vm-app'
  params: {
    name: 'vm-app'
    location: location
    subnetId: '${vnet.id}/subnets/snet-app'
    privateIp: appIp
    size: appVmSize
    adminUsername: adminUsername
    sshPublicKey: sshPublicKey
    customData: loadTextContent('../../cloud-init/app.yaml')
    osDiskSizeGB: 30
    osDiskType: 'StandardSSD_LRS'
    systemIdentity: true
    autoShutdownTime: autoShutdownTime
    tags: tags
  }
  dependsOn: [
    firewall
  ]
}

// vm-app lee sus secretos y escribe sus copias con su identidad administrada: no hay claves en el servidor.
module appReadsSecrets '../modules/role.bicep' = if (deployVms) {
  name: 'vm-app-lee-secretos'
  params: {
    kind: 'keyvault'
    resourceName: vault.name
    principalId: app!.outputs.principalId
    roleId: roles.keyVaultSecretsUser
  }
}

module appWritesBackups '../modules/role.bicep' = if (deployVms) {
  name: 'vm-app-escribe-respaldos'
  params: {
    kind: 'storage'
    resourceName: storage.name
    principalId: app!.outputs.principalId
    roleId: roles.blobDataContributor
  }
}

// ---------- Alertas ----------

resource cpuAlert 'Microsoft.Insights/metricAlerts@2018-03-01' = if (deployVms) {
  name: 'alerta-cpu-vm-app'
  location: 'global'
  tags: tags
  properties: {
    description: 'La CPU de vm-app pasó el 80 % durante 15 minutos.'
    severity: 2
    enabled: true
    scopes: [
      resourceId('Microsoft.Compute/virtualMachines', 'vm-app')
    ]
    evaluationFrequency: 'PT5M'
    windowSize: 'PT15M'
    criteria: {
      'odata.type': 'Microsoft.Azure.Monitor.SingleResourceMultipleMetricCriteria'
      allOf: [
        {
          criterionType: 'StaticThresholdCriterion'
          name: 'cpu'
          metricName: 'Percentage CPU'
          operator: 'GreaterThan'
          threshold: 80
          timeAggregation: 'Average'
        }
      ]
    }
    actions: [
      {
        actionGroupId: actionGroup.id
      }
    ]
  }
  dependsOn: [
    app
  ]
}

resource downAlert 'Microsoft.Insights/metricAlerts@2018-03-01' = if (deployVms) {
  name: 'alerta-vm-caida'
  location: 'global'
  tags: tags
  properties: {
    description: 'vm-app o vm-proxy dejó de estar disponible.'
    severity: 1
    enabled: true
    scopes: [
      resourceId('Microsoft.Compute/virtualMachines', 'vm-app')
      resourceId('Microsoft.Compute/virtualMachines', 'vm-proxy')
    ]
    targetResourceType: 'Microsoft.Compute/virtualMachines'
    targetResourceRegion: location
    evaluationFrequency: 'PT1M'
    windowSize: 'PT5M'
    criteria: {
      'odata.type': 'Microsoft.Azure.Monitor.MultipleResourceMultipleMetricCriteria'
      allOf: [
        {
          criterionType: 'StaticThresholdCriterion'
          name: 'disponibilidad'
          metricName: 'VmAvailabilityMetric'
          metricNamespace: 'Microsoft.Compute/virtualMachines'
          operator: 'LessThan'
          threshold: 1
          timeAggregation: 'Average'
        }
      ]
    }
    actions: [
      {
        actionGroupId: actionGroup.id
      }
    ]
  }
  dependsOn: [
    app
    proxy
  ]
}

output firewallPublicIp string = firewall.outputs.publicIp
output mysqlHost string = mysql.properties.fullyQualifiedDomainName
output keyVault string = vault.name
output storageAccount string = storage.name
output vnetName string = vnet.name
output githubClientId string = empty(githubRepo) ? '' : githubIdentity!.properties.clientId
output tenantId string = subscription().tenantId
output subscriptionId string = subscription().subscriptionId
output logAnalyticsWorkspace string = law.name
