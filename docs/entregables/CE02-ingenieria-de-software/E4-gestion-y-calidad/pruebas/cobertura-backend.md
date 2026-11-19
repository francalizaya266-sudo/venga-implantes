# Cobertura del backend (pcov)

Suite completa sobre la rama del E4 el 19/11/2026: 1 097 pruebas y 10 555 verificaciones en verde, 386 s con cobertura (`php artisan test --coverage-clover`). Líneas: **96,10 %** (23 354 de 24 302). Métodos: 85,83 % (2 514 de 2 929). Es la misma tabla que la CI escribe en el resumen del job "Backend" y el reporte HTML completo queda como artefacto `cobertura-backend`.

| Carpeta | Líneas | Cubiertas | % |
| --- | ---: | ---: | ---: |
| `app/Filament/Resources` | 8 533 | 8 151 | 95,5 |
| `app/Filament/Pages` | 1 686 | 1 671 | 99,1 |
| `app/Filament/Actions` | 1 477 | 1 448 | 98,0 |
| `app/Services/Clinical` | 1 371 | 1 319 | 96,2 |
| `app/Models` | 1 196 | 1 143 | 95,6 |
| `app/Filament/Widgets` | 1 125 | 1 112 | 98,8 |
| `app/Filament/Support` | 1 115 | 1 097 | 98,4 |
| `app/Http/Controllers` | 1 053 | 1 045 | 99,2 |
| `app/Services/Booking` | 836 | 809 | 96,8 |
| `app/Services/Billing` | 578 | 567 | 98,1 |
| `app/Enums` | 541 | 479 | 88,5 |
| `app/Support` | 533 | 509 | 95,5 |
| `app/Services/Reports` | 477 | 476 | 99,8 |
| `app/Services/Inventory` | 425 | 414 | 97,4 |
| `app/Services/Agenda` | 400 | 392 | 98,0 |
| `app/Services/Web` | 392 | 381 | 97,2 |
| `app/Filament/Forms` | 309 | 305 | 98,7 |
| `app/Livewire` | 281 | 260 | 92,5 |
| `app/Services/Backup` | 253 | 246 | 97,2 |
| `app/Policies` | 240 | 150 | 62,5 |
| `app/Services/Patients` | 237 | 223 | 94,1 |
| `app/Services/Dashboard` | 227 | 224 | 98,7 |
| `app/Http/Resources` | 189 | 189 | 100,0 |
| `app/Services/WhatsApp` | 158 | 139 | 88,0 |
| `app/Console/Commands` | 124 | 107 | 86,3 |
| `app/Providers/Filament` | 116 | 116 | 100,0 |
| `app/Services/Access` | 104 | 97 | 93,3 |
| `app/Http/Requests` | 85 | 85 | 100,0 |
| `app/Services/Database` | 61 | 26 | 42,6 |
| `app/Exceptions` | 51 | 46 | 90,2 |
| `app/Models/Concerns` | 38 | 38 | 100,0 |
| `app/Services` | 26 | 25 | 96,2 |
| `app/Http/Middleware` | 23 | 23 | 100,0 |
| `app/Providers` | 23 | 23 | 100,0 |
| `app/Filament/Concerns` | 19 | 19 | 100,0 |
