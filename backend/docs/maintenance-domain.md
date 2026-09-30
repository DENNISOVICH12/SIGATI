# Contrato del dominio Maintenance — fase 5B.1

## Propósito y límites

`Maintenance` representa una intervención que realmente se realizó sobre un
activo tecnológico. No representa una solicitud: esa responsabilidad continúa
perteneciendo a `Ticket`. Por ello, un ticket puede tener cero o varios
mantenimientos y un mantenimiento puede existir sin ticket.

Esta fase entrega el modelo y el servicio de aplicación; deliberadamente no
publica endpoints ni interfaz de usuario. El endpoint manual y su protección de
idempotencia corresponden a 5B.2. Cuando exista, una clave idempotente persistida
y única deberá cubrir doble clic, reintentos de red, pestañas simultáneas y
requests idénticos; el código único del mantenimiento no sustituye esa clave.

## Modelo y relaciones

La tabla `maintenances` contiene:

- `id` y `code` único;
- `asset_id` obligatorio, con relación N:1 a `assets`;
- `ticket_id` nullable, con relación N:1 a `tickets`;
- `performed_by` obligatorio, con relación N:1 a `users`;
- `origin`, `maintenance_type`, `performed_at`, `result` y `status`;
- `observations` nullable y `preventive_cycle_completed` booleano;
- timestamps.

Las tres claves foráneas restringen el borrado para preservar trazabilidad. Los
índices de consulta son `(asset_id, performed_at)`, `(ticket_id, created_at)`,
`(performed_by, performed_at)` y `(result, performed_at)`.

En PostgreSQL, un `CHECK` exige `ticket_id` cuando `origin = ticket`. La futura
operación originada en ticket también deberá bloquear primero el ticket, derivar
el activo del propio ticket, bloquear después ese activo y comprobar que ambos
`asset_id` coinciden. Una FK simple no expresa esa regla entre tablas.

No se crean todavía catálogo ni tabla de actividades. Incorporarlos sin un caso
de escritura definido fijaría prematuramente su contrato. 5B.2 deberá introducir
el catálogo y una tabla relacional con snapshot del nombre cuando defina el
registro detallado.

## Vocabulario controlado

Enums respaldados por string centralizan estos valores:

- origen: `manual`, `ticket`, `preventive_plan`;
- tipo: `preventive`, `corrective`, `additional`;
- resultado: `operational`, `follow_up_required`, `fault_persists`;
- estado: `completed`, `voided`.

Las columnas enum de base de datos rechazan valores ajenos. `voided` reserva el
estado del modelo, pero 5B.1 no ofrece operación para alcanzarlo.

El código tiene formato `MNT-AAAAMMDD-XXXXXXXXXX`. El sufijo alfanumérico posee
60 bits de entropía y se combina con `UNIQUE(code)`, evitando la carrera de una
secuencia `exists()` seguida de `insert` sin añadir un contador global.

## Registro e inmutabilidad

`MaintenanceRegistrationService::registerManual()` es la única primitiva de
registro de esta fase. Exige `maintenance.create`, abre una transacción, bloquea
el activo con `FOR UPDATE`, deriva `performed_by` del actor, fuerza origen
`manual`, estado `completed`, ticket nulo y usa la hora del servidor como
`performed_at`. Por tanto, el llamador no puede falsificar código, actor, estado
ni fecha histórica.

Un registro `completed` rechaza actualizaciones Eloquent y todo mantenimiento
rechaza borrado Eloquent. No hay PATCH, DELETE, corrección ni anulación. Una fase
posterior deberá crear una operación explícita y auditada, no relajar esta regla
mediante edición genérica.

`preventive_cycle_completed` registra la decisión histórica de si la intervención
cuenta para un ciclo preventivo. Guardarlo ahora no modifica planes ni calcula
fechas; permite que el hecho conserve esa intención cuando el motor preventivo
sea incorporado.

## Asset History

El servicio crea, dentro de la misma transacción, una acción cuyo nombre único es
`maintenance_registered`. El evento sólo contiene `maintenance_id`,
`maintenance_code`, `ticket_id` y `result`; observaciones, actividades y futuras
evidencias permanecen en Maintenance como fuente canónica. Si el historial
falla, también se revierte el mantenimiento.

## Preparación del workflow de tickets

`TicketWorkflowService::resolve()` conserva su contrato público y su propia
transacción. La transición común vive en una única primitiva interna. El nuevo
`resolveInCurrentTransaction()` sólo funciona dentro de una transacción ya
activa, adquiere por sí mismo `lockForUpdate()` sobre el ticket y ejecuta la misma
validación de estado, validación de técnico asignado, escritura de `resolved_at`,
resolución estructurada y evento `resolved`.

Así, un futuro orquestador podrá iniciar una sola transacción y reutilizar la
máquina de estados sin hacer commits parciales ni escribir `ticket.status`
directamente. 5B.1 no implementa aún Ticket → Maintenance → Resolve. Tampoco
cambia el contrato actual de `resolution`: el método `maintenance` se añadirá en
5B.4 junto con el comando que pueda garantizar su semántica completa.

## Permisos y exclusiones

Los roles engineer y technician reciben `maintenance.view` y
`maintenance.create`. El permiso heredado `maintenance.update` se conserva en el
catálogo para una actualización segura de instalaciones existentes, pero no se
concede a ningún rol y no representa edición de hechos históricos.

No forman parte de 5B.1: endpoints de mantenimiento, frontend, evidencias o
archivos, planes preventivos, jobs, alertas, dashboard, actividades, resolución
mediante mantenimiento, anulación y correcciones. Tampoco se expone información
de mantenimiento en el recurso público QR.
