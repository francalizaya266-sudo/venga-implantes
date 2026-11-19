// Alerta de presupuesto de la suscripción: avisa por correo al 50 % y al 80 % del monto mensual.
//
//   az deployment sub create -l brazilsouth -f infra/azure/presupuesto.bicep \
//     -p alertEmail='correo-del-equipo@...' startDate='2026-12-01'
//
// Si tu tipo de suscripción no admite presupuestos, el saldo del crédito se revisa en
// https://www.microsoftazuresponsorships.com/balance
targetScope = 'subscription'

@description('Monto mensual en USD. Con 100 USD para unos 2,5 meses, 40 al mes deja margen.')
param amount int = 40

@description('Primer día del mes en que empieza el presupuesto (AAAA-MM-01).')
param startDate string

param alertEmail string

resource budget 'Microsoft.Consumption/budgets@2023-11-01' = {
  name: 'presupuesto-vega'
  properties: {
    category: 'Cost'
    amount: amount
    timeGrain: 'Monthly'
    timePeriod: {
      startDate: startDate
    }
    notifications: {
      al50: {
        enabled: true
        operator: 'GreaterThanOrEqualTo'
        threshold: 50
        thresholdType: 'Actual'
        contactEmails: [
          alertEmail
        ]
      }
      al80: {
        enabled: true
        operator: 'GreaterThanOrEqualTo'
        threshold: 80
        thresholdType: 'Actual'
        contactEmails: [
          alertEmail
        ]
      }
    }
  }
}
