# BOVITRACK

Aplicación web de gestión ganadera construida con Symfony 7.4 LTS, Doctrine ORM y MySQL 8.

La documentación principal se encuentra en [`docs/README.md`](docs/README.md).

## Desarrollo local

Requisitos:

- PHP 8.2 o superior con `pdo_mysql` habilitado.
- Composer.
- MySQL 8.

Para comprobar el controlador de MySQL:

```bash
php -m
```

La salida debe incluir `pdo_mysql`. En la instalación local de Windows usada por este proyecto, se habilita descomentando `extension=pdo_mysql` en `C:\php\php.ini`.

Instalación y ejecución:

```bash
composer install
php bin/console doctrine:migrations:migrate --no-interaction
php -S 127.0.0.1:8000 -t public
```

La configuración compartida está en `.env`. Las credenciales y secretos locales deben definirse en `.env.local`, que está excluido de Git.

Configuración local verificada para el desarrollo actual:

- Contenedor MySQL: `holayahir`.
- Servidor: `127.0.0.1:3306`.
- Versión: MySQL 8.0.33.
- Base de datos: `bovitrack`.
- Usuario de aplicación: `bovitrack`, restringido a la red local de Docker.
- Contraseña y `APP_SECRET`: almacenados únicamente en `.env.local`.

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
