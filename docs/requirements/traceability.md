# Requirements Traceability

| RF | Módulo | Pantalla / área principal | Responsabilidad |
|---|---|---|---|
| RF-01 | Authentication | `/login` | Symfony Security + formulario |
| RF-02 | Administration | `/users`, `/farms` | CRUD y asignaciones |
| RF-03 | Catalogs | `/breeds`, `/vaccines` | Catálogos |
| RF-04 | Animals | `/animals`, `/animals/{id}` | Tabla, formulario y estado |
| RF-05 | Birth / Genealogy | `/animals/new`, ficha animal | Nacimiento y progenitores |
| RF-06 | Weight | ficha animal | Weight panel |
| RF-07 | Vaccination | ficha animal | Vaccination panel |
| RF-08 | Reproduction | ficha animal | Reproduction panel |
| RF-09 | Milk | ficha animal | Milk panel + total diario |
| RF-10 | History | `/animals/{id}` | Ficha integral |

Cada cambio funcional debe mapearse a un RF y a criterios de aceptación.
