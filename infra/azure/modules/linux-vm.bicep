// Una VM Linux (Ubuntu 24.04) con IP privada fija, acceso solo con llave SSH y, si se pide,
// IP pública, identidad administrada y apagado automático diario.

@description('Nombre de la VM, por ejemplo vm-app.')
param name string

param location string = resourceGroup().location

@description('Id de la subred donde va la VM.')
param subnetId string

@description('IP privada fija dentro de la subred.')
param privateIp string

param size string = 'Standard_B1s'

param adminUsername string

@description('Llave pública SSH (contenido de id_ed25519.pub).')
param sshPublicKey string

@description('cloud-init en texto plano; vacío para no usar.')
param customData string = ''

param osDiskSizeGB int = 30

@allowed([
  'Standard_LRS'
  'StandardSSD_LRS'
  'Premium_LRS'
])
param osDiskType string = 'Standard_LRS'

@description('Id de una IP pública para asociar; vacío para ninguna.')
param publicIpId string = ''

@description('Crea una identidad administrada (para leer y escribir en Blob sin claves).')
param systemIdentity bool = false

@description('Hora de apagado diario en formato HHmm, hora de Lima; vacío para no apagar.')
param autoShutdownTime string = ''

param tags object = {}

resource nic 'Microsoft.Network/networkInterfaces@2024-05-01' = {
  name: 'nic-${name}'
  location: location
  tags: tags
  properties: {
    ipConfigurations: [
      {
        name: 'ipconfig1'
        properties: {
          subnet: {
            id: subnetId
          }
          privateIPAllocationMethod: 'Static'
          privateIPAddress: privateIp
          publicIPAddress: empty(publicIpId) ? null : {
            id: publicIpId
          }
        }
      }
    ]
  }
}

resource vm 'Microsoft.Compute/virtualMachines@2024-07-01' = {
  name: name
  location: location
  tags: tags
  identity: systemIdentity ? { type: 'SystemAssigned' } : null
  properties: {
    hardwareProfile: {
      vmSize: size
    }
    osProfile: {
      computerName: name
      adminUsername: adminUsername
      customData: empty(customData) ? null : base64(customData)
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
        diskSizeGB: osDiskSizeGB
        deleteOption: 'Delete'
        managedDisk: {
          storageAccountType: osDiskType
        }
      }
    }
    networkProfile: {
      networkInterfaces: [
        {
          id: nic.id
          properties: {
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
output name string = vm.name
output privateIp string = privateIp
output principalId string = vm.?identity.?principalId ?? ''
