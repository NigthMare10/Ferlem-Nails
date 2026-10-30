# Pruebas locales — Studio Lemus

## Arranque seguro en Windows

Requiere PHP **8.3 o superior**, PDO SQLite, GD, Composer y Node. El PHP predeterminado de XAMPP instalado en esta máquina es 8.2; los scripts seleccionan otro PHP compatible del PATH.

```powershell
npm install
npm run build
.\scripts\local-qa.ps1
```

El script usa exclusivamente `storage/app/qa.sqlite`, crea `.env.qa` si falta y prepara datos ficticios. **No modifica `.env`, no usa una copia de la base de producción y no hace deploy.** No debe utilizarse esta base de demostración como base real del negocio.

- Administración: `http://127.0.0.1:8010/login`
- Portal de clientas: `http://127.0.0.1:8010/clientes/login`
- Registro: `http://127.0.0.1:8010/clientes/registro`

| Cuenta ficticia | Correo | Contraseña solo para QA |
|---|---|---|
| Propietaria | `admin@lemus.test` | `LocalTesting123!` |
| Empleada | `empleada@lemus.test` | `LocalTesting123!` |
| Clienta | `clienta@lemus.test` | `LocalTesting123!` |

El correo de QA se escribe en `storage/logs/laravel.log`, no se envía a personas reales. Los enlaces de verificación y recuperación se pueden abrir desde ese registro. No usar estas credenciales fuera de QA.

## Ejecutar comprobaciones

```powershell
.\scripts\test-local.ps1
npm run typecheck
npm run build
npx playwright install chromium
npm run test:browser
```

Mantener el servidor QA abierto para Playwright. Los tests PHP tienen una protección que impide ejecutarlos contra una base distinta de SQLite en memoria. Las pruebas de navegador sí modifican los datos ficticios de QA: crean ventas, acreditan puntos, editan la cita de muestra y configuran a la empleada de prueba.

Informes de navegador: `playwright-report/index.html`. Capturas y trazas: `test-results/`. Ambos directorios están excluidos de Git.

## Recorridos para revisión manual

1. **Servicios:** crear/editar servicio, marcar «Es promoción» y configurar puntos por unidad. Revisar separación de pestañas en nueva venta.
2. **Descuentos:** agregar un servicio, abrir «Descuentos individuales por servicio», seleccionar porcentaje o monto por línea y escribir motivo. Revisar resumen, confirmación y comprobante. El descuento frecuente y el individual no se combinan.
3. **Empleados:** Usuarios → menú de la persona → «Permisos, orden y comida». Menor orden aparece primero; los empates se ordenan por nombre. Los permisos heredados se ven marcados y no pueden quitarse en este formulario.
4. **Delegación:** habilitar «Cobrar servicios de cualquier empleado». Entrar con la empleada y comprobar que puede elegir quién atendió sin recibir acceso a reportes o nómina. Para consultar agenda ajena debe recibir también el permiso de ver todas las citas; no se concede implícitamente.
5. **Comidas:** configurar días recurrentes o excepciones por fecha. La agenda muestra el bloqueo. Crear, editar o reprogramar dentro de comida debe fallar. Una cita que termina exactamente al iniciar comida es válida.
6. **Editar cita:** la cita ficticia «Cita QA editable» está el 7 de enero de 2030. «Editar servicios» permite quitar/agregar servicios, cambiar personal autorizado, cantidad y duración. Después de guardar se vuelve a la agenda de ese día.
7. **Concurrencia:** abrir una cita en dos pestañas, modificar en una y guardar la otra. La segunda debe pedir recargar sin sobrescribir la primera.
8. **Clientas:** usar otro navegador o ventana privada para comprobar aislamiento de sesiones. Registrar una cuenta, verificar correo, configurar foto/perfil y consultar únicamente sus citas y movimientos.
9. **QR antes de cobrar:** «Vincular clienta» en la agenda asocia la cita al perfil verificado; no entrega puntos anticipados. Se acreditan al completar y cobrar.
10. **QR después de cobrar:** «Asignar puntos» en el comprobante o detalle de cita cobrada abre el lector. Confirmar identidad antes de acreditar. Repetir la acreditación no duplica movimientos. Si no hay cámara, usar el código temporal alternativo.
11. **Recompensas:** configurar costo, tipo de beneficio, disponibilidad, stock, límite por clienta y cumpleaños. La clienta reserva; el personal autorizado confirma entrega o cancela la reserva. Registrar en caja el descuento correspondiente cuando el beneficio sea un descuento/servicio gratuito; la confirmación de entrega no modifica una factura ya cobrada.
12. **Cancelación de venta:** después de acreditar puntos, anular la venta y comprobar el movimiento inverso. Si los puntos ya se gastaron, una deuda bloquea nuevos canjes; no se borra el historial.

