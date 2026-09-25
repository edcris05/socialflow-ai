# Entorno local

## Estado actual

SocialFlow AI se ejecuta como un único proyecto DDEV llamado `socialflow-ai`.

- Aplicación: Laravel, en `app-laravel/`.
- URL local: `https://socialflow-ai.ddev.site`.
- Servidor web: Nginx + PHP 8.4 provistos por DDEV.
- Base de datos: PostgreSQL 16 en el servicio DDEV `db`.
- Conexión Laravel: host `db`, base `db`, controlador `pgsql`.
- Sesiones: se almacenan en PostgreSQL mediante el driver `database`.

No se documentan ni versionan contraseñas, claves API o archivos `.env`.

## Requisitos

- Docker Engine en ejecución.
- DDEV instalado.
- Acceso del usuario al socket de Docker (normalmente mediante el grupo `docker`).

No es necesario instalar PHP, Composer, Node.js o PostgreSQL en el host para trabajar con Laravel: DDEV los provee dentro de sus contenedores.

## Iniciar y detener

Desde la raíz del repositorio:

```bash
ddev start
ddev describe
```

`ddev start` inicia juntos Laravel y PostgreSQL. `ddev describe` muestra la URL, los servicios y sus puertos.

Abrir la aplicación:

```bash
ddev launch
```

Para detener únicamente este proyecto:

```bash
ddev stop
```

Para apagar todos los proyectos DDEV activos:

```bash
ddev poweroff
```

## Comandos habituales de Laravel

Ejecutar Artisan dentro del contenedor web:

```bash
ddev artisan about
ddev artisan route:list
ddev artisan test
```

Ejecutar Composer dentro del contenedor, en `app-laravel/`:

```bash
ddev composer install
ddev composer update
```

Los comandos `update` cambian dependencias bloqueadas y no deben ejecutarse como parte de una verificación rutinaria.

## Base de datos y migraciones

Antes de aplicar cambios, inspeccionar el estado de migraciones:

```bash
ddev artisan migrate:status --no-interaction
```

Aplicar únicamente migraciones pendientes:

```bash
ddev artisan migrate --no-interaction
```

No usar `migrate:fresh`, `migrate:reset`, `migrate:rollback` ni comandos que eliminen datos sin una copia y una decisión explícita.

La migración inicial de Laravel (`0001_01_01_000000_create_users_table.php`) crea, entre otras, la tabla `sessions`. Cuando `SESSION_DRIVER=database`, dicha tabla debe existir antes de cargar la aplicación.

Para comprobar la tabla sin modificar datos:

```bash
ddev psql -d db -c "SELECT to_regclass('public.sessions') AS sessions_table;"
```

El resultado esperado es `sessions`. Si devuelve vacío y la migración inicial figura como pendiente, aplicar `ddev artisan migrate --no-interaction`. Si figura como ejecutada, detenerse e investigar la conexión y el historial de migraciones; no crear una segunda migración de sesiones ni reiniciar la base.

## Diagnóstico de error de sesiones

El error siguiente:

```text
SQLSTATE[42P01]: Undefined table: relation "sessions" does not exist
```

significa que Laravel usa sesiones en base de datos, pero PostgreSQL no encuentra esa tabla en la base activa. Diagnóstico seguro:

```bash
ddev describe
ddev artisan migrate:status --no-interaction
ddev psql -d db -c "SELECT to_regclass('public.sessions') AS sessions_table;"
```

Si la migración inicial está pendiente, la corrección segura es ejecutar una única vez:

```bash
ddev artisan migrate --no-interaction
```

Luego volver a comprobar la tabla y la respuesta HTTP:

```bash
ddev psql -d db -c "SELECT to_regclass('public.sessions') AS sessions_table;"
curl -I https://socialflow-ai.ddev.site
```

Se espera `sessions` en la primera consulta y una respuesta HTTP que no sea `500` en la segunda.

## Seguridad y alcance actual

- Mantener `.env`, certificados, tokens y claves fuera de Git.
- Usar `.env.example` con valores ficticios cuando se agregue configuración compartida.
- La autenticación web está disponible en `/ingresar` y `/registrarse`; las sesiones usan el driver `database`.
- Las marcas están aisladas mediante la relación `brand_user`; una cuenta solo puede listar, ver, crear y editar sus marcas asignadas.
- Context Retrieval v2 está disponible desde cada marca en `Preparar contenido`: `TextKnowledgeRetriever` devuelve matches rankeados y `ContextBuilder` construye un `ContextPackage` sin generar contenido.
- El `ContextPackage` separa `relevantKnowledge`, `brandContext`, policies, restrictions, pendingKnowledge, fuentes, warnings y missingInformation.
- El ranking textual pondera título, contenido y fuente, devuelve score y términos coincidentes, y limita los resultados relevantes a 8.
- `KnowledgeEntry.category` usa `fact`, `product`, `price`, `policy`, `restriction`, `contact`, `brand_identity` y `future_idea`; `applicability` separa reglas `global`, `price`, `location`, `delivery`, `order`, `customer_service` y `content`.
- Las `future_idea` no entran en consultas comerciales normales. Las policies y restricciones contextuales solo se incorporan cuando su scope coincide; las globales sí se mantienen.
- En entorno `local`, la preview permite abrir `Depuración del ranking` para ver score y términos coincidentes. Los warnings se deduplican y el contenido con `\\n` literal se renderiza como salto de línea seguro, sin interpretar HTML.
- El punto de extensión futuro es `TextKnowledgeRetriever` -> `VectorKnowledgeRetriever` o `HybridKnowledgeRetriever`; no se implementan aún embeddings, pgvector, OpenAI, FastAPI ni Meta.
- Para probarlo: iniciar sesión, abrir una marca, elegir `Preparar contenido` y consultar `Quiero promocionar stickers resistentes al agua`. Revisar entradas, políticas, fuentes y advertencias antes de crear un borrador.
- No añadir todavía FastAPI, `socialflow_ai`, pgvector, OpenAI ni Meta.
- La futura segunda base `socialflow_ai` se creará en la misma instancia PostgreSQL DDEV solo cuando se incorpore el servicio FastAPI.
