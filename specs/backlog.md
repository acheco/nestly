# Backlog

Trabajo identificado que todavía no tiene spec.

## Spec `003-user-anonymization`

Idea para esa spec, todavía sin aprobar: D-060 pasaría a ser híbrida. Cambiaría el comportamiento actual, pero no se
implementa hasta entonces.

| Caso                                         | Qué pasa                                                                        |
| -------------------------------------------- | ------------------------------------------------------------------------------- |
| Teams donde está solo (incluido el personal) | Se borran con todo su contenido, tras 30 días de gracia                         |
| Teams compartidos que es owner               | Debe transferir el ownership o sacar a los miembros antes                       |
| Teams compartidos donde es miembro           | Sus datos se quedan y se anonimiza su autoría; sus cuentas privadas se archivan |

Choques de esta idea con lo que existe hoy, a resolver en la spec:

- **D-004:** "se borran con todo su contenido" contradice el soft delete de teams, que se decidió para poder recuperar y
  auditar datos financieros. D-004 admite una purga definitiva como proceso explícito aparte; hay que decidir si los 30
  días de gracia terminan en esa purga.
- **Transferir el ownership no existe:** `TeamRole::assignable()` excluye a `Owner`, y no hay ninguna ruta ni action
  para cambiarlo. La spec tiene que incluir esa función o depender de otra. Sacar a los miembros sí existe
  (`RemoveTeamMemberAction`).

Pendientes de la mitigación temporal de D-060 y D-076, a resolver en esa spec:

- **Team personal vacío:** borrar un usuario sin movimientos deja su team personal sin miembros. La fila sigue
  existiendo, junto con lo que contenga (por ejemplo, cuentas sin movimientos), y nadie puede acceder a ella.
- **Bloqueos sin salida:** no hay rutas para borrar cuentas privadas ni gastos recurrentes, y los movimientos
  registrados no pueden dejar de bloquear sin perder historial. Un usuario en alguno de estos casos no puede borrar su
  cuenta por su cuenta, y el mensaje de error dice el motivo, pero no qué hacer. Tampoco tiene salida si el bloqueo está
  en un team que dejó o que se borró.

## Auditoría completa (Fase 9)

El modelo `AuditLog` ya puede guardar registros, pero nada los crea todavía. Hace falta una spec que responda antes
estas preguntas:

1. **Qué se audita:** ¿todas las acciones o solo las sensibles?
2. **Privacidad:** ¿cómo evitar que `changes` exponga saldos de cuentas privadas a quien lea el log (D-011 a D-017)?
3. **Observer o actions:** el TRD habla de un observer, pero los `update()` y `delete()` masivos no disparan eventos de
   modelo. Por ejemplo, el archivado en `RemoveTeamMemberAction`, que tiene un `TODO(audit)`.
4. **Retención:** `team_id` y `user_id` quedan en `NULL` si se borra el team o el usuario, y el registro pierde el
   rastro. ¿Se acepta? Se cruza con D-060.

A resolver también: `audit_logs.user_agent` es `varchar(255)`, así que un user agent más largo daría error al insertar.
Hoy no pasa porque nada escribe en esa columna.
