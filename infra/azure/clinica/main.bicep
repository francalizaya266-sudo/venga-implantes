// Sede de la clínica simulada: la red vnet-clinica con sus subredes (cada una equivale a una VLAN
// del diseño), las tablas de rutas que obligan a pasar por el firewall (vm-fw), los NSG y las VM de laboratorio.
// Se despliega en el grupo de recursos rg-vega-clinica, en dos pasadas:
//
// Todo sale en una sola pasada: el firewall Linux (infra/firewall/sede.nft) se configura solo con cloud-init y las
// demás VM esperan a que dé salida a internet y DNS antes de instalarse. Con deployVms=false solo red y firewall.
//
//   az deployment group create -g rg-vega-clinica -f infra/azure/clinica/main.bicep -p infra/azure/clinica/clinica.bicepparam

param location string = resourceGroup().location

param prefix string = 'vega'

param adminUsername string = 'vegaadmin'

@description('Llave pública SSH del equipo.')
param sshPublicKey string

@description('IP de administración separadas por coma (x.x.x.x/32): tu navegador y Cloud Shell. Las únicas que llegan al SSH del firewall y del jump host.')
param adminSourceIps string

@description('Red de producción en Azure (vnet-vega).')
param prodAddressSpace string = '10.20.0.0/16'

@description('IP de la tarjeta LAN del firewall: el siguiente salto de todas las subredes.')
param firewallLanIp string = '10.10.1.4'

@description('IP del servidor Wazuh, al que el honeypot sí puede enviar sus logs.')
param wazuhIp string = '10.10.50.10'

@description('true crea también las VM de laboratorio; false solo la red y el firewall.')
param deployVms bool = true

@description('Dominio de la web: el DNS de la sede lo resuelve al proxy de producción, para llegar al panel por la VPN.')
param siteDomain string

@description('Hora de apagado diario de las VM de laboratorio (HHmm, hora de Lima).')
param autoShutdownTime string = '2000'

var addressSpace = '10.10.0.0/16'
var adminIps = filter(map(split(adminSourceIps, ','), ip => trim(ip)), ip => !empty(ip))
var tags = {
  proyecto: 'vega-implant'
  entorno: 'sede-clinica-simulada'
}

// ---------- NSG ----------

resource nsgWan 'Microsoft.Network/networkSecurityGroups@2024-05-01' = {
  name: 'nsg-wan'
  location: location
  tags: tags
  properties: {
    securityRules: [
      {
        name: 'Permitir-IPsec'
        properties: {
          priority: 100
          direction: 'Inbound'
          access: 'Allow'
          protocol: 'Udp'
          sourceAddressPrefix: 'Internet'
          sourcePortRange: '*'
          destinationAddressPrefix: '*'
          destinationPortRanges: [
            '500'
            '4500'
          ]
        }
      }
      {
        name: 'Permitir-Admin'
        properties: {
          priority: 110
          direction: 'Inbound'
          access: 'Allow'
          protocol: 'Tcp'
          sourceAddressPrefixes: adminIps
          sourcePortRange: '*'
          destinationAddressPrefix: '*'
          // 2200: SSH del firewall. 2222: reenvío al SSH del jump host.
          destinationPortRanges: [
            '2200'
            '2222'
          ]
        }
      }
      {
        name: 'Permitir-Honeypot'
        properties: {
          priority: 120
          direction: 'Inbound'
          access: 'Allow'
          protocol: 'Tcp'
          sourceAddressPrefix: 'Internet'
          sourcePortRange: '*'
          destinationAddressPrefix: '*'
          // El firewall los reenvía al honeypot: cada intento queda registrado en Wazuh.
          destinationPortRanges: [
            '22'
            '3306'
          ]
        }
      }
    ]
  }
}

