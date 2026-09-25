# Graph Report - SocialFlowAI  (2026-09-25)

## Corpus Check
- 173 files · ~66,506 words
- Verdict: corpus is large enough that graph structure adds value.
- Unclassified: 20 file(s) not represented in the graph (top: (none) 16, .example 1, .xml 1)

## Summary
- 1071 nodes · 1467 edges · 112 communities (75 shown, 37 thin omitted)
- Extraction: 99% EXTRACTED · 1% INFERRED · 0% AMBIGUOUS · INFERRED: 21 edges (avg confidence: 0.95)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `f808570c`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- composer.json
- User
- Illuminate\Support\Collection
- Illuminate\Database\Migrations\Migration
- Cloud CLI
- Laravel Boost Guidelines
- Laravel Boost Guidelines
- ContextSnapshot
- package.json
- .claude/skills/testing-best-practices/SKILL.md
- Illuminate\Foundation\Http\FormRequest
- Assertions
- .agents/skills/testing-best-practices/SKILL.md
- UserFactory.php
- Factories and Test Data
- AGENTS.md
- Illuminate\View\View
- Illuminate\Database\Seeder
- .setUp
- Detection Checklist
- Process
- Security Best Practices
- Naming and Structure
- Detection Checklist
- Process
- Architecture Best Practices
- Security Best Practices
- Architecture Best Practices
- Tailwind CSS Development
- Tailwind CSS Development
- Advanced Query Best Practices
- Migration Best Practices
- Queue and Job Best Practices
- Advanced Query Best Practices
- Migration Best Practices
- Queue and Job Best Practices
- Caching Best Practices
- Database Performance Best Practices
- Eloquent Best Practices
- Events and Notifications Best Practices
- Database Performance Best Practices
- Eloquent Best Practices
- Events and Notifications Best Practices
- .agents/skills/laravel-best-practices/SKILL.md
- Blade and View Best Practices
- Error Handling Best Practices
- Task Scheduling Best Practices
- Endpoint Tests
- Blade and View Best Practices
- .claude/skills/laravel-best-practices/SKILL.md
- Caching Best Practices
- Brand
- Task Scheduling Best Practices
- KnowledgeEntry
- README.md
- Collection Best Practices
- HTTP Client Best Practices
- Mail Best Practices
- Routing and Controller Best Practices
- Convention and Style Best Practices
- Validation and Forms Best Practices
- Fakes, Mocks, and Determinism
- ContextRetrievalTest
- PromptAssemblyTest
- Collection Best Practices
- HTTP Client Best Practices
- Mail Best Practices
- Routing and Controller Best Practices
- Convention and Style Best Practices
- Validation and Forms Best Practices
- .storeDraft
- Controller
- Configuration Best Practices
- Test Suite Performance
- Reviewing Tests
- Configuration Best Practices
- bootstrap/app.php
- Reviewing Tests
- logging.php
- ExampleTest
- artisan
- laravel-boost
- console.php
- brands/create.blade.php
- brands/edit.blade.php
- drafts/create.blade.php
- drafts/edit.blade.php
- knowledge/create.blade.php
- knowledge/edit.blade.php
- copilot-instructions.md

## God Nodes (most connected - your core abstractions)
1. `Brand` - 75 edges
2. `KnowledgeEntry` - 38 edges
3. `User` - 32 edges
4. `TestCase` - 22 edges
5. `ContextSnapshot` - 19 edges
6. `Draft` - 19 edges
7. `ContextRetrievalTest` - 15 edges
8. `PromptAssemblyTest` - 14 edges
9. `KnowledgeEntryController` - 13 edges
10. `ContextBuilder` - 13 edges

## Surprising Connections (you probably didn't know these)
- `Follow Project Naming Conventions` --references--> `User`  [INFERRED]
  app-laravel/.agents/skills/laravel-best-practices/rules/style.md → app-laravel/app/Models/User.php
- `Follow Project Naming Conventions` --references--> `User`  [INFERRED]
  app-laravel/.claude/skills/laravel-best-practices/rules/style.md → app-laravel/app/Models/User.php
- `Seguridad y alcance actual` --references--> `ContextBuilder`  [INFERRED]
  docs/setup-local.md → app-laravel/app/Services/Knowledge/ContextBuilder.php
