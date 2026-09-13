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

## Patrón de autorización de organización y finca

- `FincaAccess` concentra las reglas reutilizables: un Administrador accede a cualquier finca de su organización; un Encargado únicamente a su finca activa.
- `FincaVoter` aplica la decisión al recurso con `FINCA_VIEW` o `FINCA_MANAGE`. Los controladores deben invocarlo antes de mostrar o modificar una finca.
- Las consultas de fincas deben iniciar con `FincaRepository::createAuthorizedQueryBuilder($usuario)` para limitar los resultados desde la base de datos. No se deben filtrar registros autorizados únicamente después de recuperarlos.
- Cualquier recurso futuro que pertenezca a una finca debe reutilizar estas reglas y comprobar su organización antes de exponerlo.
