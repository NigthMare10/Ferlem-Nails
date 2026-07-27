# Studio Lemus Visual System

## Propósito

Este documento aplica la dirección de `DESIGN.md` a la futura implementación. Studio Lemus es una herramienta de operación, no una landing page: cada elección visual debe acelerar una cita, una venta o la comprensión de un dato.

**Implementación actual:** 7B VISUAL FIX + POLISH consolida Pearl Ledger con base blanca/perla, contraste explícito en campos e iconos, branding oscuro de mayor escala sobre shell claro y overlays legibles. La dirección beige anterior se descarta como canvas dominante porque apagaba contenido y reducía la claridad visual.

## Principios

- Operar antes que decorar.
- Una acción principal clara por momento.
- Calidez empresarial: elegante, humana y precisa.
- Jerarquía antes que cantidad de tarjetas.
- Los estados siempre explican qué ocurrió y qué sigue.

## Personalidad Visual

**Studio Lemus - Pearl Ledger** combina la calma material de una recepción clara con el tacto de un objeto operativo premium. La feminidad proviene de marfil ambiental, rosa empolvado, ciruela profunda y contraste editorial; no de ornamentos, brillo continuo ni recursos estereotípicos. La navegación clear-glass y el contexto en cápsula dentro del flujo son la firma del shell; no existe topbar fija.

## Marca Y Logos

Usar el conjunto mínimo necesario:

- `resources/images/studio-lemus-logo-dark.png`: logo para superficies claras, sidebar clara, recibo digital y pantallas administrativas.
- `resources/images/studio-lemus-logo-white.png`: logo para contextos ciruela profundos.
- `resources/images/LOGO EDITABLE.ai`: fuente maestra; conservarla para exportar SVG/WebP/PNG optimizados, no servirla al navegador.

No usar de forma rutinaria las versiones de fondo dorado, fondo beige, JPG, PDF, PNG de fondo o múltiples variantes simultáneas. La marca debe reconocerse también sin logo por el color, la tipografía y la composición. Si se anima, el logo solo puede aparecer una vez al iniciar sesión o al abrir la navegación: opacidad/posición de 180–220ms, sin loop y sin movimiento bajo `prefers-reduced-motion`.

## Paleta Y Tokens

La fuente normativa es el frontmatter de `DESIGN.md` y su complemento `.impeccable/design.json`.

| Rol | Token | Uso |
|---|---|---|
| Fondo | `background` | Canvas perla casi blanco para sesiones largas |
| Superficie | `surface` | Blanco limpio para formularios, tablas y lectura |
| Elevada | `surface-elevated` | Selección, métricas útiles, grupos secundarios |
| Glass | `glass` | Topbar, drawer móvil, overlay y panel destacado |
| Primario | `primary` | Una acción dominante, navegación activa, importe importante |
| Secundario | `secondary` | Selección suave y contexto de cliente |
| Texto | `text` / `text-muted` | Lectura principal y soporte sobre neutros |
| Éxito | `success` | Pago/cita confirmado, siempre con texto/icono |
| Advertencia | `warning` | Revisión necesaria |
| Error | `error` | Bloqueo, cancelación o error recuperable |
| Foco | `focus` | Focus ring visible y consistente |

No usar beige dominante, gris o negro puro, texto gris sobre rosa/malva, ni gradiente morado-azul.

## Tipografía

- **DM Serif Display:** títulos de marca, login y momentos de bienvenida. Peso 400, sin itálicas decorativas.
- **DM Sans:** navegación, formularios, datos, tablas, acciones y textos de ayuda. Sus números tabulares son obligatorios para HNL, fechas, duraciones y cantidades.
- Tamaño base de entrada/lectura: 16px mínimo. Las etiquetas pueden ser 12px, siempre con contraste suficiente.
- La jerarquía depende de tamaño, peso, espacio y color; no de mayúsculas o color por sí solos.

## Espaciado, Radios Y Bordes

- Escala: 4, 8, 12, 16, 20, 24, 32 y 40px.
- Control: 10px; superficie: 16px; overlay: 20px.
- Bordes: 1px cálido y sutil para separar superficies; nunca bordes ciruela de acento en cards redondeadas.
- Un grupo relacionado se separa 12–16px; cambios de tarea se separan 24–32px.

## Sombras, Glass Y Neomorfismo

- Superficies estándar: planas, con tono y proximidad; no necesitan un borde por defecto.
- Sombras: relieve suave doble para controles interactivos y sombra ambiental para overlays. Nunca glow oscuro.
- Glass: solo topbar, paneles destacados, modales, overlays y navegación móvil. Debe seguir teniendo fondo suficiente y contraste legible.
- Neomorfismo: leve relieve tonal en botones secundarios, selectores, controles y métricas interactivas. Nunca en una tabla, formulario completo o card dentro de card.

## Iconos

Mantener Material Design Icons como familia única durante el rediseño. Iconos de 20–24px, alineados ópticamente con texto, con etiquetas cuando la acción no sea universal. No convertir iconos en una colección repetida de cuadrados redondeados.

## Componentes

### Primitivas De Fase 7A