- `Seguridad y alcance actual` --references--> `ContextPackage`  [INFERRED]
  docs/setup-local.md → app-laravel/app/Services/Knowledge/ContextPackage.php
- `Seguridad y alcance actual` --references--> `TextKnowledgeRetriever`  [INFERRED]
  docs/setup-local.md → app-laravel/app/Services/Knowledge/TextKnowledgeRetriever.php

## Import Cycles
- None detected.

## Communities (112 total, 37 thin omitted)

### Community 0 - "composer.json"
Cohesion: 0.04
Nodes (48): pestphp/pest-plugin, php-http/discovery, autoload, autoload-dev, psr-4, psr-4, config, allow-plugins (+40 more)

### Community 1 - "User"
Cohesion: 0.14
Nodes (8): User, AuthenticationTest, ExampleTest, KnowledgeManagementTest, TestCase, Illuminate\Foundation\Testing\LazilyRefreshDatabase, Illuminate\Foundation\Testing\TestCase, Illuminate\Support\ViewErrorBag

### Community 2 - "Illuminate\Support\Collection"
Cohesion: 0.09
Nodes (15): AppServiceProvider, ContextBuilder, ContextPackage, KnowledgeRetrieverInterface, TextKnowledgeRetriever, Base de datos y migraciones, Comandos habituales de Laravel, Diagnóstico de error de sesiones (+7 more)

### Community 3 - "Illuminate\Database\Migrations\Migration"
Cohesion: 0.09
Nodes (4): Illuminate\Database\Migrations\Migration, Illuminate\Database\Schema\Blueprint, Illuminate\Support\Facades\DB, Illuminate\Support\Facades\Schema

### Community 4 - "Cloud CLI"
Cohesion: 0.06
Nodes (30): Adding a cache to an existing environment, Adding a database to an existing environment, Checklists for Multi-Step Operations, Custom domain setup, Full environment setup (app + database + cache + domain), New app from scratch, Application Setup, Billing and Usage (+22 more)

### Community 5 - "Laravel Boost Guidelines"
Cohesion: 0.07
Nodes (27): APIs & Eloquent Resources, Application Structure & Architecture, Artisan, Conventions, Deployment, Do Things the Laravel Way, Documentation Files, Foundational Context (+19 more)

### Community 6 - "Laravel Boost Guidelines"
Cohesion: 0.07
Nodes (27): APIs & Eloquent Resources, Application Structure & Architecture, Artisan, Conventions, Deployment, Do Things the Laravel Way, Documentation Files, Foundational Context (+19 more)

### Community 7 - "ContextSnapshot"
Cohesion: 0.07
Nodes (16): ContextSnapshot, Draft, KnowledgeAudit, ContextSnapshotTest, Illuminate\Database\Eloquent\Attributes\Fillable, Illuminate\Database\Eloquent\Attributes\Hidden, Illuminate\Database\Eloquent\Concerns\HasUlids, Illuminate\Database\Eloquent\Factories\HasFactory (+8 more)

### Community 8 - "package.json"
Cohesion: 0.10
Nodes (20): devDependencies, concurrently, laravel-vite-plugin, tailwindcss, @tailwindcss/vite, vite, optionalDependencies, @laravel/multiplex (+12 more)

### Community 9 - ".claude/skills/testing-best-practices/SKILL.md"
Cohesion: 0.04
Nodes (38): Arrange, Act, Assert, Assert a Known Value, Assert the Complete Result, Assertions, How to Find the Correct Assertion, Named Response Assertions, Endpoint Coverage, Endpoint Tests (+30 more)

### Community 10 - "Illuminate\Foundation\Http\FormRequest"
Cohesion: 0.09
Nodes (7): ContextRequest, DraftRequest, KnowledgeEntryRequest, StoreBrandRequest, UpdateBrandRequest, Illuminate\Foundation\Http\FormRequest, Illuminate\Validation\Rule

### Community 11 - "Assertions"
Cohesion: 0.29
Nodes (6): Arrange, Act, Assert, Assert a Known Value, Assert the Complete Result, Assertions, How to Find the Correct Assertion, Named Response Assertions

### Community 12 - ".agents/skills/testing-best-practices/SKILL.md"
Cohesion: 0.17
Nodes (8): Built-in Laravel Assertion Methods, How to Find Test Framework Features, Security Tests, Consistency First, How to Apply, Rule Index, Testing Best Practices, What to Test

