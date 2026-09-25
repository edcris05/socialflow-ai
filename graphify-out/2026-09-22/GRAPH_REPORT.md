# Graph Report - SocialFlowAI  (2026-09-21)

## Corpus Check
- 128 files · ~56,181 words
- Verdict: corpus is large enough that graph structure adds value.
- Unclassified: 20 file(s) not represented in the graph (top: (none) 16, .example 1, .xml 1)

## Summary
- 870 nodes · 957 edges · 91 communities (68 shown, 23 thin omitted)
- Extraction: 96% EXTRACTED · 4% INFERRED · 0% AMBIGUOUS · INFERRED: 39 edges (avg confidence: 0.95)
- Token cost: 0 input · 0 output

## Community Hubs (Navigation)
- .update
- composer.json
- package.json
- Brand
- StoreBrandRequest
- Security Best Practices
- 0001_01_01_000000_create_users_table.php
- .agents/skills/testing-best-practices/SKILL.md
- .claude/skills/testing-best-practices/SKILL.md
- Naming and Structure
- AppServiceProvider
- .setUp
- logging.php
- ExampleTest
- artisan
- laravel-boost
- console.php
- create.blade.php
- edit.blade.php
- Cloud CLI
- Laravel Boost Guidelines
- Laravel Boost Guidelines
- Detection Checklist
- Process
- Detection Checklist
- Process
- Architecture Best Practices
- Security Best Practices
- Tailwind CSS Development
- Architecture Best Practices
- Tailwind CSS Development
- Advanced Query Best Practices
- Events and Notifications Best Practices
- Migration Best Practices
- Queue and Job Best Practices
- Advanced Query Best Practices
- Events and Notifications Best Practices
- Migration Best Practices
- Caching Best Practices
- Database Performance Best Practices
- Database Performance Best Practices
- Eloquent Best Practices
- Queue and Job Best Practices
- Entorno local
- .agents/skills/laravel-best-practices/SKILL.md
- Blade and View Best Practices
- Eloquent Best Practices
- Error Handling Best Practices
- Task Scheduling Best Practices
- Endpoint Tests
- Blade and View Best Practices
- Caching Best Practices
- Error Handling Best Practices
- Task Scheduling Best Practices
- README.md
- Collection Best Practices
- HTTP Client Best Practices
- Mail Best Practices
- Convention and Style Best Practices
- Validation and Forms Best Practices
- Collection Best Practices
- HTTP Client Best Practices
- Mail Best Practices
- Assertions
- Validation and Forms Best Practices
- Configuration Best Practices
- Configuration Best Practices
- .claude/skills/laravel-best-practices/SKILL.md
- Fakes, Mocks, and Determinism
- Test Suite Performance
- copilot-instructions.md
- Reviewing Tests
- Naming and Structure
- Reviewing Tests
- TestCase
- Testing Best Practices

## God Nodes (most connected - your core abstractions)
1. `Brand` - 21 edges
2. `User` - 20 edges
3. `TestCase` - 14 edges
4. `Cloud CLI` - 12 edges
5. `Detection Checklist` - 11 edges
6. `Architecture Best Practices` - 11 edges
7. `Security Best Practices` - 11 edges
8. `Detection Checklist` - 11 edges
9. `Architecture Best Practices` - 11 edges
10. `Security Best Practices` - 11 edges

## Surprising Connections (you probably didn't know these)
- `Follow Project Naming Conventions` --references--> `User`  [INFERRED]
  app-laravel/.agents/skills/laravel-best-practices/rules/style.md → app-laravel/app/Models/User.php
- `Follow Project Naming Conventions` --references--> `User`  [INFERRED]
  app-laravel/.claude/skills/laravel-best-practices/rules/style.md → app-laravel/app/Models/User.php
- `Test Class and Methods` --references--> `TestCase`  [INFERRED]
  app-laravel/.agents/skills/testing-best-practices/rules/naming.md → app-laravel/tests/TestCase.php
