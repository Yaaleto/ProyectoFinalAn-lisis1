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

## Datos derivados
No crear tablas independientes para genealogía, historial integral o total diario de leche.
