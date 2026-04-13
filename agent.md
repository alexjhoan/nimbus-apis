### 🚀 Perfil del Proyecto

Backend de alto rendimiento construido en **PHP puro (MVC)**. Diseñado para un sistema **SaaS PWA Multi-tenant**. Prioriza el uso de mapeadores genéricos para velocidad de desarrollo y una auditoría estricta para integridad de datos.

### 📁 Estructura del Sistema

- **/controllers**: Solo para lógica de negocio compleja (ej. procesar ventas, validación de stock).
- **/services**:
  - `get.php`, `post.php`, `patch.php`: Servicios genéricos que mapean `JSON Input` ↔ `DB Columns` (Relación 1:1).
  - `Router.php` & `routes.php`: Gestión de endpoints.
- **/swagger**: Documentación `swagger.json` actualizada en tiempo real.
- `db.php`: Conexión MySQLi con soporte para cambio dinámico de base de datos (Tenant).
- `utils.php`: Seguridad, validación de tokens y logs.

---

### 🛡️ Protocolo de Seguridad y Multi-tenancy

1. **Validación Obligatoria:** Toda petición debe iniciar ejecutando `Utils::validateAppToken()`.
2. **Auth:** El token se recibe vía `Authorization: Bearer [TOKEN]`.
3. **Identificación de Tenant:** El Header `X-Tenant-ID` define la base de datos a la que `DataBase::getConnection($tenant)` debe conectarse.
4. **Protección SQL:** Uso estricto de `bind_param` y `htmlspecialchars()`. Prohibido concatenar variables en queries.

---

### 📝 Reglas Operativas (Lógica de Negocio)

1. **Prioridad Genérica:** Si el CRUD es un mapeo simple, **NO** crees un controlador. Agrega el caso al array `$table` en los servicios genéricos.
2. **Política de No-Borrado (Soft Delete):** - Está **estrictamente prohibido** usar `DELETE`.
   - Las bajas se realizan vía `PATCH` cambiando el campo `status` a `0`.
   - Los `GET` genéricos deben incluir siempre `WHERE status = 1` por defecto.
3. **Registro de Auditoría (Audit Log):**
   - Todo `POST` y `PATCH` debe registrarse en la tabla `audit_logs`.
   - Registrar: `user_id` (del token), `table_name`, `record_id`, `action`, y los cambios en formato JSON.
4. **Sincronización de Versiones:**
   - Endpoint `GET /version-check`: Compara el header de versión del Front contra la tabla `system_versions`. Si `force_update = 1` y no coinciden, retornar `426 Upgrade Required`.

---

### ✍️ Flujo de Trabajo para Antigravity

Cuando se solicite un nuevo módulo o CRUD:

1. **Mapeo:** Actualiza los servicios genéricos (`get`, `post`, `patch`) añadiendo la nueva tabla al mapper.
2. **Controlador:** Crea un controlador solo si la lógica excede un simple insert/update (ej. validaciones cruzadas).
3. **Rutas:** Registra el endpoint en `routes.php`.
4. **Documentación:** Actualiza inmediatamente `swagger/swagger.json` con los nuevos parámetros y esquemas de respuesta.
5. **Output:** Las respuestas deben mantener el formato:
   `{ "status": [int], "body": [array], "statusText": [string], "total": [int] }`

---

### 📊 Esquema de Base de Datos de Referencia (Tenant)

- **`products`**: `id`, `sku`, `name`, `description`, `purchase_price`, `sale_price`, `stock`, `min_stock`, `status`.
- **`transactions`**: `id`, `user_id`, `type` (sale/purchase), `total`, `status`, `created_at`.
- **`transaction_details`**: `id`, `transaction_id`, `product_id`, `quantity`, `unit_price`, `subtotal`.
- **`audit_logs`**: `id`, `user_id`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `created_at`.
- **`system_versions`**: `id`, `version_number`, `force_update`, `platform`.

---