### Community 13 - "UserFactory.php"
Cohesion: 0.16
Nodes (7): BrandFactory, UserFactory, Illuminate\Database\Eloquent\Factories\Factory, Illuminate\Support\Facades\Hash, Illuminate\Support\Str, Pdo\Mysql, static

### Community 14 - "Factories and Test Data"
Cohesion: 0.40
Nodes (4): Data Providers, Each Test Makes Its Own Data, Factories and Test Data, Record Construction

### Community 16 - "Illuminate\View\View"
Cohesion: 0.17
Nodes (6): AuthController, BrandController, Illuminate\Http\RedirectResponse, Illuminate\Http\Request, Illuminate\Support\Facades\Auth, Illuminate\View\View

### Community 17 - "Illuminate\Database\Seeder"
Cohesion: 0.16
Nodes (7): ArtMadeCommercialKnowledgeSeeder, ArtMadeKnowledgeSeeder, BrandSeeder, ConsolidateArtMadeKnowledgeSeeder, DatabaseSeeder, Illuminate\Database\Console\Seeds\WithoutModelEvents, Illuminate\Database\Seeder

### Community 18 - ".setUp"
Cohesion: 0.22
Nodes (7): Framework Fakes, Data and Determinism, Framework Fakes, Data Providers, Each Test Makes Its Own Data, Factories and Test Data, Record Construction

### Community 19 - "Detection Checklist"
Cohesion: 0.17
Nodes (11): A. Validation & HTTP input, B. Controllers & routing, C. Authorization, D. Eloquent & models, Detection Checklist, E. Architecture & organization, F. Frontend & views, G. Database & migrations (+3 more)

### Community 20 - "Process"
Cohesion: 0.17
Nodes (11): Edge cases, Glob mapping, Ground Rules (read before you start), Infer Conventions, Process, Step 0: Orient, Step 1: Predefined sweep, Step 2: Open-ended pass (+3 more)

### Community 21 - "Security Best Practices"
Cohesion: 0.18
Nodes (11): Apply Cross-Site Request Forgery Protection, Audit Dependencies, Authorize Protected Actions, Bind Query Parameters, Control Mass Assignment, Encrypt Sensitive Attributes When Appropriate, Escape Output in Its Context, Keep Secrets Out of Application Code (+3 more)

### Community 22 - "Naming and Structure"
Cohesion: 0.33
Nodes (5): File Layout, Grouping, Naming and Structure, Naming Tests, Test Class and Methods

### Community 23 - "Detection Checklist"
Cohesion: 0.17
Nodes (11): A. Validation & HTTP input, B. Controllers & routing, C. Authorization, D. Eloquent & models, Detection Checklist, E. Architecture & organization, F. Frontend & views, G. Database & migrations (+3 more)

### Community 24 - "Process"
Cohesion: 0.17
Nodes (11): Edge cases, Glob mapping, Ground Rules (read before you start), Infer Conventions, Process, Step 0: Orient, Step 1: Predefined sweep, Step 2: Open-ended pass (+3 more)

### Community 25 - "Architecture Best Practices"
Cohesion: 0.18
Nodes (11): Architecture Best Practices, Depend on Contracts at Boundaries, Extract Focused Business Operations, Follow Framework Conventions, Inject Required Dependencies, Specify a Deterministic Sort Order, Use Atomic Locks for Race Conditions, Use `Concurrency::run()` for Parallel Execution (+3 more)

### Community 26 - "Security Best Practices"
Cohesion: 0.18
Nodes (11): Apply Cross-Site Request Forgery Protection, Audit Dependencies, Authorize Protected Actions, Bind Query Parameters, Control Mass Assignment, Encrypt Sensitive Attributes When Appropriate, Escape Output in Its Context, Keep Secrets Out of Application Code (+3 more)

### Community 27 - "Architecture Best Practices"
Cohesion: 0.18
Nodes (11): Architecture Best Practices, Depend on Contracts at Boundaries, Extract Focused Business Operations, Follow Framework Conventions, Inject Required Dependencies, Specify a Deterministic Sort Order, Use Atomic Locks for Race Conditions, Use `Concurrency::run()` for Parallel Execution (+3 more)