- `Names and Structure` --references--> `TestCase`  [INFERRED]
  app-laravel/.agents/skills/testing-best-practices/rules/review.md → app-laravel/tests/TestCase.php
- `Test Class and Methods` --references--> `TestCase`  [INFERRED]
  app-laravel/.claude/skills/testing-best-practices/rules/naming.md → app-laravel/tests/TestCase.php

## Import Cycles
- None detected.

## Communities (91 total, 23 thin omitted)

### Community 0 - ".update"
Cohesion: 0.08
Nodes (25): Keep Controllers Focused on HTTP Concerns, Organize Controllers Around Resources, Routing and Controller Best Practices, Scope Nested Bindings, Use Implicit Route Model Binding, Use Resource Routes for Resourceful Actions, Control Mass Assignment, AuthController (+17 more)

### Community 1 - "composer.json"
Cohesion: 0.04
Nodes (48): pestphp/pest-plugin, php-http/discovery, autoload, autoload-dev, psr-4, psr-4, config, allow-plugins (+40 more)

### Community 2 - "package.json"
Cohesion: 0.10
Nodes (20): devDependencies, concurrently, laravel-vite-plugin, tailwindcss, @tailwindcss/vite, vite, optionalDependencies, @laravel/multiplex (+12 more)

### Community 3 - "Brand"
Cohesion: 0.06
Nodes (24): Brand, User, BrandFactory, UserFactory, BrandSeeder, DatabaseSeeder, AuthenticationTest, BrandManagementTest (+16 more)

### Community 4 - "StoreBrandRequest"
Cohesion: 0.17
Nodes (4): StoreBrandRequest, UpdateBrandRequest, Illuminate\Foundation\Http\FormRequest, Illuminate\Validation\Rule

### Community 5 - "Security Best Practices"
Cohesion: 0.20
Nodes (10): Apply Cross-Site Request Forgery Protection, Audit Dependencies, Authorize Protected Actions, Bind Query Parameters, Encrypt Sensitive Attributes When Appropriate, Escape Output in Its Context, Keep Secrets Out of Application Code, Rate Limit Sensitive Endpoints (+2 more)

### Community 6 - "0001_01_01_000000_create_users_table.php"
Cohesion: 0.16
Nodes (3): Illuminate\Database\Migrations\Migration, Illuminate\Database\Schema\Blueprint, Illuminate\Support\Facades\Schema

### Community 7 - ".agents/skills/testing-best-practices/SKILL.md"
Cohesion: 0.05
Nodes (31): Arrange, Act, Assert, Assert a Known Value, Assert the Complete Result, Assertions, How to Find the Correct Assertion, Named Response Assertions, Endpoint Coverage, Endpoint Tests (+23 more)

### Community 8 - ".claude/skills/testing-best-practices/SKILL.md"
Cohesion: 0.15
Nodes (8): Built-in Laravel Assertion Methods, How to Find Test Framework Features, Common Errors, How to Find a Slow Test, How to Run the Suite in Parallel, Test Environment, Test Suite Performance, Security Tests

### Community 9 - "Naming and Structure"
Cohesion: 0.33
Nodes (5): File Layout, Grouping, Naming and Structure, Naming Tests, Test Class and Methods

### Community 11 - ".setUp"
Cohesion: 0.22
Nodes (7): Framework Fakes, Global Fakes, Data and Determinism, Data Providers, Each Test Makes Its Own Data, Factories and Test Data, Record Construction

### Community 12 - "logging.php"
Cohesion: 0.40
Nodes (4): Monolog\Handler\NullHandler, Monolog\Handler\StreamHandler, Monolog\Handler\SyslogUdpHandler, Monolog\Processor\PsrLogMessageProcessor

