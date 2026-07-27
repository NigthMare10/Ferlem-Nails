# Studio Lemus Frontend Redesign Plan

## Alcance Y Contratos

El plan cambia composición, jerarquía, navegación y componentes visuales sin cambiar endpoints, payloads, reglas de permisos, cálculos, persistencia, estados de negocio ni flujos transaccionales. Cada fase conserva los contratos Inertia existentes y la impresión térmica.

## Hallazgos Que Guían El Plan

- Inicio debe evolucionar de conteos de configuración a cockpit operativo por rol.
- Agenda/citas necesita agrupación progresiva y recuperación explícita ante disponibilidad fallida.
- La navegación debe separar operar, revisar y administrar sin cambiar rutas backend.
- La carga inicial requiere tratar eager imports y fuentes/iconos antes de añadir peso visual.
- Responsive es una base sólida, pero debe normalizar breakpoints y completar targets/etiquetas accesibles.
- El detector actual señala Inter, side-tabs y acentos en bordes redondeados; son restricciones de la implementación futura.

## 7A — Sistema Visual y App Shell

**Estado global: FASE 7A REWORK + 7B VISUAL PASS 1 / En revisión visual.**

**Prompt 1 de 2: Rechazado / Sustituido.**

**Prompt 2 de 2: Implementado para revisión / No desplegado.**

- Tokens OKLCH, paleta Vuetify, foco, elevación y reduced motion: implementados en prueba.
- DM Sans y DM Serif Display: configuradas en prueba.
- AppLayout: sidebar agrupado/colapsable, topbar glass y drawer móvil: implementados en prueba.
- Logo negro/blanco existente: integrado de forma mínima en el shell; se conserva la optimización de derivados como pendiente técnica.
- PageHeader, MetricCard, EmptyState, ConfirmDialog y primitivas base: adaptados/creados en prueba.
- Contratos conservados: rutas actuales, props Inertia, permisos y composición de páginas.
- Validación: 320px, tablet, escritorio, teclado, detector y build.

## 7B — Operación Principal

**Estado: Pendiente / No iniciada.**

- Nueva venta: jerarquía de servicio, carrito, resumen y confirmación de cobro; conservar payload y lógica de pago.
- Resumen móvil: barra operativa y bottom sheet sin perder estado del carrito.
- Facturas: mejorar lectura de estado, filtros y acciones respetando los mismos endpoints.
- Agenda mensual/diaria: continuidad mes→día, targets táctiles y foco de “siguiente cita”.
- Citas: agrupar formulario en cliente, servicios/personal, disponibilidad y adelanto/notas; preservar exactamente solicitudes, validación y estados existentes.
- Historial: priorizar filtros y resultados sin ocultar datos relevantes.

## 7C — Administración

**Estado: Pendiente / No iniciada.**

- Gastos y ganancias: composición de lectura financiera, evitando cuadrículas excesivas de cards.
- Configuración: navegación de sección, usuarios, servicios y compensación con formularios consistentes.
- Mantener tablas en escritorio y cards móviles con la metadata operativa necesaria.
- Contratos conservados: permisos, importes, cálculos, filtros, formularios, endpoints y rutas existentes.

## 7D — Estados y Comunicación

**Estado: Pendiente / No iniciada.**

- Notificaciones: jerarquía, feedback de lectura y microinteracción breve.
- Diálogos: confirmación, cancelación, prueba de transferencia y errores con recuperación concreta.
- Vacíos, loading, error, forbidden y estados de permisos con lenguaje claro.
- Confirmación de cobro y de adelanto sin retrasar tareas.
- Contratos conservados: acciones, validaciones, persistencia y reglas de autorización actuales.

## 7E — Adaptación y Pulido

**Estado: Pendiente / No iniciada.**

- Pruebas completas desde 320px, tablet, escritorio, orientación y zoom.
- Objetivos táctiles, focus, contraste, lectores de pantalla y `prefers-reduced-motion`.
- Normalizar breakpoints, controlar bundle, code-splitting y uso de iconos/fuentes.
- Ejecutar `/impeccable audit`, `/impeccable critique`, `/impeccable adapt`, `/impeccable animate` y `/impeccable polish` sobre objetivos concretos.
- Ejecutar detector final, typecheck, build y pruebas existentes; verificar recibo térmico sin alterar su salida.

## Dirección Seleccionada

**Studio Lemus - Pearl Ledger.** Canvas marfil/perla, navegación clara con glass localizado, contexto dentro del flujo y relieve neomórfico funcional en controles, campos, filtros, métricas y estados. DM Serif Display queda para marca y momentos editoriales; DM Sans gobierna la operación. El login usa el logo como protagonista. El shell ya no tiene topbar fija, eliminando el offset que rompía vistas.

## Alternativas Descartadas

1. **Luxe editorial maximalista:** alta identidad, pero ralentiza cobros y pierde claridad en tablas, formularios y agenda.
2. **Dashboard pastel modular:** fácil de implementar, pero se convierte en SaaS genérico y multiplica cards.
3. **Neo-futurismo oscuro:** atractivo para marketing, pero reduce legibilidad financiera, encarece rendimiento y no corresponde al uso diurno del salón.
4. **El Atelier Operativo inicial:** rechazado por aplicar color y bordes a una estructura SaaS convencional, con sidebar claro genérico, navegación rosa plana, cards anidadas y materialidad insuficiente.

## Riesgos Y Mitigaciones

- **Cambio visual sin pérdida de contratos:** migrar por superficies y comparar props/eventos/payloads antes de cada entrega.
- **Peso de fuentes/logos:** exportar derivados de logo; subsetting y preload medido de fuentes; no cargar assets maestros.
- **Glass/performance:** limitar blur a overlays y topbar, con fallback opaco.
- **Densidad financiera:** probar datos reales y evitar que la reducción móvil elimine estado indispensable.
- **Accesibilidad:** validar contraste, target size, foco y reduced motion en cada fase, no solo al final.
