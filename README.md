# BOVITRACK

Aplicación web de gestión ganadera construida con Symfony 7.4 LTS, Doctrine ORM y MySQL 8.

La documentación principal se encuentra en [`docs/README.md`](docs/README.md).

## Desarrollo local

Requisitos:

- PHP 8.2 o superior con `pdo_mysql` habilitado.
- Composer.
- Docker Desktop con Docker Compose.
- Node.js 20 o superior y npm para compilar los estilos.

Para comprobar el controlador de MySQL:

```bash
php -m
```

La salida debe incluir `pdo_mysql`. En la instalación local de Windows usada por este proyecto, se habilita descomentando `extension=pdo_mysql` en `C:\php\php.ini`.

Instalación y ejecución:

```bash
composer install
npm install
npm run build:css
docker compose up -d --wait
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console doctrine:migrations:migrate --env=test --no-interaction
php bin/console app:seed-demo
php -S 127.0.0.1:8000 -t public
```

Durante el trabajo visual, `npm run watch:css` recompila Tailwind al cambiar plantillas o componentes. El CSS generado se guarda en `assets/styles/app.css` y AssetMapper lo entrega junto con Symfony UX.

La configuración compartida está en `.env`. Las credenciales y secretos locales deben definirse en `.env.local`, que está excluido de Git.

Configuración de desarrollo reproducible:

- Servicio Compose: `database`.
- Contenedor MySQL: `bovitrack-mysql`.
- Servidor: `127.0.0.1:3306`.
- Versión: MySQL 8.4 LTS.
- Bases de datos: `bovitrack` y `bovitrack_test`.
- Usuario de desarrollo: `bovitrack`.
- Datos persistentes: volumen Docker `bovitrack_mysql_data`.

`docker compose down` detiene el entorno sin borrar datos. Usa
`docker compose down -v` únicamente cuando quieras eliminar también las bases
de datos del entorno Docker.

Para ejecutar todas las pruebas:

```bash
docker compose up -d --wait
php bin/console doctrine:migrations:migrate --env=test --no-interaction
vendor/bin/simple-phpunit
```

Credenciales locales creadas por `php bin/console app:seed-demo`:

- Administrador: `admin.demo@bovitrack.local`
- Encargado: `encargado.demo@bovitrack.local`
- Contraseña para ambas cuentas: `BoviTrackDemo2026!`

El comando puede ejecutarse varias veces: actualiza los datos demo sin duplicarlos.

Abrir `http://127.0.0.1:8000/` para comprobar que Symfony funciona y que MySQL responde.

## Arquitectura definida

Decisiones visuales y técnicas congeladas para V1/MVP:
- Symfony + Doctrine ORM + MySQL 8.
- Twig + Symfony Forms.
- Tailwind CSS.
- Symfony UX Twig Components, Live Components, Turbo y Toolkit.
- Stimulus solo cuando sea necesario.
- Microsoft Fluent 2 como sistema de diseño base.
- Fluent System Icons como iconografía preferente.
- Paleta de marca BOVITRACK definida y documentada.
- Uso intencional de imágenes o ilustraciones cuando aporten contexto visual.