### Community 32 - "Cloud CLI"
Cohesion: 0.06
Nodes (30): Adding a cache to an existing environment, Adding a database to an existing environment, Checklists for Multi-Step Operations, Custom domain setup, Full environment setup (app + database + cache + domain), New app from scratch, Application Setup, Billing and Usage (+22 more)

### Community 33 - "Laravel Boost Guidelines"
Cohesion: 0.07
Nodes (27): APIs & Eloquent Resources, Application Structure & Architecture, Artisan, Conventions, Deployment, Do Things the Laravel Way, Documentation Files, Foundational Context (+19 more)

### Community 34 - "Laravel Boost Guidelines"
Cohesion: 0.07
Nodes (27): APIs & Eloquent Resources, Application Structure & Architecture, Artisan, Conventions, Deployment, Do Things the Laravel Way, Documentation Files, Foundational Context (+19 more)

### Community 35 - "Detection Checklist"
Cohesion: 0.17
Nodes (11): A. Validation & HTTP input, B. Controllers & routing, C. Authorization, D. Eloquent & models, Detection Checklist, E. Architecture & organization, F. Frontend & views, G. Database & migrations (+3 more)

### Community 36 - "Process"
Cohesion: 0.17
Nodes (11): Edge cases, Glob mapping, Ground Rules (read before you start), Infer Conventions, Process, Step 0: Orient, Step 1: Predefined sweep, Step 2: Open-ended pass (+3 more)

### Community 37 - "Detection Checklist"
Cohesion: 0.17
Nodes (11): A. Validation & HTTP input, B. Controllers & routing, C. Authorization, D. Eloquent & models, Detection Checklist, E. Architecture & organization, F. Frontend & views, G. Database & migrations (+3 more)

### Community 38 - "Process"
Cohesion: 0.17
Nodes (11): Edge cases, Glob mapping, Ground Rules (read before you start), Infer Conventions, Process, Step 0: Orient, Step 1: Predefined sweep, Step 2: Open-ended pass (+3 more)

### Community 39 - "Architecture Best Practices"
Cohesion: 0.18
Nodes (11): Architecture Best Practices, Depend on Contracts at Boundaries, Extract Focused Business Operations, Follow Framework Conventions, Inject Required Dependencies, Specify a Deterministic Sort Order, Use Atomic Locks for Race Conditions, Use `Concurrency::run()` for Parallel Execution (+3 more)

### Community 40 - "Security Best Practices"
Cohesion: 0.18
Nodes (10): Apply Cross-Site Request Forgery Protection, Audit Dependencies, Authorize Protected Actions, Bind Query Parameters, Encrypt Sensitive Attributes When Appropriate, Escape Output in Its Context, Keep Secrets Out of Application Code, Rate Limit Sensitive Endpoints (+2 more)

### Community 41 - "Tailwind CSS Development"
Cohesion: 0.18
Nodes (10): Basic Usage, Common Pitfalls, CSS-First Configuration, Dark Mode, Documentation, Import Syntax, Replaced Utilities, Spacing (+2 more)

### Community 42 - "Architecture Best Practices"
Cohesion: 0.17
Nodes (11): Architecture Best Practices, Depend on Contracts at Boundaries, Extract Focused Business Operations, Follow Framework Conventions, Inject Required Dependencies, Specify a Deterministic Sort Order, Use Atomic Locks for Race Conditions, Use `Concurrency::run()` for Parallel Execution (+3 more)

### Community 43 - "Tailwind CSS Development"
Cohesion: 0.18
Nodes (10): Basic Usage, Common Pitfalls, CSS-First Configuration, Dark Mode, Documentation, Import Syntax, Replaced Utilities, Spacing (+2 more)

### Community 44 - "Advanced Query Best Practices"
Cohesion: 0.20
Nodes (9): Advanced Query Best Practices, Combine Related Counts with Conditional Aggregates, Compare `whereHas()` with an `IN` Subquery, Consider a Correlated Subquery for Has-Many Ordering, Create Dynamic Relationships with a Subquery Foreign Key, Design Composite Indexes for the Query, Measure Two Simple Queries Against One Complex Query, Reuse Loaded Parent Models with `setRelation()` (+1 more)

