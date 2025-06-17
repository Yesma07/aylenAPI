# ERP Modular Backend

Este proyecto corresponde al backend de un sistema ERP modular desarrollado en PHP 8.3 puro, sin frameworks. Está diseñado con una arquitectura limpia, organizada por módulos de negocio, con soporte multientorno y una integración flexible con PostgreSQL y Tryton ERP.

---

## 📁 Estructura del Proyecto

```
/erp/backend/
├── composer.json
├── .env
├── .gitignore
├── logs/
│   └── app.log
├── public/
│   └── index.php              ← Punto de entrada HTTP (único archivo expuesto públicamente)
├── src/                       ← Código fuente con namespaces PSR-4 bajo App\
│   ├── Config/
│   │   └── EnvLoader.php
│   ├── Core/
│   │   ├── Exceptions/
│   │   │   └── HttpException.php
│   │   ├── BaseModelController.php
│   │   ├── Crypto.php
│   │   ├── Database.php
│   │   ├── DomainParser.php
│   │   ├── MethodDispatcher.php
│   │   ├── QueryBuilder.php
│   │   ├── Request.php
│   │   ├── Router.php
│   ├── Clients/               ← Clientes externos como Tryton
│   │   └── TrytonClient.php
│   ├── Services/
│   ├── Middlewares/          ← Autenticación, CORS
│   ├── Helpers/              ← Funciones utilitarias
│   │   ├── FieldMapperHelper.php
│   │   ├── ModelHelper.php
│   │   ├── RedisHelper.php
│   │   └── RelationResolverHelper.php
│   └── Modules/              ← Módulos de Negocio
│       ├── CfgPlatform/
│       │   └── Controller/
│       │       └── CfgPlatformController.php
│       └── Usuarios/
│           └── Controller/
│               └── UsuariosController.php
├── storage/
└── README.md
```

---

## ▶️ Levantar el servidor local

```bash
php -S localhost:8000 -t public
```

---

## 🧠 Redis: Instalación y Configuración

### 1. Instalar la extensión Redis en PHP
1. Ir a: https://pecl.php.net/package/redis/6.2.0/windows
2. Descargar la versión `Thread Safe` que coincida con tu versión de PHP.
3. Copiar `php_redis.dll` a la carpeta `ext` de PHP.
4. Agregar al `php.ini`:
    ```ini
    extension=php_redis.dll
    ```
5. Reiniciar el servidor web o PHP.

### 2. Instalar Redis como servicio en Windows
1. Descargar: https://github.com/microsoftarchive/redis/releases (`Redis-x64-3.0.504.zip`)
2. Extraer y abrir CMD como administrador en esa carpeta.
3. Ejecutar:
    ```bash
    redis-server --service-install
    redis-server --service-start
    redis-cli ping  # Debería responder: PONG
    ```

---

## ⚙️ Engine Operacional

### Contexto

```json
{
  "company": 1,
  "user_env": 1,
  "engine": "dlm"
}
```

- `company`: ID de la compañía. Ej: Delthac (1), QS1 (2).
- `user_env`: ID del usuario autenticado.
- `engine`: Define el motor de transacción:
  - `dlm`: PostgreSQL
  - `tryton`: Tryton ERP

### Modelo

Corresponde a la ruta del módulo bajo `Modules`.  
Ejemplo: `"model": "usuarios"` se resuelve como `App\Modules\Usuarios\Controller\UsuariosController.php`.

### Campos de Consulta

Permite consultas simples y con relaciones (`joins`). Ejemplo:
```json
"fields_names": ["nombre", "usuario_crea.nombres"]
```

### Dominios

Permite expresiones condicionales tipo Tryton para filtrar resultados.

```
  "AND": "[('id', '=', 1), ('cedula', '=', '123')]",
  "OR": "[OR[('id', '=', 1), ('cedula', '=', '123')]]",
  "AND + OR": "[('estado', '=', 'activo'), OR[('id', '=', 1), ('cedula', '=', '123')]]",
  "IN": "[('id', 'in', [1, 2, 3])]",
  "NOT IN": "[('estado', 'not in', ['activo', 'inactivo'])]",
  "=": "[('id', '=', 1)]",
  "!=": "[('id', '!=', 1)]",
  ">": "[('edad', '>', 18)]",
  "<": "[('edad', '<', 65)]",
  ">=": "[('monto', '>=', 1000)]",
  "<=": "[('monto', '<=', 5000)]",
  "LIKE": "[('nombre', 'like', '%Yes%')]",
  "ILIKE": "[('nombre', 'ilike', '%yes%')]",
  "NOT LIKE": "[('nombre', 'not like', '%No%')]",
  "NOT ILIKE": "[('nombre', 'not ilike', '%no%')]",
  "BOOLEAN TRUE": "[('activo', '=', true)]",
  "BOOLEAN FALSE": "[('activo', '=', false)]"
```

---

## 📡 Ejemplo de Payload para `/search`

```json
{
  "context": {
    "company": 1,
    "user_env": 1,
    "engine": "dlm"
  },
  "model": "cfg.platform",
  "fields_names": [
    "nombre",
    "usuario_crea.nombres"
  ],
  "domain": "[]"
}
```

---

## 📌 Notas

- El sistema infiere relaciones y nombres físicos automáticamente desde el esquema de PostgreSQL.
- Redis es utilizado para cachear estructuras y mejorar el rendimiento.
- El framework soporta extensiones limpias a través de módulos y controladores desacoplados.

---