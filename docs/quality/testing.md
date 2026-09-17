# Estrategia de pruebas automatizadas

## Regla obligatoria

Cada cambio que incorpore lógica de dominio, validaciones, persistencia o reglas de negocio debe incluir pruebas automatizadas. Antes de integrar una issue se ejecutan todas las pruebas y deben finalizar correctamente.

## Ejecución local

```bash
vendor/bin/simple-phpunit
```

La suite carga `.env` y usa el entorno `test`; Doctrine agrega `_test` al nombre
configurado, por lo que nunca debe apuntar a la base de desarrollo o producción.

## Capas obligatorias

1. **Unitarias:** reglas de una entidad o servicio sin arrancar Symfony ni usar BD.
2. **Integración de BD:** mapeo Doctrine, migraciones, claves foráneas, unicidad,
   precisión decimal y consultas con alcance de organización/finca sobre MySQL 8.
3. **Funcionales:** petición HTTP completa, autenticación, autorización, formulario
   y persistencia observable.

Una prueba que usa Doctrine o MySQL es de integración, no unitaria. Ambas son
obligatorias cuando una regla existe en el dominio y también debe resistir escrituras
concurrentes o accesos que eviten el formulario.

## Alcance mínimo por incremento

- caso válido de la regla nueva;
- casos inválidos por valor, relación, organización y fecha;
- límites de nulabilidad, precisión y unicidad;
- regresión de la regla cuando se corrija un defecto.

## Matriz mínima para persistencia

| Área | Casos exigidos |
|---|---|
| Tenencia | misma organización, organización ajena, encargado sin finca |
| Unicidad | valor libre, duplicado exacto, mismo valor en otra organización |
| Fechas | válida, anterior al nacimiento, posterior a la salida, mismo día |
| Valores | positivo, cero, negativo y límite decimal |
| Relaciones | relación válida, sexo incorrecto, autorreferencia, entidad ajena |
| Catálogos | activo, inactivo, usado históricamente |
| Historial | sin registros, un registro, varios registros y orden cronológico |

Las pruebas de integración de base de datos complementan las unitarias cuando una restricción, relación o migración de Doctrine deba comprobarse contra MySQL.

Las reglas de autorización deben verificarse además con pruebas funcionales: una solicitud autorizada, una denegación por finca no asignada y una denegación por organización ajena. Las consultas con alcance de organización o finca también deben comprobar que no devuelvan registros no autorizados.

Las pruebas funcionales usan automáticamente la base `bovitrack_test`, separada de la base local de desarrollo. Configura una conexión local equivalente en `.env.test.local` (archivo ignorado) para que PHPUnit pueda acceder a ella. La configuración de Doctrine añade el sufijo `_test` al nombre de la base.