### Community 45 - "Events and Notifications Best Practices"
Cohesion: 0.20
Nodes (9): Cache Event Discovery During Production Deployment, Dispatch Queued Notifications After Commit, Events and Notifications Best Practices, Implement `HasLocalePreference` on Notifiable Models, Queue Slow Notifications, Rely on Event Discovery, Route Notification Channels to Dedicated Queues, Use On-Demand Notifications for Non-User Recipients (+1 more)

### Community 46 - "Migration Best Practices"
Cohesion: 0.22
Nodes (9): Define Foreign-Key Constraints Deliberately, Design Indexes for Real Queries, Generate Migrations with Artisan, Keep Migrations Focused, Make Rollbacks Honest, Migration Best Practices, Mirror Defaults Only When Unsaved Models Need Them, Stage Changes That Affect Existing Rows (+1 more)

### Community 47 - "Queue and Job Best Practices"
Cohesion: 0.20
Nodes (9): Back Off Transient Failures, Batch Jobs for Group Coordination, Configure Time-Based Retry Limits Deliberately, Handle Terminal Failure When Needed, Keep Reservation Time Longer Than Execution Time, Queue and Job Best Practices, Rate Limit External Calls, Use Horizon for Redis Queue Operations (+1 more)

### Community 48 - "Advanced Query Best Practices"
Cohesion: 0.20
Nodes (9): Advanced Query Best Practices, Combine Related Counts with Conditional Aggregates, Compare `whereHas()` with an `IN` Subquery, Consider a Correlated Subquery for Has-Many Ordering, Create Dynamic Relationships with a Subquery Foreign Key, Design Composite Indexes for the Query, Measure Two Simple Queries Against One Complex Query, Reuse Loaded Parent Models with `setRelation()` (+1 more)

### Community 49 - "Events and Notifications Best Practices"
Cohesion: 0.20
Nodes (9): Cache Event Discovery During Production Deployment, Dispatch Queued Notifications After Commit, Events and Notifications Best Practices, Implement `HasLocalePreference` on Notifiable Models, Queue Slow Notifications, Rely on Event Discovery, Route Notification Channels to Dedicated Queues, Use On-Demand Notifications for Non-User Recipients (+1 more)

### Community 50 - "Migration Best Practices"
Cohesion: 0.22
Nodes (9): Define Foreign-Key Constraints Deliberately, Design Indexes for Real Queries, Generate Migrations with Artisan, Keep Migrations Focused, Make Rollbacks Honest, Migration Best Practices, Mirror Defaults Only When Unsaved Models Need Them, Stage Changes That Affect Existing Rows (+1 more)

### Community 51 - "Caching Best Practices"
Cohesion: 0.22
Nodes (8): Caching Best Practices, Configure Failover Cache Stores in Production, Consider `Cache::flexible()` for Stale-While-Revalidate, Use `Cache::add()` for Atomic Conditional Writes, Use `Cache::memo()` to Avoid Redundant Hits Within an Execution, Use `Cache::remember()` for Cache-Aside Reads, Use Cache Tags to Invalidate Related Groups, Use `once()` for In-Process Memoization

### Community 52 - "Database Performance Best Practices"
Cohesion: 0.25
Nodes (8): Add Indexes for Measured Query Patterns, Count Relationships Without Loading Them, Database Performance Best Practices, Eager Load Relationships Before Iterating, Keep Queries Out of Blade Templates, Prevent Lazy Loading in Development, Process Large Data Sets Incrementally, Select Only Needed Columns

