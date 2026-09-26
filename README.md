# Jetpack CRM REST API Improved

A complete, standards-compliant WordPress REST API for **Jetpack CRM** (a.k.a.
`zero-bs-crm`), plus an embedded **MCP (Model Context Protocol) server** that lets
AI agents work with your CRM through native tools.

The plugin replaces the incomplete and non-standard Jetpack CRM API with a single,
consistent REST layer that covers every major CRM entity, uses real WordPress
authentication, returns correct HTTP status codes, and ships with auto-generated
OpenAPI documentation and an MCP endpoint.

- **REST namespace:** `jpcrm-improved/v1`
- **Base URL:** `https://<your-site>/wp-json/jpcrm-improved/v1`
- **MCP endpoint:** `https://<your-site>/wp-json/jpcrm-improved/v1/mcp`
- **License:** GPL-2.0-or-later

---

## Table of contents

- [Why this plugin exists](#why-this-plugin-exists)
- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Authentication](#authentication)
- [Quick start](#quick-start)
- [REST API overview](#rest-api-overview)
- [MCP server overview](#mcp-server-overview)
- [Architecture](#architecture)
- [Extending the plugin](#extending-the-plugin)
- [Security notes](#security-notes)
- [Development and testing](#development-and-testing)
- [Documentation](#documentation)
- [Changelog](#changelog)
- [Credits](#credits)
- [License](#license)

---

## Why this plugin exists

The REST API bundled with Jetpack CRM has several long-standing problems that make
it unsuitable for modern integrations:

- **Custom route** (`/zbs_api/`) instead of the WordPress standard `/wp-json/`.
- **Credentials in query strings**, which leak secrets into server logs, proxies,
  browser history and referrers.
- **A single global key/secret** with no per-user scoping, roles or revocation.
- **Broken semantics:** many endpoints require a trailing slash, return `405`, or
  time out; some "read" operations use `POST`.
- **Errors returned as HTTP 200** with `{ "error": 100 }`, so clients cannot tell
  success from failure.
- **Missing operations:** no `DELETE`, no standalone `UPDATE`, no `GET` by ID for
  most entities, and no access to tasks, logs, forms, segments, tags, custom
  fields, meta, line items, templates or emails.
- **No pagination headers, schemas, validation or batch operations.**

Jetpack CRM REST API Improved implements the whole surface as a normal WordPress
REST API. See [`docs/REST-API.md`](docs/REST-API.md) for the full reference.

---

## Features

- **Full CRUD** (`GET`, `POST`, `PUT`, `PATCH`, `DELETE`) for:
  contacts, companies, invoices, quotes, transactions, tasks, task reminders,
  logs, forms, segments, quote templates, line items, tags, emails, email threads
  and email templates.
- **Standard WordPress authentication** using Application Passwords (HTTP Basic)
  or the logged-in cookie plus `X-WP-Nonce`.
- **Capability-based authorization** mapped to Jetpack CRM permissions
  (`admin_zerobs_*`).
- **Consistent responses:** JSON objects for single items, arrays for collections,
  `201 Created` on create, correct `400`/`401`/`403`/`404`/`405`/`422`/`500` errors.
- **Pagination** with `X-WP-Total` and `X-WP-TotalPages` headers.
- **Sub-resources** for every CRM entity: tags, meta, custom fields, external
  sources and object links.
- **Batch endpoint** for running multiple operations in one request.
- **Auto-generated OpenAPI 3.0.3** document at `/openapi.json`.
- **Embedded MCP server** (Streamable HTTP) with generic CRUD tools, discovery,
  resources, prompts and safety modes.
- **Extensible** through WordPress filters and actions.

---

## Requirements

| Requirement | Version |
|---|---|
| WordPress | 6.0 or newer |
| PHP | 7.4 or newer |
| Jetpack CRM (`zero-bs-crm`) | Active and configured |

The plugin activates only when Jetpack CRM is active; otherwise the REST routes are
simply not registered.

---

## Installation

### From GitHub (recommended)

1. Download the repository as a ZIP archive and unpack it, or clone it:

   ```bash
   git clone https://github.com/ivannikitin-com/jetpack-crm-rest-api-improved.git
   ```

2. Copy the `jetpack-crm-rest-api-improved` folder into
   `wp-content/plugins/`.

3. Activate **Jetpack CRM REST API Improved** in **Plugins → Installed Plugins**.
   Make sure **Jetpack CRM** is installed and active.

The plugin ships with a PSR-4 fallback autoloader, so it works without running
Composer. If you want the optimized Composer autoloader or the development tools,
run:

```bash
composer install --no-dev    # production
composer install             # development (PHPUnit, PHPCS, WPCS)
```

### Via Composer

```bash
composer require ivannikitin/jetpack-crm-rest-api-improved
```

### Updating

Replace the plugin folder with the new version and keep your existing settings.
Plugin options are stored as WordPress options and are preserved across updates.

---

## Configuration

Open **Jetpack CRM → REST API / MCP** in the WordPress admin. The page is available
to users with the `admin_zerobs_manage_options` capability.

| Setting | Option name | Default | Description |
|---|---|---|---|
| MCP server | `jpcrm_improved_mcp_enabled` | `false` | Registers the `/mcp` endpoint. When off, only the REST API is exposed. |
| `crm_raw` | `jpcrm_improved_mcp_raw_enabled` | `false` | Exposes the `crm_raw` tool, which can call any REST route in the namespace. Intended for debugging; not recommended in production. |
| Read-only mode | `jpcrm_improved_mcp_readonly` | `false` | Hides and blocks all write tools. The agent can still read and search. |
| Confirmation | `jpcrm_improved_mcp_confirm` | `false` | Requires an explicit `confirm=true` argument for `crm_delete`, `crm_batch` and `crm_raw`. |
| Logging | `jpcrm_improved_mcp_logging` | `false` | Logs MCP method and tool names (never secrets) to `debug.log` when `WP_DEBUG` is enabled. |

The settings page also displays the exact MCP endpoint URL and the expected
`Authorization: Basic ...` header.

---

## Authentication

The plugin does not invent its own authentication. It uses the standard WordPress
mechanisms, so any client that can authenticate against WordPress can use the API.

### Application Passwords (recommended)

1. Edit your WordPress user profile and scroll to **Application Passwords**.
2. Enter a name (for example, `crm-integration`) and click **Add New Application
   Password**.
3. Copy the generated password and send it as HTTP Basic auth using the format
   `base64(login:application_password)`:

   ```bash
   curl -u "admin:xxxx xxxx xxxx xxxx xxxx xxxx" \
     https://example.com/wp-json/jpcrm-improved/v1/status
   ```

Application Passwords require HTTPS. For local development over HTTP the plugin
automatically enables them when WordPress runs with
`WP_ENVIRONMENT_TYPE=local`.

### Cookie authentication

Requests made from a logged-in browser session must include a valid REST nonce in
the `X-WP-Nonce` header.

### Permissions

Each operation checks a Jetpack CRM capability for the current user. The mapping is
documented in [`docs/REST-API.md`](docs/REST-API.md#permissions). A user without
the required capability receives `403 jpcrm_rest_forbidden`.

---

## Quick start

List the five most recent contacts:

```bash
curl -u "admin:APP_PASSWORD" \
  "https://example.com/wp-json/jpcrm-improved/v1/contacts?per_page=5&orderby=id&order=desc"
```

Create a contact:

```bash
curl -u "admin:APP_PASSWORD" \
  -H "Content-Type: application/json" \
  -X POST "https://example.com/wp-json/jpcrm-improved/v1/contacts" \
  -d '{
        "email": "jane@example.com",
        "first_name": "Jane",
        "last_name": "Doe",
        "status": "Lead",
        "tags": ["web"]
      }'
```

Update a contact (partial update):

```bash
curl -u "admin:APP_PASSWORD" \
  -H "Content-Type: application/json" \
  -X PATCH "https://example.com/wp-json/jpcrm-improved/v1/contacts/123" \
  -d '{ "status": "Customer" }'
```

Delete a contact:

```bash
curl -u "admin:APP_PASSWORD" \
  -X DELETE "https://example.com/wp-json/jpcrm-improved/v1/contacts/123"
```

Inspect your own permissions:

```bash
curl -u "admin:APP_PASSWORD" \
  "https://example.com/wp-json/jpcrm-improved/v1/me"
```

Discover the API:

```bash
curl -u "admin:APP_PASSWORD" \
  "https://example.com/wp-json/jpcrm-improved/v1/openapi.json"
```

---

## REST API overview

All routes live under `/wp-json/jpcrm-improved/v1`.

| Resource | Path | Operations |
|---|---|---|
| Contacts | `/contacts` | list, get, create, update, delete |
| Companies | `/companies` | list, get, create, update, delete |
| Invoices | `/invoices` | list, get, create, update, delete |
| Quotes | `/quotes` | list, get, create, update, delete, accept |
| Transactions | `/transactions` | list, get, create, update, delete |
| Tasks | `/tasks` | list, get, create, update, delete |
| Task reminders | `/task-reminders` | list, get, create, update, delete |
| Logs | `/logs` | list, get, create, update, delete |
| Forms | `/forms` | list, get, create, update, delete |
| Segments | `/segments` | list, get, create, update, delete, compile |
| Quote templates | `/quote-templates` | list, get, create, update, delete |
| Line items | `/line-items` | list, get, create, update, delete |
| Tags | `/tags` | list, get, create, update, delete |
| Emails | `/emails` | list, get, send, delete |
| Email threads | `/email-threads` | list, get, delete, star, read, reply |
| Email templates | `/email-templates` | list, get, create, update, delete |
| System | `/status`, `/me` | read |
| Batch | `/batch` | run multiple operations |
| OpenAPI | `/openapi.json` | read |

Collections support `page`, `per_page`, `offset`, `orderby`, `order`, `search`
(alias `s`), plus entity-specific filters. Collection responses include
`X-WP-Total` and `X-WP-TotalPages` headers.

See the full [REST API reference](docs/REST-API.md) for parameters, fields,
sub-resources, error codes and examples.

---

## MCP server overview

When enabled, the plugin exposes a **Model Context Protocol** server over
Streamable HTTP. AI agents (Claude Desktop, Cursor, opencode, Hermes Agent, custom
clients) connect with their own WordPress user and Application Password and get a
native toolkit for the CRM.

- **Endpoint:** `POST /wp-json/jpcrm-improved/v1/mcp`
- **Transport:** Streamable HTTP (JSON-RPC 2.0, single requests and batches)
- **Server name:** `jetpack-crm-mcp`
- **Protocol versions:** `2025-03-26`, `2025-06-18` (default), `2026-07-28`
- **Tools:** 21 built-in tools, including a generic CRUD facade
  (`crm_search`, `crm_get`, `crm_create`, `crm_update`, `crm_delete`,
  `crm_batch`, `crm_raw`), discovery (`crm_entities`, `crm_me`, `crm_status`),
  email tools, sub-resource tools and action tools.
- **Resources:** `jpcrm://entities`, `jpcrm://me`, `jpcrm://status`,
  `jpcrm://openapi` and the template `jpcrm://{entity}/{id}`.
- **Prompts:** `summarize_contact`, `contact_timeline`, `pipeline_review`,
  `draft_email`.

Every MCP tool dispatches internally through the plugin's own REST controllers via
`rest_do_request()`, so permissions, validation and response shapes are identical
to the REST API. There is no network hop and no second source of truth.

See the full [MCP server documentation](docs/MCP-SERVER.md).

---

## Architecture

```
AI agent ──(MCP / Streamable HTTP, Basic Application Password)──►  WordPress
                                                                     │
                            REST controllers  ──►  DAL3  ──►  wp_zbs_* tables
                                     │
                              Jetpack CRM core
```

- `classes/Rest/` — REST controllers, field mapping, permissions, OpenAPI.
- `classes/Rest/Controllers/` — one controller per resource.
- `classes/Mcp/` — JSON-RPC server, HTTP endpoint, tool registry, tools.
- `classes/Admin/SettingsPage.php` — the admin settings page.
- `classes/Plugin.php` — bootstrap, hooks and CRM extension registration.

The REST layer talks to Jetpack CRM's data layer (DAL3). Emails and email
templates, which do not have DAL support in the CRM core, are handled through the
`Rest\MailAdapter`.

---

## Extending the plugin

### Filters

| Filter | Default | Purpose |
|---|---|---|
| `jpcrm_improved_rest_controllers` | controller list | Add or remove REST controllers. |
| `jpcrm_improved_rest_max_per_page` | `1000` | Maximum `per_page` value. Use `0` or less to disable the cap. |
| `jpcrm_improved_rest_batch_max_requests` | `25` | Maximum number of sub-requests in `POST /batch`. |
| `jpcrm_improved_openapi_public` | `false` | Make `/openapi.json` publicly readable. |
| `jpcrm_improved_mcp_batch_max_requests` | `25` | Maximum number of operations in the `crm_batch` tool. |
| `jpcrm_improved_mcp_raw_enabled` | option value | Programmatically gate the `crm_raw` tool. |

### Actions

| Action | Purpose |
|---|---|
| `jpcrm_improved_mcp_register_tools` | Receives the `ToolRegistry`; call `->add()` to register custom MCP tools. |

Example — make the OpenAPI document public:

```php
add_filter( 'jpcrm_improved_openapi_public', '__return_true' );
```

Example — register a custom MCP tool:

```php
add_action( 'jpcrm_improved_mcp_register_tools', function ( $registry ) {
    $registry->add( array(
        'name'        => 'my_tool',
        'description' => 'Does something useful.',
        'inputSchema' => array(
            'type'       => 'object',
            'properties' => array( 'id' => array( 'type' => 'integer' ) ),
            'required'   => array( 'id' ),
        ),
        'capability'  => array( 'contacts', 'read' ),
        'annotations' => array(
            'readOnlyHint'    => true,
            'destructiveHint' => false,
            'idempotentHint'  => true,
            'openWorldHint'   => false,
        ),
        'handler'     => 'my_tool_handler',
    ) );
} );
```

---

## Security notes

- Authentication is delegated entirely to WordPress; the plugin stores no API keys
  of its own.
- Always use HTTPS. Application Passwords are transmitted with every request and
  must not be sent over plain HTTP.
- Use a dedicated WordPress user for integrations and grant only the CRM
  capabilities it needs.
- For AI agents, consider enabling **Read-only mode** and requiring
  **confirmation** for destructive operations.
- Keep `crm_raw` disabled unless you are actively debugging.
- Never commit Application Passwords, database credentials or other secrets to
  version control. The bundled `.gitignore` excludes `.env`, logs, dependencies and
  build artifacts.

---

## Development and testing

The plugin depends on Jetpack CRM, so the recommended development setup is a
WordPress install (often Docker) with the plugin mounted at
`wp-content/plugins/jetpack-crm-rest-api-improved`.

Useful commands inside the plugin directory:

```bash
composer install                 # install development dependencies
vendor/bin/phpcs                 # WordPress Coding Standards
vendor/bin/phpunit               # run the unit/integration suite (when present)
```

Complementary end-to-end checks used during development include HTTP contract runs
(`run.ps1` against a running site) and an MCP coverage check that verifies every
registered route is reachable through an MCP tool. Those scripts live in the
development workspace outside the distributable plugin folder.

---

## Documentation

| Document | Description |
|---|---|
| [`docs/REST-API.md`](docs/REST-API.md) | Complete REST API reference: authentication, parameters, resources, fields, sub-resources, errors, batch and OpenAPI. |
| [`docs/MCP-SERVER.md`](docs/MCP-SERVER.md) | Complete MCP server reference: transport, JSON-RPC methods, tool catalog, resources, prompts, entity registry and safety modes. |

The living, machine-readable specification is always available at
`GET /wp-json/jpcrm-improved/v1/openapi.json`.

---

## Changelog

### 0.8.0

- REST API for all Jetpack CRM entities: contacts, companies, invoices, quotes,
  transactions, tasks, task reminders, logs, forms, segments, quote templates,
  line items, tags, emails, email threads and email templates.
- Sub-resource routes for tags, meta, custom fields, external sources and links.
- Batch endpoint and OpenAPI 3.0.3 generation.
- Embedded MCP server with generic CRUD, discovery, resources, prompts and safety
  modes (read-only, confirmation, `crm_raw`, logging).
- Admin settings page under **Jetpack CRM → REST API / MCP**.

---

## Credits

- Author: **Ivan Nikitin** — <https://ivannikitin.com>
- Requires: [Jetpack CRM](https://jetpackcrm.com/) (`zero-bs-crm`)

---

## License

This plugin is licensed under the **GNU General Public License v2.0 or later**
(GPL-2.0-or-later). See <https://www.gnu.org/licenses/gpl-2.0.html>.
