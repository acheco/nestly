# Backlog

Trabajo identificado que todavía no tiene spec.

## Spec `003-user-anonymization`

Pendientes de la mitigación temporal de D-060 y D-076, a resolver en esa spec:

- **Team personal vacío:** borrar un usuario sin movimientos deja su team personal sin miembros. La fila sigue existiendo, junto con lo que contenga (por ejemplo, cuentas sin movimientos), y nadie puede acceder a ella.
- **Bloqueos sin salida:** no hay rutas para borrar cuentas privadas ni gastos recurrentes, y los movimientos registrados no pueden dejar de bloquear sin perder historial. Un usuario en alguno de estos casos no puede borrar su cuenta por su cuenta, y el mensaje de error dice el motivo, pero no qué hacer. Tampoco tiene salida si el bloqueo está en un team que dejó o que se borró.

## Auditoría completa (Fase 9)

El modelo `AuditLog` ya puede guardar registros, pero nada los crea todavía. Hace falta una spec que responda antes estas preguntas:

1. **Qué se audita:** ¿todas las acciones o solo las sensibles?
2. **Privacidad:** ¿cómo evitar que `changes` exponga saldos de cuentas privadas a quien lea el log (D-011 a D-017)?
3. **Observer o actions:** el TRD habla de un observer, pero los `update()` y `delete()` masivos no disparan eventos de modelo. Por ejemplo, el archivado en `RemoveTeamMemberAction`, que tiene un `TODO(audit)`.
4. **Retención:** `team_id` y `user_id` quedan en `NULL` si se borra el team o el usuario, y el registro pierde el rastro. ¿Se acepta? Se cruza con D-060.

A resolver también: `audit_logs.user_agent` es `varchar(255)`, así que un user agent más largo daría error al insertar. Hoy no pasa porque nada escribe en esa columna.
