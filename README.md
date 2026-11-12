# BOVITRACK

Aplicación web de gestión ganadera (V1/MVP) construida con Symfony 7.4, Doctrine ORM y MySQL 8.4. Se usa desde un navegador en computadora y está en español.

## Requisito local

Docker Desktop con Docker Compose v2. PHP, Apache, Composer, Node.js, npm y MySQL corren dentro de contenedores; no hace falta instalarlos en el equipo.

## Arranque

Desde la raíz del repositorio:

```bash
docker compose up --build -d
```

Qué ocurre en el primer arranque:

1. `assets` instala los paquetes npm y compila Tailwind CSS en `assets/styles/app.css`.
2. `database` (MySQL 8.4) inicializa un volumen vacío ejecutando el SQL de `docker/mysql/`. Crea dos bases con el esquema físico V1 completo (diez tablas, claves foráneas, restricciones e índices): `bovitrack`, para usar la aplicación, y `bovitrack_test`, para las pruebas automáticas, que pueden borrar datos sin afectar a la otra. Solo `bovitrack` recibe una organización, una finca y las dos cuentas de prueba; ninguna base trae animales ni registros operativos.
3. `app` instala las dependencias de Composer, limpia la caché, compila los recursos de AssetMapper en `public/assets/` y arranca Apache.

La aplicación queda en <http://127.0.0.1:8080/>. PHP y MySQL usan la hora de Guatemala (UTC−6).

## Modo de ejecución

La aplicación corre en modo producción: ante una dirección inexistente o un error, el usuario ve las páginas propias de BOVITRACK y no el detalle técnico de Symfony.

El modo se define en `compose.yaml` con `APP_ENV: prod` en el servicio `app`. Si se cambió, para volver a producción deja ese valor y reinicia el contenedor:

```bash
docker compose up -d app
```

Qué **no** ocurre: no se ejecutan migraciones de Doctrine ni `doctrine:schema:update --force`. El esquema vive solo en el SQL inicial; Doctrine ORM se usa únicamente para leer y guardar entidades. Los arranques posteriores no modifican el esquema ni los datos existentes.

## Cuentas demo

Creadas por `docker/mysql/02-usuarios-demo.sql` al inicializar `bovitrack`, junto con la organización y la finca demo que necesitan:

| Rol | Correo |
| --- | --- |
| Administrador | `admin.demo@bovitrack.local` |
| Encargado | `encargado.demo@bovitrack.local` |

Contraseña de ambas: `BoviTrackDemo2026!`. Son credenciales de desarrollo; no usar fuera del entorno local.

## Datos y esquema

- El SQL inicial vive en `docker/mysql/`: `00-inicializar.sql` crea las dos bases y orquesta la carga, `01-esquema.sql` define las diez tablas finales con sus claves foráneas y restricciones, y `02-usuarios-demo.sql` crea la organización, la finca y las cuentas de prueba solo en `bovitrack`. `bovitrack_test` queda con el mismo esquema y sin datos.
- Los datos persisten en el volumen Docker `bovitrack_mysql_data`. `docker compose down` detiene el entorno sin borrarlos.
- El SQL inicial solo se ejecuta cuando el volumen está vacío. Si cambias el esquema (`docker/mysql/01-esquema.sql`), recrea el volumen de desarrollo de forma explícita:

```bash
docker compose down -v
docker compose up --build -d
```

  `down -v` borra las bases de datos, las dependencias y las cachés del entorno local. Nunca se ejecuta automáticamente.
- La base empieza sin animales ni registros operativos. Importar datos de un entorno anterior es opcional y no forma parte del arranque.

## Servicios y puertos

| Servicio | Stack | Acceso desde el equipo |
| --- | --- | --- |
| `app` | PHP 8.4, Apache, Composer, Symfony 7.4 | `127.0.0.1:8080` |
| `assets` | Node.js 24, npm, Tailwind CSS | solo se ejecuta al arrancar |
| `database` | MySQL 8.4 (`bovitrack-mysql`), zona horaria `-06:00` | `127.0.0.1:3307`; entre contenedores, `database:3306` |
Los puertos se publican solo en `127.0.0.1`. Usuario de desarrollo: `bovitrack`; las credenciales de prueba están en `compose.yaml` y no son para producción.

## Comandos habituales

Todo se ejecuta dentro de Compose:

```bash
docker compose exec app php bin/console <comando>      # Symfony
docker compose exec app composer <comando>             # Composer
docker compose exec app vendor/bin/simple-phpunit      # todas las pruebas
docker compose run --rm assets npm run build:css       # recompilar estilos tras editar plantillas (luego reinicia app)
```

La base de pruebas (`bovitrack_test`) se crea con el mismo esquema que `bovitrack`. Para dejarla otra vez limpia, recrea el entorno con `docker compose down -v` y `docker compose up --build -d`.

## Arquitectura

- PHP 8.2 o superior (en Compose, PHP 8.4) con Symfony 7.4 y Doctrine ORM para la lógica de negocio y la persistencia; MySQL 8.4 como base de datos, con el esquema SQL propio como fuente de verdad.
- Twig y Symfony Forms para las plantillas y los formularios con validación.
- Tailwind CSS 4, Symfony UX Twig Components, Stimulus, Turbo y AssetMapper para la interfaz. No se usa React.
- Microsoft Fluent 2 como base visual, con SVG.
- PHPUnit para las pruebas.
- Multi-organización: toda tabla operativa lleva su organización y las claves foráneas compuestas impiden cruzar organizaciones.
