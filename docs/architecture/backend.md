# Backend Architecture

## Estructura esperada
```text
src/
├── Controller/
├── Entity/
├── Repository/
├── Form/
├── Service/
├── Security/
└── Twig/
```

## Responsabilidades
- **Controller:** coordina request/response.
- **Entity:** modelo persistente.
- **Repository:** consultas.
- **Form:** definición de formularios y validación.
- **Service:** lógica reutilizable.
- **Security:** autorización.
- **Twig/Components:** componentes de interfaz.

## Principios
- Evitar lógica extensa en controladores.
- Aplicar restricciones de organización/finca en backend.
- Validaciones críticas nunca dependen solo del frontend.
- Las contraseñas se validan y se hashean mediante `UsuarioPasswordManager` antes de persistirse.
