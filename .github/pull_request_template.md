## Qué cambia

<!-- Lo que nota la clínica o el paciente, en sus palabras. Si toca un flujo que la clínica ya usa, dilo aquí. -->

## Cómo se probó

<!-- Pruebas nuevas o cambiadas, y lo que se revisó a mano (pantalla, rol, celular). -->

## Revisión (quien revisa marca cada punto)

- [ ] **Hace lo que dice.** Lo de "Qué cambia" se ve en el código y no hay cambios ajenos al pedido.
- [ ] **Pruebas.** Cada regla nueva o corregida tiene su prueba, y la CI está en verde (backend, MySQL 8 y web).
- [ ] **Permisos.** Lo nuevo del panel pasa por una política; lo clínico solo lo ve quien atiende.
- [ ] **Datos.** Nada se borra de verdad (se anula con motivo); las migraciones nuevas funcionan en MySQL 8.
- [ ] **Errores.** Lo que falla se le dice a la persona en español y sin detalles internos; los servicios externos tienen tiempo de espera.
- [ ] **Seguridad.** Sin claves, `.env` ni datos de pacientes reales en el código, las pruebas o las capturas.
- [ ] **Contrato de la API.** Si cambia `/api/v1`, `docs/api/openapi.json` cambia con ella.
- [ ] **Código.** Sin métodos de más de 60 líneas ni complejidad mayor que 10 en lo nuevo, o con el motivo escrito.
- [ ] **Documentos.** Si cambia un requisito, una regla o el despliegue, el entregable que lo describe se actualiza.