### Community 28 - "Tailwind CSS Development"
Cohesion: 0.18
Nodes (10): Basic Usage, Common Pitfalls, CSS-First Configuration, Dark Mode, Documentation, Import Syntax, Replaced Utilities, Spacing (+2 more)

### Community 29 - "Tailwind CSS Development"
Cohesion: 0.18
Nodes (10): Basic Usage, Common Pitfalls, CSS-First Configuration, Dark Mode, Documentation, Import Syntax, Replaced Utilities, Spacing (+2 more)

### Community 30 - "Advanced Query Best Practices"
Cohesion: 0.20
Nodes (9): Advanced Query Best Practices, Combine Related Counts with Conditional Aggregates, Compare `whereHas()` with an `IN` Subquery, Consider a Correlated Subquery for Has-Many Ordering, Create Dynamic Relationships with a Subquery Foreign Key, Design Composite Indexes for the Query, Measure Two Simple Queries Against One Complex Query, Reuse Loaded Parent Models with `setRelation()` (+1 more)

### Community 31 - "Migration Best Practices"
Cohesion: 0.20
Nodes (9): Define Foreign-Key Constraints Deliberately, Design Indexes for Real Queries, Generate Migrations with Artisan, Keep Migrations Focused, Make Rollbacks Honest, Migration Best Practices, Mirror Defaults Only When Unsaved Models Need Them, Stage Changes That Affect Existing Rows (+1 more)

### Community 32 - "Queue and Job Best Practices"
Cohesion: 0.20
Nodes (9): Back Off Transient Failures, Batch Jobs for Group Coordination, Configure Time-Based Retry Limits Deliberately, Handle Terminal Failure When Needed, Keep Reservation Time Longer Than Execution Time, Queue and Job Best Practices, Rate Limit External Calls, Use Horizon for Redis Queue Operations (+1 more)

### Community 33 - "Advanced Query Best Practices"
Cohesion: 0.20
Nodes (9): Advanced Query Best Practices, Combine Related Counts with Conditional Aggregates, Compare `whereHas()` with an `IN` Subquery, Consider a Correlated Subquery for Has-Many Ordering, Create Dynamic Relationships with a Subquery Foreign Key, Design Composite Indexes for the Query, Measure Two Simple Queries Against One Complex Query, Reuse Loaded Parent Models with `setRelation()` (+1 more)

### Community 34 - "Migration Best Practices"
Cohesion: 0.20
Nodes (9): Define Foreign-Key Constraints Deliberately, Design Indexes for Real Queries, Generate Migrations with Artisan, Keep Migrations Focused, Make Rollbacks Honest, Migration Best Practices, Mirror Defaults Only When Unsaved Models Need Them, Stage Changes That Affect Existing Rows (+1 more)

### Community 35 - "Queue and Job Best Practices"
Cohesion: 0.20
Nodes (9): Back Off Transient Failures, Batch Jobs for Group Coordination, Configure Time-Based Retry Limits Deliberately, Handle Terminal Failure When Needed, Keep Reservation Time Longer Than Execution Time, Queue and Job Best Practices, Rate Limit External Calls, Use Horizon for Redis Queue Operations (+1 more)

### Community 36 - "Caching Best Practices"
Cohesion: 0.22
Nodes (8): Caching Best Practices, Configure Failover Cache Stores in Production, Consider `Cache::flexible()` for Stale-While-Revalidate, Use `Cache::add()` for Atomic Conditional Writes, Use `Cache::memo()` to Avoid Redundant Hits Within an Execution, Use `Cache::remember()` for Cache-Aside Reads, Use Cache Tags to Invalidate Related Groups, Use `once()` for In-Process Memoization

### Community 37 - "Database Performance Best Practices"
Cohesion: 0.22
Nodes (8): Add Indexes for Measured Query Patterns, Count Relationships Without Loading Them, Database Performance Best Practices, Eager Load Relationships Before Iterating, Keep Queries Out of Blade Templates, Prevent Lazy Loading in Development, Process Large Data Sets Incrementally, Select Only Needed Columns