// Las subredes internas: el filtrado fino lo hace el firewall. Lo que llega de producción por la VPN conserva su
// IP de origen (10.20.x.x), que Azure no considera parte de esta red: hay que permitirlo aquí.
resource nsgInterno 'Microsoft.Network/networkSecurityGroups@2024-05-01' = {
  name: 'nsg-interno'
  location: location
  tags: tags
  properties: {
    securityRules: [
      {
        name: 'Permitir-Produccion-Logs-Y-Copias'
        properties: {
          priority: 100
          direction: 'Inbound'
          access: 'Allow'
          protocol: 'Tcp'
          sourceAddressPrefix: prodAddressSpace
          sourcePortRange: '*'
          destinationAddressPrefix: '10.10.50.0/24'
          // 1514-1515: agentes de Wazuh. 22: copia horaria hacia vm-respaldo.
          destinationPortRanges: [
            '22'
            '1514-1515'
          ]
        }
      }
      {
        // Syslog de producción hacia Wazuh.
        name: 'Permitir-Produccion-Syslog'
        properties: {
          priority: 105
          direction: 'Inbound'
          access: 'Allow'
          protocol: 'Udp'
          sourceAddressPrefix: prodAddressSpace
          sourcePortRange: '*'
          destinationAddressPrefix: wazuhIp
          destinationPortRange: '514'
        }
      }
      {
        // El firewall de la sede entrega por su tarjeta LAN lo que vuelve de producción.
        name: 'Permitir-Produccion-Respuestas'
        properties: {
          priority: 110
          direction: 'Inbound'
          access: 'Allow'
          protocol: '*'
          sourceAddressPrefix: prodAddressSpace
          sourcePortRange: '*'
          destinationAddressPrefix: '10.10.1.0/24'
          destinationPortRange: '*'
        }
      }
    ]
  }
}

// La DMZ no puede iniciar conexiones hacia adentro, salvo enviar sus logs a Wazuh.
resource nsgDmz 'Microsoft.Network/networkSecurityGroups@2024-05-01' = {
  name: 'nsg-dmz'
  location: location
  tags: tags
  properties: {
    securityRules: [
      {
        name: 'Permitir-Logs-Wazuh'
        properties: {
          priority: 100
          direction: 'Outbound'
          access: 'Allow'
          protocol: 'Tcp'
          sourceAddressPrefix: '*'
          sourcePortRange: '*'
          destinationAddressPrefix: wazuhIp
          destinationPortRange: '1514-1515'
        }
      }
      {
        name: 'Bloquear-Hacia-Clinica'
        properties: {
          priority: 110
          direction: 'Outbound'
          access: 'Deny'
          protocol: '*'
          sourceAddressPrefix: '*'
          sourcePortRange: '*'
          destinationAddressPrefix: addressSpace
          destinationPortRange: '*'
        }
      }
      {
        name: 'Bloquear-Hacia-Produccion'
        properties: {
          priority: 120
          direction: 'Outbound'
          access: 'Deny'
          protocol: '*'
          sourceAddressPrefix: '*'
          sourcePortRange: '*'
          destinationAddressPrefix: prodAddressSpace
          destinationPortRange: '*'
        }
      }
    ]
  }
}

// ---------- Tabla de rutas: todo sale por el firewall ----------

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
        // Mismo prefijo que la red: obliga a que el tráfico entre subredes cruce el firewall.
        name: 'clinica-por-firewall'
        properties: {
          addressPrefix: addressSpace
          nextHopType: 'VirtualAppliance'
          nextHopIpAddress: firewallLanIp
        }
      }
      {
        name: 'produccion-por-firewall'
        properties: {
          addressPrefix: prodAddressSpace
          nextHopType: 'VirtualAppliance'
          nextHopIpAddress: firewallLanIp
        }
      }
    ]
  }
}

// ---------- Red ----------

var internalSubnets = [
  {
    name: 'snet-mgmt'
    prefix: '10.10.10.0/24'
    nsg: nsgInterno.id
  }
  {
    name: 'snet-personal'
    prefix: '10.10.30.0/24'
    nsg: nsgInterno.id
  }
  {
    name: 'snet-servidores'
    prefix: '10.10.50.0/24'
    nsg: nsgInterno.id
  }
  {
    name: 'snet-dmz'
    prefix: '10.10.60.0/24'
    nsg: nsgDmz.id
  }
]

