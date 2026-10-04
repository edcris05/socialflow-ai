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
- OpenAI Generation v1 uses Responses API only to generate a draft from its historical ContextSnapshot. Local configuration stays in .env and is documented with safe placeholders in app-laravel/.env.example: OPENAI_API_KEY, OPENAI_MODEL, OPENAI_MAX_OUTPUT_TOKENS, OPENAI_STORE, OPENAI_TIMEOUT, and the three per-million token prices. Never commit or share the API key.
- The minimal generation request contains model, historical prompt, max_output_tokens=750, reasoning.effort=minimal, and store=false. It has no tools, web search, images, agents, or Meta.
- Do not add FastAPI, socialflow_ai, pgvector, Meta, or generation capabilities outside this flow yet.
- La futura segunda base `socialflow_ai` se creará en la misma instancia PostgreSQL DDEV solo cuando se incorpore el servicio FastAPI.

- Generation Guardrails & Evaluation v1 evaluates each successful GenerationRun deterministically from its historical ContextSnapshot. `passed` means no automatic rule violation was detected; it does not mean every factual claim was verified.
- Successful GenerationRuns persist the exact evaluated provider output in `generated_content`, independently from the mutable Draft content. Historical runs created before this column intentionally keep `generated_content=NULL`; they are not backfilled because a later regeneration may already have replaced the Draft content.
- The pastel pink and pastel blue palette belongs to Art Made to Print's visual identity, logo, and institutional communication. It does not restrict the colors of personalized products, stickers, prints, or customer designs; those may use other colors according to the design and order.
- v1 does not infer unsupported product qualities or use cases. The current KB confirms water-resistant adhesive sheets, but it does not confirm durability, outdoor suitability, everyday-object use, or suitability for notebooks, agendas, or gifts. “Water resistant” must not be expanded into “weatherproof” or “waterproof.” These remain future factual-grounding work. Private-address detection also remains limited to exact values available in structured snapshot data.
- Publishable Generation Behavior v2 adds an explicit output contract: the structured context, policies, restrictions, and warnings have factual authority over contradictory claims in the user request. When data is unconfirmed, generation must omit it or use a generic consultation fallback while still returning one concise, publishable result without exposing internal policies or warnings.
- The OpenAI Responses payload sends factual guardrails and output requirements through the top-level `instructions` field, while the user request and historical snapshot context remain in `input`. Silent fallback is mandatory: the response must start directly with the publishable piece and contain no operator-facing preamble or follow-up.
- Deterministic detection of conversational meta-responses remains deferred. After repeated real evidence, a separate publishability/editorial evaluation is reasonable, but phrases such as questions or offers can be valid customer-facing copy; a future implementation needs stronger structural evidence than a broad regex and must remain separate from factual evaluation.
- Ambiguous temporal copy such as “¿Querés los tuyos esta semana?” is not treated as a concrete delivery or production promise in v1. Expanding the temporal regex without a stronger rule would risk false positives, so this remains explicit evaluation debt.
- Structured Knowledge Claims v1 stores optional, validated `grounding_metadata` with verified KnowledgeEntry records and copies it into historical ContextSnapshot payloads. `coverage=open` means an absent claim or use remains unknown; it does not mean the claim is unsupported or prohibited.
- Grounding metadata is curated through seeders or internal administrative processes for now. The knowledge UI intentionally does not expose a raw JSON editor; a safe structured editor remains future UI work.
- Every `evidence_excerpt` must exist in the same KnowledgeEntry content after conservative whitespace and Unicode case normalization. KnowledgeEntry enforces this invariant on every save, so content edits cannot leave historical grounding metadata stale; invalid updates must change or clear the metadata explicitly.
- Structured Knowledge Claims v1 is intentionally claim-centric: `claims` must contain at least one item, so an entry containing only `allowed_uses` is not representable yet. This is acceptable for the first factual-grounding scope; support for use-only entries should be added only when a confirmed use case requires it.
- Factual Grounding Core v1 evaluates an already structured claim exclusively against verified entries serialized in the historical ContextSnapshot. It does not query the current KnowledgeEntry table, perform retrieval, extract claims from prose, or infer aliases; only exact `subject` + `predicate` + `value` equality is `SUPPORTED`.
- `phrases`, `evidence_excerpt`, and historical `source` explain a structured match but never create one. Missing subjects, predicates, values, legacy metadata, and absent uses under `coverage=open` are `UNKNOWN`; an absent allowed use is `UNSUPPORTED` only when all applicable historical coverage is closed.
- Distinct explicit values for the same subject and predicate produce `CONFLICT` instead of selecting evidence arbitrarily. The v1 schema has no predicate-cardinality metadata, so this conservative state may require refinement before supporting legitimately multi-valued predicates.

