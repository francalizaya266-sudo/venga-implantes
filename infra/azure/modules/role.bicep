// Da un rol a una identidad sobre una cuenta de almacenamiento o un Key Vault.
// Va en un módulo para que el nombre de la asignación dependa de la identidad: si una VM se vuelve a crear
// (ejercicio de recuperación), su identidad nueva recibe una asignación nueva sin chocar con la anterior.

@allowed([
  'storage'
  'keyvault'
])
param kind string

param resourceName string

param principalId string

@description('Id del rol (GUID).')
param roleId string

@allowed([
  'ServicePrincipal'
  'User'
  'Group'
])
param principalType string = 'ServicePrincipal'

resource storage 'Microsoft.Storage/storageAccounts@2023-05-01' existing = if (kind == 'storage') {
  name: resourceName
}

resource vault 'Microsoft.KeyVault/vaults@2023-07-01' existing = if (kind == 'keyvault') {
  name: resourceName
}

resource onStorage 'Microsoft.Authorization/roleAssignments@2022-04-01' = if (kind == 'storage') {
  name: guid(resourceGroup().id, resourceName, principalId, roleId)
  scope: storage
  properties: {
    roleDefinitionId: subscriptionResourceId('Microsoft.Authorization/roleDefinitions', roleId)
    principalId: principalId
    principalType: principalType
  }
}

resource onVault 'Microsoft.Authorization/roleAssignments@2022-04-01' = if (kind == 'keyvault') {
  name: guid(resourceGroup().id, resourceName, principalId, roleId)
  scope: vault
  properties: {
    roleDefinitionId: subscriptionResourceId('Microsoft.Authorization/roleDefinitions', roleId)
    principalId: principalId
    principalType: principalType
  }
}