### Community 38 - "Eloquent Best Practices"
Cohesion: 0.22
Nodes (8): Apply Global Scopes Sparingly, Cast Date and Time Attributes, Define Attribute Casts, Define Precise Relationship Types, Eloquent Best Practices, Keep Application Queries Model-Aware, Use Local Scopes for Reusable Queries, Use `whereBelongsTo()` for Relationship Queries

### Community 39 - "Events and Notifications Best Practices"
Cohesion: 0.20
Nodes (9): Cache Event Discovery During Production Deployment, Dispatch Queued Notifications After Commit, Events and Notifications Best Practices, Implement `HasLocalePreference` on Notifiable Models, Queue Slow Notifications, Rely on Event Discovery, Route Notification Channels to Dedicated Queues, Use On-Demand Notifications for Non-User Recipients (+1 more)

### Community 40 - "Database Performance Best Practices"
Cohesion: 0.22
Nodes (8): Add Indexes for Measured Query Patterns, Count Relationships Without Loading Them, Database Performance Best Practices, Eager Load Relationships Before Iterating, Keep Queries Out of Blade Templates, Prevent Lazy Loading in Development, Process Large Data Sets Incrementally, Select Only Needed Columns

### Community 41 - "Eloquent Best Practices"
Cohesion: 0.22
Nodes (8): Apply Global Scopes Sparingly, Cast Date and Time Attributes, Define Attribute Casts, Define Precise Relationship Types, Eloquent Best Practices, Keep Application Queries Model-Aware, Use Local Scopes for Reusable Queries, Use `whereBelongsTo()` for Relationship Queries

### Community 42 - "Events and Notifications Best Practices"
Cohesion: 0.20
Nodes (9): Cache Event Discovery During Production Deployment, Dispatch Queued Notifications After Commit, Events and Notifications Best Practices, Implement `HasLocalePreference` on Notifiable Models, Queue Slow Notifications, Rely on Event Discovery, Route Notification Channels to Dedicated Queues, Use On-Demand Notifications for Non-User Recipients (+1 more)

### Community 43 - ".agents/skills/laravel-best-practices/SKILL.md"
Cohesion: 0.25
Nodes (5): Consistency First, Decision Rules, How to Apply, Laravel Best Practices, Rule Index

### Community 44 - "Blade and View Best Practices"
Cohesion: 0.25
Nodes (7): Blade and View Best Practices, Prefer Components for Explicit Interfaces, Return Blade Fragments for Partial Rendering, Share Compatible View Data with a View Composer, Share Parent Component Props with `@aware`, Use `$attributes->merge()` in Component Templates, Use `@pushOnce` for Per-Component Scripts

### Community 45 - "Error Handling Best Practices"
Cohesion: 0.10
Nodes (15): Add Context to Exception Classes, Choose Where to Report and Render Exceptions, Define JSON Rendering for API Routes, Error Handling Best Practices, Mark Exceptions the Handler Should Not Report, Prevent Duplicate Reports of One Exception Instance, Throttle High-Volume Exception Reports, GenerationPrompt (+7 more)

### Community 46 - "Task Scheduling Best Practices"
Cohesion: 0.25
Nodes (7): Bound Work Inside the Task, Group Shared Configuration, Prevent Unwanted Overlap, Restrict Tasks by Environment, Run a Task on One Server, Run Eligible Commands in the Background, Task Scheduling Best Practices

### Community 47 - "Endpoint Tests"
Cohesion: 0.25
Nodes (7): Endpoint Coverage, Endpoint Tests, How to Write the Test, Tenant Isolation, Test Authorization at the Policy Level, Testing Validation, Which Layer Owns Which Case

### Community 48 - "Blade and View Best Practices"
Cohesion: 0.25
Nodes (7): Blade and View Best Practices, Prefer Components for Explicit Interfaces, Return Blade Fragments for Partial Rendering, Share Compatible View Data with a View Composer, Share Parent Component Props with `@aware`, Use `$attributes->merge()` in Component Templates, Use `@pushOnce` for Per-Component Scripts

### Community 49 - ".claude/skills/laravel-best-practices/SKILL.md"
Cohesion: 0.25
Nodes (5): Consistency First, Decision Rules, How to Apply, Laravel Best Practices, Rule Index

