# Pruebas automatizadas

## Regla obligatoria

Cada cambio que incorpore lógica de dominio, validaciones o reglas de negocio debe incluir pruebas unitarias. Antes de integrar una issue se ejecutan todas las pruebas y deben finalizar correctamente.

## Ejecución local

```bash
vendor/bin/simple-phpunit
```

## Alcance mínimo por incremento

- caso válido de la regla nueva;
- caso inválido o límite relevante;
- regresión de la regla cuando se corrija un defecto.

Las pruebas de integración de base de datos complementan las unitarias cuando una restricción, relación o migración de Doctrine deba comprobarse contra MySQL.

Las pruebas funcionales usan automáticamente la base `bovitrack_test`, separada de la base local de desarrollo. Configura una conexión local equivalente en `.env.test.local` (archivo ignorado) para que PHPUnit pueda acceder a ella. La configuración de Doctrine añade el sufijo `_test` al nombre de la base.
