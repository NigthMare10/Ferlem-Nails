# Correcciones de caja, agenda y fidelidad

## Cambios

- Los cargos adicionales usan campos apilados dentro del panel, no columnas que se superponen. QA comprueba las coordenadas de cada campo.
- Cada servicio del carrito incluye su editor de descuento, también en el resumen móvil y al cobrar citas. El cálculo, motivo y control de permisos siguen en el servidor.
- Editar servicios abre una modal sobre la agenda con carga/errores, formulario protegido durante el guardado y validación de revisión. La antigua URL redirige al día con la modal abierta; no existe una página independiente.
- Fidelidad se organiza en catálogo, historial de canjes y niveles. Crear/editar recompensas se hace en una modal con selección de servicios por nombre.
- «Ver clientas» permite buscar por nombre, correo o teléfono. Cada perfil muestra saldo, progreso, visitas, movimientos, citas y canjes paginados.
- Propietaria y administradores pueden reservar para una clienta activa/verificada. Se valida saldo, cumpleaños, vigencia, límite y stock exactamente como en el portal. Se registra quién reservó y quién entregó; los empleados requieren permisos explícitos.
- El canje aparece tanto en el perfil administrativo como en el historial global y en «Mis canjes» de la clienta. Estados: pendiente de entrega, entregada, cancelada y vencida.
- Reservar y entregar son pasos separados con confirmación. Cancelar una reserva devuelve puntos/stock una sola vez. Una entrega no se revierte como reserva. La entrega no altera facturas ya emitidas.

## Diseño y validación

Se revisaron `DESIGN.md` y las referencias Operate/Harden de Impeccable incluidas en `main`. Se preserva Pearl Ledger, con jerarquía por tarea, estados explícitos, formularios enfocados y controles de Vuetify. El detector mecánico sobre las nuevas superficies terminó sin hallazgos.

La integración incluye los cambios remotos de `main`, no sustituye la rama por la copia local antigua. Se conservan los tests adicionales de gastos, nómina, cierre diario y ajustes de ventas.

- PHP: **382 pruebas, 3,578 assertions**, sin fallos ni errores.
- Navegador: **20 pruebas** Chromium escritorio/móvil, incluidos cargos sin superposición, descuento, modal y reserva/entrega administrativa.
- TypeScript/build y auditorías Composer/npm comprobados.
- Cámara física, Safari y contención MySQL real no se simulan como aprobados.

## Publicación

El flujo aprobado usa `build-reviewed-release.ps1` y `publish-reviewed-release.py`, con clave SSH fijada, dependencias `--no-dev`, sin `.env`, SQLite, storage, cachés locales ni seeder QA en el paquete.

`deploy-reviewed-release.sh` crea y verifica respaldos privados de MySQL y archivos/storage antes de migrar. Conserva `.env`, APP_KEY, archivos privados y datos del negocio. Compara conteos existentes antes/después y realiza smoke tests públicos. No ejecuta seeders de demostración ni `migrate:fresh`.

El resultado efectivo y la ruta del respaldo se registran al terminar la publicación; este documento por sí solo no certifica un deploy.