### Community 53 - "Database Performance Best Practices"
Cohesion: 0.22
Nodes (8): Add Indexes for Measured Query Patterns, Count Relationships Without Loading Them, Database Performance Best Practices, Eager Load Relationships Before Iterating, Keep Queries Out of Blade Templates, Prevent Lazy Loading in Development, Process Large Data Sets Incrementally, Select Only Needed Columns

### Community 54 - "Eloquent Best Practices"
Cohesion: 0.22
Nodes (8): Apply Global Scopes Sparingly, Cast Date and Time Attributes, Define Attribute Casts, Define Precise Relationship Types, Eloquent Best Practices, Keep Application Queries Model-Aware, Use Local Scopes for Reusable Queries, Use `whereBelongsTo()` for Relationship Queries

### Community 55 - "Queue and Job Best Practices"
Cohesion: 0.22
Nodes (9): Back Off Transient Failures, Batch Jobs for Group Coordination, Configure Time-Based Retry Limits Deliberately, Handle Terminal Failure When Needed, Keep Reservation Time Longer Than Execution Time, Queue and Job Best Practices, Rate Limit External Calls, Use Horizon for Redis Queue Operations (+1 more)

### Community 56 - "Entorno local"
Cohesion: 0.22
Nodes (8): Base de datos y migraciones, Comandos habituales de Laravel, Diagnóstico de error de sesiones, Entorno local, Estado actual, Iniciar y detener, Requisitos, Seguridad y alcance actual

### Community 57 - ".agents/skills/laravel-best-practices/SKILL.md"
Cohesion: 0.22
Nodes (5): Consistency First, Decision Rules, How to Apply, Laravel Best Practices, Rule Index

### Community 58 - "Blade and View Best Practices"
Cohesion: 0.25
Nodes (7): Blade and View Best Practices, Prefer Components for Explicit Interfaces, Return Blade Fragments for Partial Rendering, Share Compatible View Data with a View Composer, Share Parent Component Props with `@aware`, Use `$attributes->merge()` in Component Templates, Use `@pushOnce` for Per-Component Scripts

### Community 59 - "Eloquent Best Practices"
Cohesion: 0.22
Nodes (8): Apply Global Scopes Sparingly, Cast Date and Time Attributes, Define Attribute Casts, Define Precise Relationship Types, Eloquent Best Practices, Keep Application Queries Model-Aware, Use Local Scopes for Reusable Queries, Use `whereBelongsTo()` for Relationship Queries

### Community 60 - "Error Handling Best Practices"
Cohesion: 0.25
Nodes (7): Add Context to Exception Classes, Choose Where to Report and Render Exceptions, Define JSON Rendering for API Routes, Error Handling Best Practices, Mark Exceptions the Handler Should Not Report, Prevent Duplicate Reports of One Exception Instance, Throttle High-Volume Exception Reports

### Community 61 - "Task Scheduling Best Practices"
Cohesion: 0.25
Nodes (7): Bound Work Inside the Task, Group Shared Configuration, Prevent Unwanted Overlap, Restrict Tasks by Environment, Run a Task on One Server, Run Eligible Commands in the Background, Task Scheduling Best Practices

### Community 62 - "Endpoint Tests"
Cohesion: 0.25
Nodes (7): Endpoint Coverage, Endpoint Tests, How to Write the Test, Tenant Isolation, Test Authorization at the Policy Level, Testing Validation, Which Layer Owns Which Case

### Community 63 - "Blade and View Best Practices"
Cohesion: 0.25
Nodes (7): Blade and View Best Practices, Prefer Components for Explicit Interfaces, Return Blade Fragments for Partial Rendering, Share Compatible View Data with a View Composer, Share Parent Component Props with `@aware`, Use `$attributes->merge()` in Component Templates, Use `@pushOnce` for Per-Component Scripts

### Community 64 - "Caching Best Practices"
Cohesion: 0.22
Nodes (8): Caching Best Practices, Configure Failover Cache Stores in Production, Consider `Cache::flexible()` for Stale-While-Revalidate, Use `Cache::add()` for Atomic Conditional Writes, Use `Cache::memo()` to Avoid Redundant Hits Within an Execution, Use `Cache::remember()` for Cache-Aside Reads, Use Cache Tags to Invalidate Related Groups, Use `once()` for In-Process Memoization