resource vnet 'Microsoft.Network/virtualNetworks@2024-05-01' = {
  name: 'vnet-clinica'
  location: location
  tags: tags
  properties: {
    addressSpace: {
      addressPrefixes: [
        addressSpace
      ]
    }
    // Las VM usan el DNS del firewall (Unbound), que resuelve el dominio al proxy de producción por la VPN.
    // El firewall mismo usa el DNS de Azure: su DNS no depende de sí mismo.
    dhcpOptions: {
      dnsServers: [
        firewallLanIp
      ]
    }
    subnets: concat([
      {
        // Tarjeta WAN del firewall, con la IP pública.
        name: 'snet-wan'
        properties: {
          addressPrefix: '10.10.0.0/24'
          networkSecurityGroup: {
            id: nsgWan.id
          }
        }
      }
      {
        // Tarjeta LAN del firewall. Sin tabla de rutas para no crear un bucle.
        name: 'snet-lan'
        properties: {
          addressPrefix: '10.10.1.0/24'
          networkSecurityGroup: {
            id: nsgInterno.id
          }
        }
      }
    ], map(internalSubnets, s => {
      name: s.name
      properties: {
        addressPrefix: s.prefix
        networkSecurityGroup: {
          id: s.nsg
        }
        routeTable: {
          id: rtFirewall.id
        }
      }
    }))
  }
}

// ---------- VM de laboratorio (segunda pasada) ----------

var vms = [
  {
    name: 'vm-wazuh'
    subnet: 'snet-servidores'
    ip: wazuhIp
    // Wazuh necesita 4 GiB.
    size: 'Standard_B2als_v2'
    disk: 64
    cloudInit: loadTextContent('../../cloud-init/wazuh.yaml')
  }
  {
    name: 'vm-respaldo'
    subnet: 'snet-servidores'
    ip: '10.10.50.20'
    size: 'Standard_B2ats_v2'
    disk: 64
    cloudInit: loadTextContent('../../cloud-init/respaldo.yaml')
  }
  {
    name: 'vm-honeypot'
    subnet: 'snet-dmz'
    ip: '10.10.60.10'
    size: 'Standard_B2ats_v2'
    disk: 30
    cloudInit: loadTextContent('../../cloud-init/honeypot.yaml')
  }
  {
    name: 'vm-jump'
    subnet: 'snet-mgmt'
    ip: '10.10.10.20'
    size: 'Standard_B2ats_v2'
    disk: 30
    cloudInit: loadTextContent('../../cloud-init/base.yaml')
  }
  {
    name: 'vm-recepcion'
    subnet: 'snet-personal'
    ip: '10.10.30.10'
    size: 'Standard_B2ats_v2'
    disk: 30
    cloudInit: loadTextContent('../../cloud-init/base.yaml')
  }
]

// ---------- Firewall (vm-fw) ----------

module firewall '../modules/firewall-vm.bicep' = {
  name: 'vm-fw'
  params: {
    role: 'sede'
    location: location
    wanSubnetId: '${vnet.id}/subnets/snet-wan'
    lanSubnetId: '${vnet.id}/subnets/snet-lan'
    wanIp: '10.10.0.4'
    lanIp: firewallLanIp
    lanGateway: '10.10.1.1'
    internalPrefixes: map(internalSubnets, s => s.prefix)
    nftRules: loadTextContent('../../firewall/sede.nft')
    siteDomain: siteDomain
    adminUsername: adminUsername
    sshPublicKey: sshPublicKey
    autoShutdownTime: autoShutdownTime
    tags: tags
  }
}

module labVms '../modules/linux-vm.bicep' = [for vm in vms: if (deployVms) {
  name: vm.name
  params: {
    name: vm.name
    location: location
    subnetId: '${vnet.id}/subnets/${vm.subnet}'
    privateIp: vm.ip
    size: vm.size
    adminUsername: adminUsername
    sshPublicKey: sshPublicKey
    customData: vm.cloudInit
    osDiskSizeGB: vm.disk
    osDiskType: 'Standard_LRS'
    autoShutdownTime: autoShutdownTime
    tags: tags
  }
  dependsOn: [
    firewall
  ]
}]

output firewallPublicIp string = firewall.outputs.publicIp
output vnetName string = vnet.name
output wanSubnetId string = '${vnet.id}/subnets/snet-wan'
output lanSubnetId string = '${vnet.id}/subnets/snet-lan'
