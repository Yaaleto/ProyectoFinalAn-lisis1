# Data Model

## Entidades principales previstas
- `OrganizacionGanadera`
- `Usuario`
- `Finca`
- `Raza`
- `Animal`
- `Vacuna`
- `Vacunacion`
- `Pesaje`
- `MontaReproductiva`
- `ProduccionLeche`

## Relaciones conceptuales
```text
OrganizacionGanadera
├── Usuario
└── Finca
    └── Animal
        ├── Pesaje
        ├── Vacunacion
        ├── MontaReproductiva
        └── ProduccionLeche

Animal
├── padre -> Animal?
├── madre -> Animal?
└── raza -> Raza

Vacunacion
└── vacuna -> Vacuna
```

## Base de tenencia — Fase 1

| Entidad | Campos de negocio iniciales | Restricciones |
|---|---|---|
| `OrganizacionGanadera` | nombre, código | código único globalmente |
| `Usuario` | nombre, correo electrónico, hash de contraseña, rol, finca activa opcional | correo único globalmente; pertenece a una organización; la finca activa debe pertenecer a la misma organización |
| `Finca` | nombre, código | pertenece a una organización; código único dentro de esa organización |

Las tres entidades conservan `creado_por`, `fecha_creacion`, `actualizado_por` y `fecha_actualizacion`. Las referencias de auditoría son opcionales para permitir el alta inicial de una organización y de su primer usuario.

## Datos derivados
No crear tablas independientes para genealogía, historial integral o total diario de leche.