### Community 65 - "Error Handling Best Practices"
Cohesion: 0.25
Nodes (7): Add Context to Exception Classes, Choose Where to Report and Render Exceptions, Define JSON Rendering for API Routes, Error Handling Best Practices, Mark Exceptions the Handler Should Not Report, Prevent Duplicate Reports of One Exception Instance, Throttle High-Volume Exception Reports

### Community 66 - "Task Scheduling Best Practices"
Cohesion: 0.25
Nodes (7): Bound Work Inside the Task, Group Shared Configuration, Prevent Unwanted Overlap, Restrict Tasks by Environment, Run a Task on One Server, Run Eligible Commands in the Background, Task Scheduling Best Practices

### Community 67 - "README.md"
Cohesion: 0.25
Nodes (7): About Laravel, Agentic Development, Code of Conduct, Contributing, Learning Laravel, License, Security Vulnerabilities

### Community 68 - "Collection Best Practices"
Cohesion: 0.29
Nodes (6): Choose Between `cursor()` and `lazy()`, Collection Best Practices, Use `#[CollectedBy]` for Custom Collection Classes, Use Higher-Order Messages for Simple Operations, Use `lazyById()` When Updating Records While Iterating, Use `toQuery()` for Bulk Operations on Collections

### Community 69 - "HTTP Client Best Practices"
Cohesion: 0.29
Nodes (6): Fake HTTP Requests in Tests, Handle Errors Explicitly, HTTP Client Best Practices, Pool Independent Requests, Retry Only Safe Operations, Set Explicit Timeouts

### Community 70 - "Mail Best Practices"
Cohesion: 0.29
Nodes (6): Assert the Delivery Mode, Dispatch Queued Mail After Commit, Mail Best Practices, Queue Slow Mail Delivery, Separate Content and Delivery Tests, Use Markdown Mailables When They Fit

### Community 71 - "Convention and Style Best Practices"
Cohesion: 0.13
Nodes (12): Convention and Style Best Practices, Follow Project Naming Conventions, Keep Presentation Code Maintainable, Prefer Clear, Idiomatic Syntax, Use Utilities When They Clarify Intent, Write Comments That Explain Why, Convention and Style Best Practices, Follow Project Naming Conventions (+4 more)

### Community 72 - "Validation and Forms Best Practices"
Cohesion: 0.29
Nodes (6): Add Cross-Field Validation After Base Rules, Express Conditional Rules Clearly, Extract Validation When It Improves the Boundary, Prefer Readable Rule Syntax, Use Only Intended Validated Data, Validation and Forms Best Practices

### Community 73 - "Collection Best Practices"
Cohesion: 0.29
Nodes (6): Choose Between `cursor()` and `lazy()`, Collection Best Practices, Use `#[CollectedBy]` for Custom Collection Classes, Use Higher-Order Messages for Simple Operations, Use `lazyById()` When Updating Records While Iterating, Use `toQuery()` for Bulk Operations on Collections

### Community 74 - "HTTP Client Best Practices"
Cohesion: 0.29
Nodes (6): Fake HTTP Requests in Tests, Handle Errors Explicitly, HTTP Client Best Practices, Pool Independent Requests, Retry Only Safe Operations, Set Explicit Timeouts

### Community 75 - "Mail Best Practices"
Cohesion: 0.29
Nodes (6): Assert the Delivery Mode, Dispatch Queued Mail After Commit, Mail Best Practices, Queue Slow Mail Delivery, Separate Content and Delivery Tests, Use Markdown Mailables When They Fit

### Community 76 - "Assertions"
Cohesion: 0.29
Nodes (6): Arrange, Act, Assert, Assert a Known Value, Assert the Complete Result, Assertions, How to Find the Correct Assertion, Named Response Assertions

