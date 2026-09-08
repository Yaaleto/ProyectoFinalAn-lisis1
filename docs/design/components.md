# Component Specifications

## Componentes base
- Button
- IconButton
- Input
- Textarea
- Select
- Checkbox
- Radio
- FormField
- FormError
- Badge
- StatusBadge
- Alert
- Toast
- Card
- Table
- Pagination
- Dialog
- Tabs
- Dropdown
- Breadcrumb
- Sidebar
- Topbar
- EmptyState
- Spinner
- Skeleton
- Tooltip
- IllustrationCard
- ActionTile

## Componentes de dominio
- AnimalStatusBadge
- AnimalIdentity
- AnimalSummaryCard
- WeightRecord
- VaccinationRecord
- MilkProductionRecord
- ReproductionRecord

## Button
Variantes previstas:
- `primary`
- `secondary`
- `subtle`
- `transparent`
- `destructive`

Estados:
- default
- hover
- pressed
- focus
- disabled
- loading

## Uso de ilustraciones
- `IllustrationCard`: para estados vacíos, ayudas contextuales o módulos destacados.
- `ActionTile`: tarjeta accionable que puede combinar imagen/ilustración, título, descripción e icono secundario.

## Regla
No crear una variante visual nueva si una variante existente resuelve el caso. Las imágenes complementan la comprensión, pero no reemplazan acciones pequeñas y repetitivas que funcionan mejor con iconos.