### Community 50 - "Caching Best Practices"
Cohesion: 0.22
Nodes (8): Caching Best Practices, Configure Failover Cache Stores in Production, Consider `Cache::flexible()` for Stale-While-Revalidate, Use `Cache::add()` for Atomic Conditional Writes, Use `Cache::memo()` to Avoid Redundant Hits Within an Execution, Use `Cache::remember()` for Cache-Aside Reads, Use Cache Tags to Invalidate Related Groups, Use `once()` for In-Process Memoization

### Community 51 - "Brand"
Cohesion: 0.24
Nodes (3): DraftController, Brand, BrandManagementTest

### Community 52 - "Task Scheduling Best Practices"
Cohesion: 0.25
Nodes (7): Bound Work Inside the Task, Group Shared Configuration, Prevent Unwanted Overlap, Restrict Tasks by Environment, Run a Task on One Server, Run Eligible Commands in the Background, Task Scheduling Best Practices

### Community 53 - "KnowledgeEntry"
Cohesion: 0.33
Nodes (3): KnowledgeEntryController, KnowledgeEntry, RetrievalMatch

### Community 54 - "README.md"
Cohesion: 0.25
Nodes (7): About Laravel, Agentic Development, Code of Conduct, Contributing, Learning Laravel, License, Security Vulnerabilities

### Community 55 - "Collection Best Practices"
Cohesion: 0.29
Nodes (6): Choose Between `cursor()` and `lazy()`, Collection Best Practices, Use `#[CollectedBy]` for Custom Collection Classes, Use Higher-Order Messages for Simple Operations, Use `lazyById()` When Updating Records While Iterating, Use `toQuery()` for Bulk Operations on Collections

### Community 56 - "HTTP Client Best Practices"
Cohesion: 0.29
Nodes (6): Fake HTTP Requests in Tests, Handle Errors Explicitly, HTTP Client Best Practices, Pool Independent Requests, Retry Only Safe Operations, Set Explicit Timeouts

### Community 57 - "Mail Best Practices"
Cohesion: 0.29
Nodes (6): Assert the Delivery Mode, Dispatch Queued Mail After Commit, Mail Best Practices, Queue Slow Mail Delivery, Separate Content and Delivery Tests, Use Markdown Mailables When They Fit

### Community 58 - "Routing and Controller Best Practices"
Cohesion: 0.29
Nodes (6): Keep Controllers Focused on HTTP Concerns, Organize Controllers Around Resources, Routing and Controller Best Practices, Scope Nested Bindings, Use Implicit Route Model Binding, Use Resource Routes for Resourceful Actions

### Community 59 - "Convention and Style Best Practices"
Cohesion: 0.29
Nodes (6): Convention and Style Best Practices, Follow Project Naming Conventions, Keep Presentation Code Maintainable, Prefer Clear, Idiomatic Syntax, Use Utilities When They Clarify Intent, Write Comments That Explain Why

### Community 60 - "Validation and Forms Best Practices"
Cohesion: 0.29
Nodes (6): Add Cross-Field Validation After Base Rules, Express Conditional Rules Clearly, Extract Validation When It Improves the Boundary, Prefer Readable Rule Syntax, Use Only Intended Validated Data, Validation and Forms Best Practices

### Community 61 - "Fakes, Mocks, and Determinism"
Cohesion: 0.29
Nodes (6): Database, Fakes, Mocks, and Determinism, How to Isolate a Dependency, Mocking, Outbound HTTP Testing, Time and Randomness

### Community 64 - "Collection Best Practices"
Cohesion: 0.29
Nodes (6): Choose Between `cursor()` and `lazy()`, Collection Best Practices, Use `#[CollectedBy]` for Custom Collection Classes, Use Higher-Order Messages for Simple Operations, Use `lazyById()` When Updating Records While Iterating, Use `toQuery()` for Bulk Operations on Collections

### Community 65 - "HTTP Client Best Practices"
Cohesion: 0.29
Nodes (6): Fake HTTP Requests in Tests, Handle Errors Explicitly, HTTP Client Best Practices, Pool Independent Requests, Retry Only Safe Operations, Set Explicit Timeouts

### Community 66 - "Mail Best Practices"
Cohesion: 0.29
Nodes (6): Assert the Delivery Mode, Dispatch Queued Mail After Commit, Mail Best Practices, Queue Slow Mail Delivery, Separate Content and Delivery Tests, Use Markdown Mailables When They Fit