## Grounded Generation v1

Grounded Generation v1 asks the existing single OpenAI Responses request for strict structured output containing the publishable `content` and the `factual_claims` declared by the generator. Each declared claim is evaluated only against the historical `ContextSnapshot` attached to its `GenerationRun`; no current knowledge retrieval, second model call, embeddings, or external grounding service is used.

`grounding_status=passed` means every declared factual claim was `SUPPORTED`, or that the generator declared no factual claims. It does not mean the complete caption was exhaustively fact-checked. Invalid structured output or a declared `text` fragment absent from `content` fails closed and leaves grounding as `not_evaluated`.

The first real manual test used: “Creá un post breve de Instagram promocionando nuestros stickers resistentes al agua. Usá un tono cercano y un llamado a la acción.” The response produced valid structured output, declared `stickers.water_resistance=resistant`, and grounded it as `SUPPORTED` with the correct “Adhesivo resistente al agua” evidence. It did not introduce price, stock, or production-time claims. However, the generated content also mentioned notebooks, bottles, and objects carried with the customer without declaring those concrete uses in `factual_claims`. This confirms the v1 boundary: Grounded Generation validates claims declared by the generator, not exhaustive factual coverage of the caption. Human approval remains required before publishing.

### Grounding Improvements / Post-MVP

The following capabilities are explicitly not implemented yet:

1. Independent Claim Extractor.
2. Optional second extraction/audit call.
3. Detection of factual claims omitted by the generator.
4. Semantic claim normalization.
5. Entailment/NLI.
6. Optional LLM grounding judge.
7. Embeddings when evidence shows they add value.
8. Cardinality for multi-valued predicates.
9. Exhaustive caption coverage.
10. Autopublishing conditioned on strong grounding.

## Approval Workflow v1

Approval Workflow v1 keeps the human decision separate from automatic evaluation. A Draft can move from `draft` to `approved` or `rejected`; a rejected Draft can explicitly return to `draft`, while an approved Draft is terminal in v1 and cannot be silently edited, regenerated, rejected, or reopened. Approval means “approved for a future publication”; it does not call Meta, publish, or schedule anything.

Approval and rejection store the responsible user and timestamp. Reopening a rejected Draft preserves its rejection metadata, and a later approval does not erase it. This is intentionally a compact audit record rather than a complete transition ledger. Repeated rejection cycles retain the most recent rejection details; complete transition history is Post-MVP work.

When a person changes `Draft.content` after a successful generation, `manually_edited_at` makes the divergence explicit. `GenerationRun.generated_content`, commercial evaluation, factual grounding, and the historical `ContextSnapshot` remain unchanged. The UI therefore warns that automatic results describe the generated version, not necessarily the current human-edited Draft. v1 does not automatically extract or re-ground human edits.

### Approval Improvements / Post-MVP

The following capabilities are explicitly deferred:

1. Approver-specific roles.
2. Multiple approval levels.
3. Collaborative review comments.
4. A complete append-only transition history.
5. Configurable approval policies.
6. Automatic re-grounding after human editing.
7. Risk-based auto-approval.
8. Approval notifications.
9. Scheduled approval expiration.

## Strategy Agent v1

