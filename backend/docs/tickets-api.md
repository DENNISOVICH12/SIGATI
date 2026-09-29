# Mesa de Ayuda: contrato y operación del backend

## Transiciones semánticas

Todos los endpoints requieren autenticación Sanctum y un usuario activo. La autorización se evalúa en el servidor mediante permisos y roles.

| Acción | Payload JSON | Resultado exitoso | Errores relevantes |
| --- | --- | --- | --- |
| `POST /api/tickets/{ticket}/claim` | Sin campos | `200`; asigna al técnico autenticado | `403` si no es técnico autorizado; `404` si no existe; `409` si ya no está disponible |
| `POST /api/tickets/{ticket}/release` | `reason` requerido, texto no vacío, máximo 1000 | `200`; vuelve a `new` y desasigna | `403` si no corresponde al técnico; `409` si el estado no permite liberar; `422` por payload inválido |
| `POST /api/tickets/{ticket}/assign` | `technician_id` requerido; `reason` opcional, pero requerido al reasignar un ticket `in_progress` | `200`; asigna o reasigna | `403` sin permiso; `409` por conflicto de estado o misma asignación; `422` por payload o técnico no elegible |
| `POST /api/tickets/{ticket}/start` | `note` opcional, máximo 2000 | `200`; pasa a `in_progress` | `403` si no corresponde al técnico; `409` por estado incompatible; `422` por payload inválido |
| `POST /api/tickets/{ticket}/resolve` | `diagnosis` y `solution` requeridos; `notes` opcional (`observations` se acepta actualmente como alias) | `200`; pasa a `resolved` y guarda resolución estructurada | `403` si no corresponde al técnico; `409` por estado incompatible; `422` por payload inválido |
| `POST /api/tickets/{ticket}/close` | `reason` requerido, texto no vacío, máximo 2000 | `200`; pasa a `closed` | `403` si no es ingeniero autorizado; `409` si no está resuelto; `422` por payload inválido |

`reason` expresa el motivo operativo o administrativo de una transición y se guarda en `ticket_events.reason`. `note` en `start` es una observación opcional sobre el inicio, mientras que `notes` forma parte de la resolución estructurada.

La creación de tickets responde `201`. La API conserva los códigos `401` (no autenticado o usuario inactivo), `403` (no autorizado), `404` (recurso inexistente), `409` (conflicto de workflow), `422` (validación) y `500` (fallo interno no controlado).

### Validación de creación

`POST /api/tickets` normaliza con `trim` los textos del formulario y convierte correo y teléfono vacíos en `null`. El título requiere entre 5 y 150 caracteres; la descripción, entre 10 y 5000; el nombre del solicitante, entre 2 y 150 y al menos una letra Unicode; y la categoría, entre 2 y 100. El teléfono admite únicamente dígitos y los caracteres de formato `+`, espacio, `-`, `(` y `)`, con 7 a 15 dígitos reales. El correo es opcional, permite dominios internos como `.local` y tiene un máximo de 254 caracteres.

Las prioridades reconocidas son `low`, `medium`, `high` y `critical`. `asset_id` es opcional, pero, cuando se informa, debe identificar un activo existente.

> **Mejora pendiente:** `category` continúa siendo texto libre para preservar el contrato actual. Debe evolucionar posteriormente hacia un catálogo controlado, sin acoplar este endurecimiento de validaciones a una migración funcional del módulo.

## Atomicidad y concurrencia

`claim`, `release`, `assign`, `start`, `resolve` y `close` consultan nuevamente el ticket dentro de una transacción y aplican `lockForUpdate()`. La mutación y su `TicketEvent` pertenecen a la misma transacción: si el evento falla, la mutación se revierte. `assign` también bloquea el técnico seleccionado antes de comprobar que continúa activo y conserva su rol.

La suite usa SQLite en memoria. Sus pruebas secuenciales verifican el rechazo de una segunda toma y la ausencia de un segundo evento, pero SQLite no reproduce de forma fiable el bloqueo de filas de PostgreSQL. La concurrencia efectiva debe conservarse como prueba de integración sobre PostgreSQL; no debe sustituirse por una prueba supuestamente concurrente sobre SQLite.

## Excepciones y configuración de producción

Las rutas `api/*` se renderizan siempre como JSON. El nivel de detalle de las excepciones sigue la configuración estándar de Laravel:

- Desarrollo: `APP_ENV=local` y `APP_DEBUG=true` permiten diagnóstico detallado.
- Producción: `APP_ENV=production` y `APP_DEBUG=false` devuelven errores sanitizados; no incluyen excepción, traza, rutas, archivos ni líneas internas.

El despliegue debe definir esas variables en el entorno real y ejecutar `php artisan config:cache` después de configurarlas. Nunca debe reutilizarse un caché de configuración generado con debug activo. `.env.example` es una plantilla de desarrollo y no es configuración de producción.