- `AppButton`: variantes primary, secondary, quiet y danger, con altura operativa mínima.
- `AppCard`: variantes flat, elevated y glass para casos justificados; no sustituye la composición de secciones.
- `AppField`: campo con etiqueta, ayuda y error persistente.
- `AppStatus`: estado textual con icono opcional y tonos semánticos.
- `SectionSurface`, `GlassPanel`, `LoadingSkeleton` y `BottomSheet`: contenedores opt-in para migraciones posteriores.
- Los componentes existentes siguen compatibles; estas primitivas no fuerzan cambios de payload, eventos ni contratos.

### Botones
- Altura mínima 44px y foco visible.
- Primario: una acción local dominante.
- Secundario: relieve ligero y texto ciruela profundo.
- Destructivo: solo cuando hay riesgo real; pedir confirmación cuando exista contrato actual.

### Campos
- Etiqueta siempre visible, ayuda y error junto al campo.
- Prerrequisitos bloqueados explican la causa y ofrecen recuperación cuando sea posible.
- Nunca ocultar el formato HNL o fecha que el usuario necesita revisar.
- Texto escrito, placeholder, label, caret e iconos tienen roles de contraste independientes. Las piezas del outline son transparentes y nunca pueden cubrir el contenido.

### Cards Y Métricas
- Una card existe para agrupar una decisión o una tarea, no para encapsular todo.
- Las métricas se presentan como una composición jerárquica: una dominante, pocas secundarias y datos de soporte, no una cuadrícula homogénea.

### Tablas Y Cards Móviles
- Escritorio: tabla para comparar; acciones secundarias reunidas y predecibles.
- Móvil: cards conservan estado, importe, fecha, identidad y acción primaria; no eliminan metadata relevante como último acceso cuando sea necesaria.

### Modales, Drawers Y Bottom Sheets
- Modal para confirmación o foco breve.
- Drawer para filtros y configuración secundaria.
- Bottom sheet para tareas móviles que continúan una decisión ya iniciada, como revisar un cobro.
- Cerrar un overlay no debe perder información; conservar los contratos existentes de estado y payload.

## Estados

- Vacío: explica la ausencia y la siguiente acción permisible.
- Loading: conserva jerarquía y evita saltos de layout.
- Error: lenguaje sencillo, causa próxima y recuperación visible.
- Éxito: confirmación breve, no bloqueante y legible sin depender solo de verde.
- Permisos: explicar la limitación sin revelar acciones no autorizadas.

## Responsive

- 320–767px: tarea única, controls de 44px, acciones principales en zona baja cuando convenga; filtros en drawer/sheet.
- 768–1023px: dos columnas o master-detail, soporte para touch y puntero.
- 1024px+: rail persistente, tablas comparables y contexto simultáneo.
- Consolidar en fase 7E los breakpoints actuales a cortes guiados por contenido; evitar nuevos puntos arbitrarios.
- Probar zoom 200%, orientación horizontal y teclado virtual.
- El rail de escritorio puede colapsar a 76px con tooltips; en móvil se convierte en drawer temporal y se cierra al navegar.

## Animación

- 100–150ms: feedback inmediato, añadido de servicio y toque móvil.
- 180–280ms: botones, selects, notificaciones y cambios normales.
- 220–280ms: drawers, bottom sheets y modales.
- Calendario a día: transición espacial breve que conserva fecha/contexto.
- Cobro: confirmación visual única y corta; no retrasar el cierre.
- Métricas: aparición progresiva solo al cargar el resumen, con retraso total limitado.
- `prefers-reduced-motion`: eliminar desplazamiento/blur no esencial, mantener cambios de color, foco y estado.
- Shell implementado: rail/drawer, transición de contenido, profundidad activa y feedback puntual de campana usan 180–260ms y quedan reducidos bajo `prefers-reduced-motion`.

No usar bounce, elastic, rotación decorativa, parallax, loops, pulsing dots o revelados de scroll genéricos.

## Accesibilidad

- Contraste razonable WCAG AA, especialmente texto sobre glass y tonos rosados.
- Focus visible, navegación por teclado y nombres accesibles para botones icon-only.
- Estado mediante texto + icono + color.
- Objetivos táctiles de 44px donde sea viable; nunca menos de 24px.
- Animación interrumpible y sin depender del hover.
- Todo control icon-only requiere nombre accesible contextual; los iconos conservan contraste en resting, focus y disabled.

## Antipatrones

Correcto: topbar glass acotada, una métrica de caja dominante, tabla plana con una acción clara, fondo marfil y jerarquía tipográfica.

Incorrecto: gradiente violeta-azul, seis cards idénticas de métricas, glass en cada panel, side-tab de color, campos sin recuperación, iconos en tiles repetidos, o logo dorado de fondo dentro de la UI operativa.

También rechazado: canvas beige plano, sidebar administrativo claro, etiquetas de navegación genéricas, estado activo rosa rectangular, bordes finos por defecto, mini-sidebar en card, jerarquía plana y glass/neomorfismo meramente nominal. Esa combinación se considera anti-referencia SaaS, no una base que deba pulirse.