Strategy Agent v1 answers which content the brand could create next; it does not write, approve, publish, or schedule the final post. A single OpenAI Responses request returns exactly three structured suggestions with topic, objective, format, angle, reason, and generation_query.

Each StrategyRun persists the exact strategic context, suggestions, provider/model, status, token usage, estimated cost, provider request identifier, finish reason, and safe error. The context contains verified knowledge, policies, restrictions, non-usable pending/future titles, and a bounded summary of the current user's recent Draft queries and Strategy topics. Pending knowledge and future ideas are never included as authorized facts.

Selecting a suggestion creates a Draft and runs its stored generation_query through the existing ContextBuilder to create a historical ContextSnapshot. It does not invoke OpenAI or GenerationService; the user continues through the existing Generation, Grounding, and Approval workflow explicitly.

The v1 provider reuses gpt-5-mini, reasoning.effort=minimal, a dedicated strategy_max_output_tokens=1500 budget, store=false, timeout, and pricing from services.openai. Generation keeps its existing max_output_tokens configuration. There is no second model call, embeddings, ranking score, analytics, Meta integration, or autonomous orchestration.

### Strategy Improvements / Post-MVP

The following capabilities are explicitly deferred:

1. Analytics-driven strategy and engagement scoring.
2. Best-time-to-post recommendations.
3. Adaptive publishing frequency.
4. Multi-post campaign planning.
5. Configurable content pillars.
6. Seasonality and calendar optimization.
7. Autonomous strategy loops.
8. Strategy Agent and Analytics Agent feedback.
9. Automatic re-planning.
10. Facebook-specific formats and copy adaptation.

## Calendar / Queue v1

Calendar v1 schedules only approved Drafts for future internal publication. It stores a `ScheduledPublication` with its brand, Draft, scheduling actor, scheduled time, status, and cancellation audit. Draft approval remains terminal and independent from scheduling; scheduling does not modify GenerationRun, ContextSnapshot, evaluation, grounding, or approval timestamps.

The persisted states are `scheduled` and `cancelled`. `READY` is derived when a scheduled item has `scheduled_for <= now()`. READY means only that the item is ready for a future publisher; Calendar v1 never calls Meta, Instagram, Facebook, OpenAI, or any external provider.

A Draft has one active schedule. Reprogramming updates that active record, while cancelling preserves it with `cancelled_at` and `cancelled_by`; a cancelled Draft can receive a new active schedule. The application uses UTC for this MVP and does not implement per-brand timezones.

Future improvements explicitly deferred: Meta publishing, publication retries and attempts, scheduled workers, recurrence, per-brand timezones, campaign calendars, drag-and-drop calendars, Strategy auto-scheduling, best-time analytics, failed publication queues, notifications, and complete publication history.

## Meta Connection v1

Meta Connection stores one manual connection per Brand. It records optional Facebook Page and Instagram Professional Account identifiers separately, encrypts the access token with Laravel's `encrypted` cast, and shows only whether a token is configured. Saving manual configuration always results in `configured_unverified`.

The explicit verification action uses Instagram API with Instagram Login and sends one authenticated `GET https://graph.instagram.com/v26.0/me?fields=user_id,username` request. The returned `user_id` must match the configured Instagram Account ID before the connection becomes `verified`. Remote errors, invalid responses, and account mismatches are stored only as sanitized messages. `last_verified_at` represents the last successful verification and is preserved when a later re-verification fails. Verification never creates media containers or publications.

The current official Meta documentation distinguishes Facebook Page access tokens from Instagram access tokens depending on the login path. It also describes Page-to-Instagram Professional Account relationships and different permission sets. SocialFlow therefore stores IDs and an optional scopes snapshot without hard-coding permissions or claiming that a connection is verified. Exact scopes, token lifecycle, OAuth, Page selection, reconnect, revocation, and app review remain for a future integration step.

The next milestone is the Meta Publishing Boundary: `ScheduledPublication READY` -> `PublicationService` -> `MetaPublisherInterface` -> `PublicationAttempt` with idempotency and fake HTTP tests. No part of that publishing boundary is implemented here.
