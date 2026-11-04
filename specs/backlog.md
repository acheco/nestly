# Backlog

Trabajo identificado que todavía no tiene spec.

## Auditoría completa (Fase 9)

El modelo `AuditLog` ya puede guardar registros, pero nada los crea todavía. Hace falta una spec que responda antes estas preguntas:

1. **Qué se audita:** ¿todas las acciones o solo las sensibles?
2. **Privacidad:** ¿cómo evitar que `changes` exponga saldos de cuentas privadas a quien lea el log (D-011 a D-017)?
3. **Observer o actions:** el TRD habla de un observer, pero los `update()` y `delete()` masivos no disparan eventos de modelo. Por ejemplo, el archivado en `RemoveTeamMemberAction`, que tiene un `TODO(audit)`.
4. **Retención:** `team_id` y `user_id` quedan en `NULL` si se borra el team o el usuario, y el registro pierde el rastro. ¿Se acepta? Se cruza con D-060.

A resolver también: `audit_logs.user_agent` es `varchar(255)`, así que un user agent más largo daría error al insertar. Hoy no pasa porque nada escribe en esa columna.