### Community 77 - "Validation and Forms Best Practices"
Cohesion: 0.29
Nodes (6): Add Cross-Field Validation After Base Rules, Express Conditional Rules Clearly, Extract Validation When It Improves the Boundary, Prefer Readable Rule Syntax, Use Only Intended Validated Data, Validation and Forms Best Practices

### Community 78 - "Configuration Best Practices"
Cohesion: 0.33
Nodes (5): Configuration Best Practices, Name Repeated Domain Values, Protect Production Secrets, Read Environment Variables in Configuration Files, Use `App::environment()` for Environment Checks

### Community 79 - "Configuration Best Practices"
Cohesion: 0.33
Nodes (5): Configuration Best Practices, Name Repeated Domain Values, Protect Production Secrets, Read Environment Variables in Configuration Files, Use `App::environment()` for Environment Checks

### Community 80 - ".claude/skills/laravel-best-practices/SKILL.md"
Cohesion: 0.22
Nodes (5): Consistency First, Decision Rules, How to Apply, Laravel Best Practices, Rule Index

### Community 81 - "Fakes, Mocks, and Determinism"
Cohesion: 0.29
Nodes (7): Database, Fakes, Mocks, and Determinism, Framework Fakes, How to Isolate a Dependency, Mocking, Outbound HTTP Testing, Time and Randomness

### Community 82 - "Test Suite Performance"
Cohesion: 0.33
Nodes (6): Common Errors, Global Fakes, How to Find a Slow Test, How to Run the Suite in Parallel, Test Environment, Test Suite Performance

### Community 84 - "Reviewing Tests"
Cohesion: 0.33
Nodes (6): Assertions, Coverage, Data and Determinism, Names and Structure, Reviewing Tests, Test Value

### Community 85 - "Naming and Structure"
Cohesion: 0.33
Nodes (5): File Layout, Grouping, Naming and Structure, Naming Tests, Test Class and Methods

### Community 86 - "Reviewing Tests"
Cohesion: 0.33
Nodes (5): Assertions, Coverage, Names and Structure, Reviewing Tests, Test Value

### Community 87 - "TestCase"
Cohesion: 0.47
Nodes (3): ExampleTest, TestCase, Illuminate\Foundation\Testing\TestCase

### Community 88 - "Testing Best Practices"
Cohesion: 0.40
Nodes (5): Consistency First, How to Apply, Rule Index, Testing Best Practices, What to Test

## Knowledge Gaps
- **505 isolated node(s):** `php`, `$schema`, `name`, `type`, `description` (+500 more)
  These have ≤1 connection - possible missing edges or undocumented components. (Counts symbols only; 575 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **23 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `User` connect `Brand` to `.update`, `Convention and Style Best Practices`?**
  _High betweenness centrality (0.119) - this node is a cross-community bridge._
- **Are the 2 inferred relationships involving `User` (e.g. with `Follow Project Naming Conventions` and `Follow Project Naming Conventions`) actually correct?**
  _`User` has 2 INFERRED edges - model-reasoned connections that need verification._
- **Are the 6 inferred relationships involving `TestCase` (e.g. with `Test Class and Methods` and `Global Fakes`) actually correct?**
  _`TestCase` has 6 INFERRED edges - model-reasoned connections that need verification._
- **What connects `php`, `$schema`, `name` to the rest of the system?**
  _505 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `.update` be split into smaller, more focused modules?**
  _Cohesion score 0.08282828282828283 - nodes in this community are weakly interconnected._
- **Should `composer.json` be split into smaller, more focused modules?**
  _Cohesion score 0.04081632653061224 - nodes in this community are weakly interconnected._
- **Should `package.json` be split into smaller, more focused modules?**
  _Cohesion score 0.09956709956709957 - nodes in this community are weakly interconnected._