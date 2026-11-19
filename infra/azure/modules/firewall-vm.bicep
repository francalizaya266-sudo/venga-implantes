// vm-fw: firewall Linux con dos tarjetas (WAN con IP pública y LAN), configurado por cloud-init con las reglas
// de nftables del repositorio. Cumple el papel que el E1 da a OPNsense, sin pasos manuales.

param name string = 'vm-fw'

param location string = resourceGroup().location

@allowed([
  'produccion'
  'sede'
])
param role string

param wanSubnetId string

param lanSubnetId string

param wanIp string

param lanIp string

@description('Puerta de enlace de la subred LAN (la .1, el router de Azure).')
param lanGateway string

@description('Subredes internas a las que se llega por la tarjeta LAN.')
param internalPrefixes array

@description('Reglas de nftables (contenido de infra/firewall/*.nft).')
param nftRules string

@description('Solo sede: dominio que el DNS interno resuelve al proxy de producción.')
param siteDomain string = ''

@description('Solo sede: IP privada del proxy de producción.')
param prodProxyIp string = '10.20.3.4'

@description('2 vCPU y 1 GiB con 2 GiB de swap. Si Suricata se queda corto, Standard_B2als_v2 (4 GiB).')
param size string = 'Standard_B2ats_v2'

param adminUsername string

param sshPublicKey string

@description('Hora de apagado diario (HHmm, hora de Lima); vacío para no apagar.')
param autoShutdownTime string = ''

param tags object = {}

var netplan = 'network:\n  version: 2\n  ethernets:\n    eth1:\n      routes:\n${join(map(internalPrefixes, p => '        - to: ${p}\n          via: ${lanGateway}\n'), '')}'

var unbound = role == 'sede' ? 'server:\n  interface: ${lanIp}\n  interface-automatic: no\n  ip-freebind: yes\n  access-control: 10.10.0.0/16 allow\n  local-zone: "${siteDomain}." redirect\n  local-data: "${siteDomain}. 300 IN A ${prodProxyIp}"\n\nforward-zone:\n  name: "."\n  forward-addr: 168.63.129.16\n' : '# Sin DNS interno en producción.\n'

var template = loadTextContent('../../cloud-init/firewall.yaml')
var withRules = replace(template, '__NFT_B64__', base64(nftRules))
var withRoutes = replace(withRules, '__NETPLAN_B64__', base64(netplan))
var withDns = replace(withRoutes, '__UNBOUND_B64__', base64(unbound))
var cloudInit = replace(withDns, '__ROL__', role)

resource pip 'Microsoft.Network/publicIPAddresses@2024-05-01' = {
  name: 'pip-fw'
  location: location
  tags: tags
  sku: {
    name: 'Standard'
  }
  properties: {
    publicIPAllocationMethod: 'Static'
  }
}

resource nicWan 'Microsoft.Network/networkInterfaces@2024-05-01' = {
  name: 'nic-${name}-wan'
  location: location
  tags: tags
  properties: {
    enableIPForwarding: true
    // El firewall usa el DNS de Azure: la red de la sede apunta su DNS a este mismo firewall (Unbound).
    dnsSettings: {
      dnsServers: [
        '168.63.129.16'
      ]
    }
    ipConfigurations: [
      {
        name: 'wan'
        properties: {
          subnet: {
            id: wanSubnetId
          }
          privateIPAllocationMethod: 'Static'
          privateIPAddress: wanIp
          publicIPAddress: {
            id: pip.id
          }
        }
      }
    ]
  }
}

resource nicLan 'Microsoft.Network/networkInterfaces@2024-05-01' = {
  name: 'nic-${name}-lan'
  location: location
  tags: tags
  properties: {
    enableIPForwarding: true
    // El firewall usa el DNS de Azure: la red de la sede apunta su DNS a este mismo firewall (Unbound).
    dnsSettings: {
      dnsServers: [
        '168.63.129.16'
      ]
    }
    ipConfigurations: [
      {
        name: 'lan'
        properties: {
          subnet: {
            id: lanSubnetId
          }
          privateIPAllocationMethod: 'Static'
          privateIPAddress: lanIp
        }
      }
    ]
  }
}

resource vm 'Microsoft.Compute/virtualMachines@2024-07-01' = {
  name: name
  location: location
  tags: tags
  properties: {
    hardwareProfile: {
      vmSize: size
    }
    osProfile: {
      computerName: name
      adminUsername: adminUsername
      customData: base64(cloudInit)
      linuxConfiguration: {
        disablePasswordAuthentication: true
        ssh: {
          publicKeys: [
            {
              path: '/home/${adminUsername}/.ssh/authorized_keys'
              keyData: sshPublicKey
            }
          ]
        }
      }
    }
    storageProfile: {
      imageReference: {
        publisher: 'Canonical'
        offer: 'ubuntu-24_04-lts'
        sku: 'server'
        version: 'latest'
      }
      osDisk: {
        name: 'osdisk-${name}'
        createOption: 'FromImage'
        diskSizeGB: 30
        deleteOption: 'Delete'
        managedDisk: {
          storageAccountType: 'StandardSSD_LRS'
        }
      }
    }
    networkProfile: {
      networkInterfaces: [
        {
          id: nicWan.id
          properties: {
            primary: true
            deleteOption: 'Delete'
          }
        }
        {
          id: nicLan.id
          properties: {
            primary: false
            deleteOption: 'Delete'
          }
        }
      ]
    }
    diagnosticsProfile: {
      bootDiagnostics: {
        enabled: true
      }
    }
  }
}

resource shutdown 'Microsoft.DevTestLab/schedules@2018-09-15' = if (!empty(autoShutdownTime)) {
  name: 'shutdown-computevm-${name}'
  location: location
  tags: tags
  properties: {
    status: 'Enabled'
    taskType: 'ComputeVmShutdownTask'
    dailyRecurrence: {
      time: autoShutdownTime
    }
    timeZoneId: 'SA Pacific Standard Time'
    notificationSettings: {
      status: 'Disabled'
    }
    targetResourceId: vm.id
  }
}

output id string = vm.id
output publicIp string = pip.properties.ipAddress