## Reglas de fidelidad

- Las cuentas de clientas no son usuarios administrativos; usan proveedor, guard y tabla de recuperación separados.
- Los puntos del servicio se copian al vender. Cambiar el catálogo no recalcula ventas pasadas. Los servicios existentes empiezan con cero puntos hasta configurarlos.
- Cada línea elegible se acredita una única vez. Una línea completamente gratuita no genera puntos. Adelantos, cargos adicionales y citas pendientes tampoco.
- El saldo disponible y los puntos calificables para nivel son distintos. Canjear no baja de nivel. Los ajustes extraordinarios de saldo no promueven automáticamente de nivel.
- El QR contiene un token aleatorio sin datos personales, vence en cinco minutos y se consume al confirmar una asociación. No sirve para iniciar sesión.
- Las reservas de recompensas vencen a las 24 horas y liberan saldo/stock una sola vez. `php artisan loyalty:expire` permite procesarlas manualmente; el calendario de tareas lo programa cada cinco minutos. La reserva también revisa vencimientos de la propia cuenta.
- Los movimientos de puntos y líneas de ventas no se editan ni eliminan: se corrigen con movimientos compensatorios.
- Las fotos se validan, reprocesan a WebP, guardan de forma privada y solo se sirven a su propietaria autenticada.

## Mapa del código

- Permisos, orden y comida: `EmployeeOptionsController`, `EmployeeAvailability`, `employee_breaks`, `security_audits`.
- Edición segura: `EditAppointmentServicesAction`, `AppointmentRevision`, `Appointments/Edit.vue`.
- Cálculos: `ItemDiscount`, `SaleFinancials`, `PersistCompletedSaleAction`.
- Clientas: `routes/customer.php`, `CustomerAccess`, controladores `Customer/`, `CustomerLayout.vue`.
- Fidelidad: `LoyaltyService`, `LoyaltyController`, `loyalty_entries`, `loyalty_rewards`, `loyalty_redemptions`, `customer_qr_tokens`.
- Pruebas nuevas: `tests/Feature/ExtendedOperationsTest.php` y `tests/browser/operations.spec.ts`.

Las rutas de orden y comida se consolidaron en `PUT /configuration/users/{user}/breaks`; el catálogo de miembros y recompensas está en `/configuration/loyalty/rewards`. No es necesario un endpoint independiente para cada pestaña.

## Límites de esta validación

- Chromium de escritorio y emulación móvil no equivalen a Safari de iPhone ni a Android físico. El escaneo con cámara real y los gestos del equipo donde ocurría el bloqueo requieren comprobación física. Sí se prueba el manejo de cámara no disponible y el código alternativo.
- Los tests de datos usan SQLite. Los bloqueos transaccionales están implementados, pero la contención real de dos conexiones MySQL debe verificarse antes de publicar.
- No se hizo una medición de Core Web Vitals en Hostinger ni se afirma haber alcanzado tiempos específicos de red real. Se dividió el frontend por páginas, se difirió cámara/QR y se redujeron consultas de disponibilidad y efectos costosos en móvil.
- El perfil implementa la composición, paleta, niveles y navegación de la referencia con datos reales de la cuenta. Las fotografías de ejemplo no se incorporaron como si fueran clientas del negocio; la cuenta puede cargar su propia foto. No hay una aprobación de igualdad visual píxel por píxel.
- No se enviaron correos reales ni se desplegaron estos cambios.

## Antes de cualquier publicación futura

Revisar dependencias y copias de seguridad, validar recuperación, MySQL y dispositivos físicos, configurar correo real/HTTPS y el scheduler. No ejecutar el seeder QA en producción. La reversión de la migración que permite líneas con descuentos distintos se bloquea si ya existen duplicados históricos: **no consolidar ni borrar ventas para forzar el rollback**.