### Community 67 - "Routing and Controller Best Practices"
Cohesion: 0.29
Nodes (6): Keep Controllers Focused on HTTP Concerns, Organize Controllers Around Resources, Routing and Controller Best Practices, Scope Nested Bindings, Use Implicit Route Model Binding, Use Resource Routes for Resourceful Actions

### Community 68 - "Convention and Style Best Practices"
Cohesion: 0.29
Nodes (6): Convention and Style Best Practices, Follow Project Naming Conventions, Keep Presentation Code Maintainable, Prefer Clear, Idiomatic Syntax, Use Utilities When They Clarify Intent, Write Comments That Explain Why

### Community 69 - "Validation and Forms Best Practices"
Cohesion: 0.29
Nodes (6): Add Cross-Field Validation After Base Rules, Express Conditional Rules Clearly, Extract Validation When It Improves the Boundary, Prefer Readable Rule Syntax, Use Only Intended Validated Data, Validation and Forms Best Practices

### Community 71 - "Controller"
Cohesion: 0.38
Nodes (3): Controller, PromptController, PromptComposer

### Community 72 - "Configuration Best Practices"
Cohesion: 0.33
Nodes (5): Configuration Best Practices, Name Repeated Domain Values, Protect Production Secrets, Read Environment Variables in Configuration Files, Use `App::environment()` for Environment Checks

### Community 73 - "Test Suite Performance"
Cohesion: 0.33
Nodes (6): Common Errors, Global Fakes, How to Find a Slow Test, How to Run the Suite in Parallel, Test Environment, Test Suite Performance

### Community 74 - "Reviewing Tests"
Cohesion: 0.33
Nodes (5): Assertions, Coverage, Names and Structure, Reviewing Tests, Test Value

### Community 75 - "Configuration Best Practices"
Cohesion: 0.33
Nodes (5): Configuration Best Practices, Name Repeated Domain Values, Protect Production Secrets, Read Environment Variables in Configuration Files, Use `App::environment()` for Environment Checks

### Community 76 - "bootstrap/app.php"
Cohesion: 0.40
Nodes (3): Illuminate\Foundation\Application, Illuminate\Foundation\Configuration\Exceptions, Illuminate\Foundation\Configuration\Middleware

### Community 77 - "Reviewing Tests"
Cohesion: 0.33
Nodes (6): Assertions, Coverage, Data and Determinism, Names and Structure, Reviewing Tests, Test Value

### Community 79 - "logging.php"
Cohesion: 0.40
Nodes (4): Monolog\Handler\NullHandler, Monolog\Handler\StreamHandler, Monolog\Handler\SyslogUdpHandler, Monolog\Processor\PsrLogMessageProcessor

## Knowledge Gaps
- **516 isolated node(s):** `php`, `$schema`, `name`, `type`, `description` (+511 more)
  These have ≤1 connection - possible missing edges or undocumented components. (Counts symbols only; 624 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **37 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `User` connect `User` to `Convention and Style Best Practices`, `ContextSnapshot`, `UserFactory.php`, `Illuminate\View\View`, `Illuminate\Database\Seeder`, `Brand`, `Convention and Style Best Practices`, `ContextRetrievalTest`, `PromptAssemblyTest`?**
  _High betweenness centrality (0.285) - this node is a cross-community bridge._
- **Why does `Follow Project Naming Conventions` connect `Convention and Style Best Practices` to `User`?**
  _High betweenness centrality (0.158) - this node is a cross-community bridge._
- **Are the 2 inferred relationships involving `User` (e.g. with `Follow Project Naming Conventions` and `Follow Project Naming Conventions`) actually correct?**
  _`User` has 2 INFERRED edges - model-reasoned connections that need verification._
- **Are the 6 inferred relationships involving `TestCase` (e.g. with `Test Class and Methods` and `Global Fakes`) actually correct?**
  _`TestCase` has 6 INFERRED edges - model-reasoned connections that need verification._
- **What connects `php`, `$schema`, `name` to the rest of the system?**
  _516 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `composer.json` be split into smaller, more focused modules?**
  _Cohesion score 0.04081632653061224 - nodes in this community are weakly interconnected._
- **Should `User` be split into smaller, more focused modules?**
  _Cohesion score 0.14461538461538462 - nodes in this community are weakly interconnected._