# Jetpack CRM MCP Server

Version: **0.8.0** · Server name: **`jetpack-crm-mcp`** · Endpoint:
**`/wp-json/jpcrm-improved/v1/mcp`**

The plugin embeds a **Model Context Protocol (MCP)** server so that AI agents
(Claude Desktop, Cursor, opencode, Hermes Agent and custom clients) can work with
Jetpack CRM through native tools, resources and prompts.

The MCP server does not duplicate any CRM logic. Every tool dispatches internally
through the plugin's own REST controllers using `rest_do_request()`, so
authentication, authorization, validation and response shapes are identical to the
[REST API](REST-API.md) — with no network hop and no second source of truth.

```
AI agent ──(MCP / Streamable HTTP, Basic Application Password)──►  WordPress
                                                                     │
                            REST controllers  ──►  DAL3  ──►  wp_zbs_* tables
```

---

## Table of contents

- [Requirements and enabling the server](#requirements-and-enabling-the-server)
- [Endpoint and transport](#endpoint-and-transport)
- [Authentication](#authentication)
- [Protocol and capabilities](#protocol-and-capabilities)
- [JSON-RPC methods](#json-rpc-methods)
- [Notifications and batches](#notifications-and-batches)
- [JSON-RPC error codes](#json-rpc-error-codes)
- [Tool result format](#tool-result-format)
- [Tool catalog](#tool-catalog)
  - [System tools](#system-tools)
  - [CRUD tools](#crud-tools)
  - [Email tools](#email-tools)
  - [Resource tools](#resource-tools)
  - [Action tools](#action-tools)
- [Resources](#resources)
- [Prompts](#prompts)
- [Entity registry](#entity-registry)
- [Safety modes](#safety-modes)
- [Client configuration](#client-configuration)
- [Example session](#example-session)
- [Extending the MCP server](#extending-the-mcp-server)
- [Troubleshooting](#troubleshooting)

---

## Requirements and enabling the server

- WordPress 6.0+, PHP 7.4+.
- Jetpack CRM active.
- The plugin's **MCP server** option enabled in
  **Jetpack CRM → REST API / MCP**.

When the option is disabled the `/mcp` route is not registered at all.

The settings page exposes the following options:

| Option name | Default | Effect |
|---|---|---|
| `jpcrm_improved_mcp_enabled` | `false` | Registers the MCP endpoint. |
| `jpcrm_improved_mcp_raw_enabled` | `false` | Exposes the powerful `crm_raw` tool. |
| `jpcrm_improved_mcp_readonly` | `false` | Read-only mode: write tools are hidden and blocked. |
| `jpcrm_improved_mcp_confirm` | `false` | Requires `confirm=true` for destructive tools. |
| `jpcrm_improved_mcp_logging` | `false` | Logs MCP calls (method and tool only, no secrets) to `debug.log` when `WP_DEBUG` is on. |

`SettingsPage::endpoint_url()` returns the exact endpoint for the current site.

---

## Endpoint and transport

```
POST https://<your-site>/wp-json/jpcrm-improved/v1/mcp
```

- **Transport:** Streamable HTTP. The endpoint accepts `POST` only; `GET` returns
  `405 jpcrm_rest_method_not_allowed` with the message
  `The MCP endpoint accepts POST requests only.`
- **Body:** a single JSON-RPC 2.0 request object, or a JSON-RPC batch array.
- **Response:** HTTP `200` with the JSON-RPC envelope. Notifications (messages
  without an `id`) return HTTP `202` with an empty body.
- **No server-initiated streams.** There is no SSE stream, no session identifier
  header and no subscription support.

---

## Authentication

The MCP endpoint is a normal WordPress REST route and uses standard WordPress
authentication:

```
Authorization: Basic base64(login:application_password)
```

Create an Application Password in the WordPress user profile for the account the
agent should act as. The plugin also enables Application Passwords automatically
when `WP_ENVIRONMENT_TYPE=local`.

Authorization happens twice:

1. **Endpoint level** — the caller must be logged in and have
   `admin_zerobs_usr`.
2. **Tool level** — each tool declares a capability or a required action, and each
   handler re-checks the specific entity capability before touching the CRM.

A missing login returns `401 jpcrm_rest_unauthorized`; insufficient capability
returns `403 jpcrm_rest_forbidden` (at the HTTP layer) or a tool error with
`isError: true` (at the tool layer).

---

## Protocol and capabilities

The server identifies itself as `jetpack-crm-mcp` and reports its version from
`JPCRM_IMPROVED_VERSION`.

Supported protocol versions: `2025-03-26`, `2025-06-18` (default) and
`2026-07-28`. The version requested by the client is echoed back when supported;
otherwise the server responds with `2025-06-18`.

`initialize` result:

```json
{
  "protocolVersion": "2025-06-18",
  "capabilities": {
    "tools":     { "listChanged": false },
    "resources": { "subscribe": false, "listChanged": false },
    "prompts":   { "listChanged": false }
  },
  "serverInfo": {
    "name": "jetpack-crm-mcp",
    "version": "0.8.0"
  },
  "instructions": "..."
}
```

The server also returns a short set of usage instructions that tell the agent to
call `crm_entities` for discovery and `crm_search`/`crm_get` for reading.

---

## JSON-RPC methods

| Method | Purpose |
|---|---|
| `initialize` | Handshake: protocol version, capabilities, server info, instructions. |
| `ping` | Liveness check. Returns an empty object. |
| `tools/list` | List tools available to the current user. |
| `tools/call` | Execute a tool by name with arguments. |
| `resources/list` | List static resources. |
| `resources/templates/list` | List resource URI templates. |
| `resources/read` | Read a resource by URI. |
| `prompts/list` | List prompts. |
| `prompts/get` | Build a prompt by name and arguments. |

Unknown methods return JSON-RPC error `-32601` (`Method not found: <method>`).

**Not implemented:** `completion/complete`, `logging/setLevel`,
`resources/subscribe`, `resources/unsubscribe`, `sampling/*`, `roots/list`.

---

## Notifications and batches

- Any message without an `id` is treated as a notification, ignored, and answered
  with HTTP `202`. This includes `notifications/initialized` and other standard
  notifications.
- Batch arrays are supported. Each entry is processed independently; responses are
  returned as an array (entry order is preserved for entries that produce a
  response). A batch that contains only notifications returns `202`.
- Empty bodies and invalid JSON return JSON-RPC parse errors (`-32700`).

---

## JSON-RPC error codes

| Code | Name | Meaning |
|---|---|---|
| `-32700` | Parse error | Empty body or invalid JSON. |
| `-32600` | Invalid Request | Not a valid JSON-RPC request object. |
| `-32601` | Method not found | Unknown method. |
| `-32602` | Invalid params | Unknown tool or prompt, missing required argument. |
| `-32603` | Internal error | A resource read failed unexpectedly. |
| `-32002` | Resource not found | Unknown resource URI. |

Tool execution failures are **not** JSON-RPC errors. They are returned as a
successful `tools/call` result whose content has `isError: true` (see below).

---

## Tool result format

Successful tool call:

```json
{
  "content": [ { "type": "text", "text": "<JSON string>" } ],
  "structuredContent": { "...": "..." },
  "isError": false
}
```

Failed tool call:

```json
{
  "content": [ { "type": "text", "text": "Error message (jpcrm_error_code)" } ],
  "isError": true
}
```

The `text` content is the JSON encoding of the data (or the error message).
`structuredContent` carries the same data in a machine-friendly form.

---

## Tool catalog

The plugin registers **21 built-in tools**. Tool availability depends on the
current user's capabilities and on the safety settings.

For each tool below, "Gate" shows how availability is decided:
**capability** = a specific CRM capability; **requires_any** = available if the
user can perform that action on at least one entity.

### System tools

#### `crm_status`

Check API availability and versions.

- **Arguments:** none.
- **Gate:** capability `status:read`.
- **Maps to:** `GET /status`.
- **Returns:** availability status, CRM version, API version and plugin version.

#### `crm_me`

Return the current WordPress user and their CRM permissions.

- **Arguments:** none.
- **Gate:** capability `status:read`.
- **Maps to:** `GET /me`.

#### `crm_entities`

Discovery catalog of CRM entities: operations, fields and filters. Without
arguments it lists all entities; with `entity` it returns one detailed spec.

**Schema:**

```json
{
  "type": "object",
  "properties": {
    "entity": { "type": "string", "description": "Entity name, for example contacts or invoices." }
  },
  "additionalProperties": false
}
```

- **Gate:** capability `status:read`.
- Unknown entity → error `jpcrm_mcp_unknown_entity` (404).

---

### CRUD tools

The generic CRUD facade works for every entity in the
[entity registry](#entity-registry). The `entity` argument is always required.

#### `crm_search`

Search or list CRM objects. Defaults to a concise representation.

```json
{
  "type": "object",
  "required": ["entity"],
  "properties": {
    "entity":          { "type": "string", "description": "Entity name, for example contacts." },
    "query":           { "type": "string", "description": "Search phrase." },
    "filters":         { "type": "object", "description": "Entity filters, for example {\"status\":\"Lead\",\"company\":7}." },
    "orderby":         { "type": "string" },
    "order":           { "type": "string", "enum": ["asc", "desc"] },
    "page":            { "type": "integer", "minimum": 1 },
    "per_page":        { "type": "integer" },
    "fields":          { "type": "array", "items": { "type": "string" } },
    "response_format": { "type": "string", "enum": ["concise", "detailed"] }
  },
  "additionalProperties": false
}
```

- **Gate:** `requires_any: read`.
- **Maps to:** `GET {entity.path}`.
- **Returns:** `{ "total": <int>, "page": <int>, "per_page": <int>, "items": [ ... ] }`.
  `total` comes from the `X-WP-Total` header.
- `filters` are only forwarded when the key is declared for that entity (see
  `crm_entities`).
- `fields` selects top-level fields and overrides `response_format`.
- Default representation is **concise** (`compact_fields`).

#### `crm_get`

Read one CRM object by ID. Defaults to the full object.

```json
{
  "type": "object",
  "required": ["entity", "id"],
  "properties": {
    "entity":          { "type": "string" },
    "id":              { "type": "integer" },
    "fields":          { "type": "array", "items": { "type": "string" } },
    "response_format": { "type": "string", "enum": ["concise", "detailed"] }
  },
  "additionalProperties": false
}
```

- **Gate:** `requires_any: read`.
- **Maps to:** `GET {entity.path}/{id}`.
- Default representation is **detailed**.

#### `crm_create`

Create a CRM object.

```json
{
  "type": "object",
  "required": ["entity", "data"],
  "properties": {
    "entity": { "type": "string" },
    "data":   { "type": "object", "description": "Object fields in REST format (see crm_entities)." }
  },
  "additionalProperties": false
}
```

- **Gate:** `requires_any: write`.
- **Maps to:** `POST {entity.path}` (`201` internally).
- Returns the created object (detailed by default).

#### `crm_update`

Update a CRM object. Partial by default.

```json
{
  "type": "object",
  "required": ["entity", "id", "data"],
  "properties": {
    "entity":  { "type": "string" },
    "id":      { "type": "integer" },
    "data":    { "type": "object", "description": "Fields to update." },
    "partial": { "type": "boolean", "description": "true = PATCH (partial), false = PUT (full)." }
  },
  "additionalProperties": false
}
```

- **Gate:** `requires_any: write`.
- **Maps to:** `PATCH` when `partial` is true (default), otherwise `PUT`.

#### `crm_delete`

Delete a CRM object by ID.

```json
{
  "type": "object",
  "required": ["entity", "id"],
  "properties": {
    "entity":  { "type": "string" },
    "id":      { "type": "integer" },
    "confirm": { "type": "boolean", "description": "Required when confirmation mode is enabled." }
  },
  "additionalProperties": false
}
```

- **Gate:** `requires_any: delete`; marked destructive, supports `confirm`.
- **Maps to:** `DELETE {entity.path}/{id}`.

#### `crm_batch`

Run several write operations in one call.

```json
{
  "type": "object",
  "required": ["requests"],
  "properties": {
    "requests": {
      "type": "array",
      "items": {
        "type": "object",
        "required": ["entity", "operation"],
        "properties": {
          "entity":    { "type": "string" },
          "operation": { "type": "string", "enum": ["create", "update", "delete"] },
          "id":        { "type": "integer" },
          "data":      { "type": "object" }
        }
      }
    },
    "stop_on_error": { "type": "boolean" },
    "confirm":       { "type": "boolean", "description": "Required when confirmation mode is enabled." }
  },
  "additionalProperties": false
}
```

- **Gate:** `requires_any: write`; destructive; supports `confirm`.
- Operations run sequentially. `stop_on_error` defaults to `true`.
- Maximum number of operations: `25`, filterable with
  `jpcrm_improved_mcp_batch_max_requests`.
- **Returns:** `{ "results": [ { "index": 0, "operation": "create", "ok": true, "result": { ... } }, ... ] }`
  (failures contain `error` and `code` instead of `result`).

#### `crm_raw`

Directly call any route in the plugin namespace. Intended for debugging and
features not yet covered by a dedicated tool. Disabled by default.

```json
{
  "type": "object",
  "required": ["method", "path"],
  "properties": {
    "method":  { "type": "string", "enum": ["GET", "POST", "PUT", "PATCH", "DELETE"] },
    "path":    { "type": "string", "description": "Path relative to /jpcrm-improved/v1, for example /contacts." },
    "query":   { "type": "object" },
    "body":    { "type": "object" },
    "confirm": { "type": "boolean", "description": "Required when confirmation mode is enabled." }
  },
  "additionalProperties": false
}
```

- **Gate:** option `jpcrm_improved_mcp_raw_enabled`, capability `status:read`,
  destructive, supports `confirm`.
- Rejects paths not beginning with `/`, paths beginning with `/mcp`, and paths
  containing `..`.

---

### Email tools

All email tools require the `admin_zerobs_sendemails_contacts` capability
(`emails:write`).

#### `emails_send`

Send an email to a contact.

```json
{
  "type": "object",
  "required": ["subject", "content"],
  "properties": {
    "contact_id":      { "type": "integer" },
    "email":           { "type": "string" },
    "subject":         { "type": "string" },
    "content":         { "type": "string", "description": "HTML body." },
    "delivery_method": { "type": "string" }
  },
  "additionalProperties": false
}
```

- Requires `content` and either `contact_id` or `email` identifying an existing
  contact.
- **Maps to:** `POST /emails`.

#### `email_threads_reply`

Reply inside an existing thread.

- **Required:** `id`, `content`. Optional: `subject`, `delivery_method`.
- **Maps to:** `POST /email-threads/{id}/reply`.

#### `email_threads_star`

Star or unstar a thread.

- **Required:** `id`. Optional: `starred` (`true` to star — default — `false` to
  unstar).
- **Maps to:** `POST /email-threads/{id}/star` or `DELETE /email-threads/{id}/star`.

#### `email_threads_read`

Mark a thread as read.

- **Required:** `id`.
- **Maps to:** `POST /email-threads/{id}/read`.

---

### Resource tools

Resource tools operate on the sub-resources of a CRM object. They are declared as
read-only for listing purposes, but their write actions are blocked when the server
runs in read-only mode. Available for the entities that expose sub-resources:
`contacts`, `companies`, `invoices`, `quotes`, `transactions`, `tasks`, `forms`,
`segments`, `quote-templates`, `logs`.

#### `crm_tags`

Manage the tags of an object.

```json
{
  "type": "object",
  "required": ["entity", "id"],
  "properties": {
    "entity": { "type": "string" },
    "id":     { "type": "integer" },
    "action": { "type": "string", "enum": ["get", "set", "remove"] },
    "tags":   { "type": "array", "items": { "type": "string" } },
    "tag_id": { "type": "integer" },
    "mode":   { "type": "string", "enum": ["replace", "append", "remove"] }
  },
  "additionalProperties": false
}
```

- `get` → `GET /{resource}/{id}/tags`
- `set` → `POST /{resource}/{id}/tags` with `{ tags, mode }`
- `remove` → `DELETE /{resource}/{id}/tags/{tag_id}`

#### `crm_meta`

Manage arbitrary key/value meta on an object.

```json
{
  "type": "object",
  "required": ["entity", "id"],
  "properties": {
    "entity": { "type": "string" },
    "id":     { "type": "integer" },
    "action": { "type": "string", "enum": ["get", "set", "delete"] },
    "key":    { "type": "string" },
    "value":  { "description": "Any JSON value." }
  },
  "additionalProperties": false
}
```

- `get` (with `key`) → `GET /{resource}/{id}/meta/{key}`
- `get` (without `key`) → `GET /{resource}/{id}/meta`
- `set` → `PUT /{resource}/{id}/meta/{key}` with `{ value }`
- `delete` → `DELETE /{resource}/{id}/meta/{key}`

#### `crm_custom_fields`

Read or write custom field values.

```json
{
  "type": "object",
  "required": ["entity", "id"],
  "properties": {
    "entity": { "type": "string" },
    "id":     { "type": "integer" },
    "action": { "type": "string", "enum": ["get", "set"] },
    "key":    { "type": "string" },
    "value":  { "description": "A single field value." },
    "values": { "type": "object", "description": "A map of { key: value }." }
  },
  "additionalProperties": false
}
```

- `set` with `values` → `PUT /{resource}/{id}/custom-fields` with `{ values }`
- `set` with `key` → `PUT /{resource}/{id}/custom-fields/{key}` with `{ value }`

#### `crm_external_sources`

Manage external source records.

```json
{
  "type": "object",
  "required": ["entity", "id"],
  "properties": {
    "entity": { "type": "string" },
    "id":     { "type": "integer" },
    "action": { "type": "string", "enum": ["get", "add", "delete"] },
    "source": { "type": "string" },
    "uid":    { "type": "string" },
    "origin": { "type": "string" },
    "ext_id": { "type": "integer" }
  },
  "additionalProperties": false
}
```

- `add` → `POST /{resource}/{id}/external-sources` with `{ source, uid, origin? }`
- `delete` → `DELETE /{resource}/{id}/external-sources/{ext_id}`

#### `crm_links`

Manage object links.

```json
{
  "type": "object",
  "required": ["entity", "id"],
  "properties": {
    "entity":      { "type": "string" },
    "id":          { "type": "integer" },
    "action":      { "type": "string", "enum": ["get", "add", "delete"] },
    "object_type": { "type": ["string", "integer"], "description": "Entity name (for example companies) or numeric CRM type." },
    "object_id":   { "type": "integer" }
  },
  "additionalProperties": false
}
```

- `add` → `POST /{resource}/{id}/links` with `{ object_type, object_id }`
- `delete` → `DELETE /{resource}/{id}/links/{type}/{object_id}`

Entity names are mapped to CRM types: contacts `1`, companies `2`, quotes `3`,
invoices `4`, transactions `5`, tasks `6`, forms `7`, logs `8`, segments `9`,
quote templates `12`.

---

### Action tools

#### `quotes_accept`

Accept a quote, or revoke acceptance.

```json
{
  "type": "object",
  "required": ["id"],
  "properties": {
    "id":        { "type": "integer" },
    "signed_by": { "type": "string" },
    "accept":    { "type": "boolean", "description": "true to accept (default), false to revoke." }
  },
  "additionalProperties": false
}
```

- **Gate:** capability `quotes:write`.
- `accept` (default) → `POST /quotes/{id}/accept` with `{ signed_by }`
- revoke → `DELETE /quotes/{id}/accept`

#### `segments_compile`

Recompile a segment and get the number of matches.

```json
{
  "type": "object",
  "required": ["id"],
  "properties": {
    "id": { "type": "integer" }
  },
  "additionalProperties": false
}
```

- **Gate:** capability `segments:write`.
- **Maps to:** `POST /segments/{id}/compile`.

---

## Resources

Static resources are available via `resources/list` and `resources/read`:

| URI | Description | MIME type |
|---|---|---|
| `jpcrm://entities` | Catalog of entities, operations, fields and filters. | `application/json` |
| `jpcrm://me` | Current WordPress user and CRM permissions. | `application/json` |
| `jpcrm://status` | API availability and versions. | `application/json` |
| `jpcrm://openapi` | OpenAPI 3 document for the REST API. | `application/json` |

Resource templates (`resources/templates/list`):

| URI template | Description |
|---|---|
| `jpcrm://{entity}/{id}` | Read a CRM object by ID (for example `jpcrm://contacts/12`). |

A resource read returns:

```json
{
  "contents": [
    { "uri": "jpcrm://contacts/12", "mimeType": "application/json", "text": "{...}" }
  ]
}
```

The URI `jpcrm://entities/{entity}` is also accepted and returns a single entity
spec, although it is not advertised as a template.

---

## Prompts

Prompts are available via `prompts/list` and `prompts/get`.

| Prompt | Arguments | Purpose |
|---|---|---|
| `summarize_contact` | `id` (required) | Summarize a contact: details, deals, invoices and recent activity. |
| `contact_timeline` | `id` (required) | Build a chronological activity timeline for a contact. |
| `pipeline_review` | none | Review quotes and invoices by status and suggest next actions. |
| `draft_email` | `id` (required), `goal` (optional) | Draft an email to a contact for a given goal. |

`prompts/get` returns a single `user`-role text message. Missing required
arguments return `-32602`; unknown prompt names return `-32602`.

---

## Entity registry

The entity registry powers `crm_entities`, discovery for the generic CRUD tools and
the OpenAPI generation. It contains **16 entities**. Availability is filtered by the
current user's capabilities; entities with no allowed operations are hidden and
`operations` is trimmed to what the user may perform.

Legend: R = list, G = get by ID, C = create, U = update, D = delete.

| `entity` | REST path | R | G | C | U | D | Compact fields |
|---|---|---|---|---|---|---|---|
| `contacts` | `/contacts` | ✓ | ✓ | ✓ | ✓ | ✓ | id, first_name, last_name, email, status |
| `companies` | `/companies` | ✓ | ✓ | ✓ | ✓ | ✓ | id, name, status, email |
| `invoices` | `/invoices` | ✓ | ✓ | ✓ | ✓ | ✓ | id, number, status, total, date |
| `quotes` | `/quotes` | ✓ | ✓ | ✓ | ✓ | ✓ | id, title, status, value, date |
| `transactions` | `/transactions` | ✓ | ✓ | ✓ | ✓ | ✓ | id, title, status, total, date |
| `tasks` | `/tasks` | ✓ | ✓ | ✓ | ✓ | ✓ | id, title, start, complete |
| `task-reminders` | `/task-reminders` | ✓ | ✓ | ✓ | ✓ | ✓ | id, event, remind_at, sent |
| `logs` | `/logs` | ✓ | ✓ | ✓ | ✓ | ✓ | id, object_type, object_id, type, short_description, date_created_gmt |
| `forms` | `/forms` | ✓ | ✓ | ✓ | ✓ | ✓ | id, title |
| `segments` | `/segments` | ✓ | ✓ | ✓ | ✓ | ✓ | id, name, slug |
| `quote-templates` | `/quote-templates` | ✓ | ✓ | ✓ | ✓ | ✓ | id, title |
| `line-items` | `/line-items` | ✓ | ✓ | ✓ | ✓ | ✓ | id, title, quantity, price, total |
| `tags` | `/tags` | ✓ | ✓ | ✓ | ✓ | ✓ | id, name, slug, object_type |
| `emails` | `/emails` | ✓ | ✓ | ✓ | — | ✓ | id, subject, status, contact_id, date_created_gmt |
| `email-threads` | `/email-threads` | ✓ | ✓ | — | — | ✓ | id, thread_id, contact_id, subject, status, date_created_gmt |
| `email-templates` | `/email-templates` | ✓ | ✓ | ✓ | ✓ | ✓ | id, active, subject |

**Filters per entity:**

| Entity | Filters |
|---|---|
| contacts | search, status, owner, company, tags, has_email |
| companies | search, status, owner, tags |
| invoices | search, status, contact, company, date_after, date_before |
| quotes | search, status, contact, company |
| transactions | search, status, type, contact, company, invoice |
| tasks | search, owner, contact, company |
| task-reminders | event |
| logs | object_type, object_id, type, pinned, owner |
| forms | search |
| segments | search |
| quote-templates | search |
| line-items | search, parent_object_type, parent_object_id |
| tags | object_type, search |
| emails | contact, status, starred, thread, type, search, date_after, date_before |
| email-threads | contact, status, starred, search |
| email-templates | search |

**Required on create:** contacts → `email`; companies → `name`; quotes → `title`;
tasks → `title`; forms → `title`; segments → `name`; quote-templates → `title`;
task-reminders → `event`, `remind_at`; logs → `object_type`, `object_id`;
line-items → `parent_object_type`, `parent_object_id`, `title`; tags → `name`;
emails → `subject`, `content`; email-templates → `subject`.

> Some filters advertised here are interpreted by the MCP layer and forwarded to
> the REST API; if the underlying controller does not implement a filter, it is
> ignored. `crm_entities` is the authoritative catalog for the current deployment.

---

## Safety modes

### Read-only mode

When `jpcrm_improved_mcp_readonly` is enabled:

- Write tools are removed from `tools/list`.
- Any attempt to call a write tool returns `isError: true` with the message
  `MCP server is running in read-only mode.`
- Resource tools remain listed (they advertise read-only), but their write actions
  return `403 jpcrm_mcp_readonly`. Read actions still work.

### Confirmation mode

When `jpcrm_improved_mcp_confirm` is enabled, destructive tools (`crm_delete`,
`crm_batch`, `crm_raw`) require `confirm: true`. Without it the call returns
`isError: true` and a message asking the agent to repeat the call with
`confirm=true`.

### `crm_raw`

Disabled unless the `jpcrm_improved_mcp_raw_enabled` option is on. It only accepts
paths inside the `jpcrm-improved/v1` namespace and rejects `/mcp` and traversal
(`..`) paths.

### Logging

When `jpcrm_improved_mcp_logging` and `WP_DEBUG` are enabled, the server writes the
JSON-RPC method and tool name to the WordPress debug log. No arguments, credentials
or payloads are logged.

---

## Client configuration

Any MCP client that supports remote Streamable HTTP and custom headers can connect.
Use the endpoint URL and an `Authorization: Basic` header.

### opencode

```json
{
  "mcp": {
    "jetpack-crm": {
      "type": "remote",
      "url": "https://example.com/wp-json/jpcrm-improved/v1/mcp",
      "headers": {
        "Authorization": "Basic <base64(login:application_password)>"
      }
    }
  }
}
```

### Claude Desktop / Cursor / generic clients

Use the same endpoint and header. Some clients require an `mcp-remote` bridge for
remote HTTP servers:

```json
{
  "mcpServers": {
    "jetpack-crm": {
      "command": "npx",
      "args": [
        "mcp-remote",
        "https://example.com/wp-json/jpcrm-improved/v1/mcp",
        "--header",
        "Authorization: Basic <base64(login:application_password)>"
      ]
    }
  }
}
```

> The plugin does not support an interactive OAuth flow. Use a WordPress
> Application Password and, ideally, a dedicated user with the minimum required
> CRM capabilities.

---

## Example session

`initialize`:

```json
{ "jsonrpc": "2.0", "id": 1, "method": "initialize", "params": { "protocolVersion": "2025-06-18" } }
```

`tools/list` (abbreviated):

```json
{ "jsonrpc": "2.0", "id": 2, "method": "tools/list" }
```

Find a contact:

```json
{
  "jsonrpc": "2.0",
  "id": 3,
  "method": "tools/call",
  "params": {
    "name": "crm_search",
    "arguments": { "entity": "contacts", "query": "jane@example.com" }
  }
}
```

Read the contact's invoices:

```json
{
  "jsonrpc": "2.0",
  "id": 4,
  "method": "tools/call",
  "params": {
    "name": "crm_search",
    "arguments": {
      "entity": "invoices",
      "filters": { "contact": 123 },
      "response_format": "detailed"
    }
  }
}
```

Create a lead:

```json
{
  "jsonrpc": "2.0",
  "id": 5,
  "method": "tools/call",
  "params": {
    "name": "crm_create",
    "arguments": {
      "entity": "contacts",
      "data": { "email": "lead@example.com", "first_name": "Ann", "status": "Lead" }
    }
  }
}
```

---

## Extending the MCP server

Register additional tools with the `jpcrm_improved_mcp_register_tools` action. The
action receives the `ToolRegistry`; call `->add()` with a tool definition.

```php
add_action( 'jpcrm_improved_mcp_register_tools', function ( $registry ) {
    $registry->add( array(
        'name'        => 'contacts_count',
        'description' => 'Return the total number of contacts.',
        'inputSchema' => array(
            'type'                 => 'object',
            'properties'           => (object) array(),
            'additionalProperties' => false,
        ),
        'capability'  => array( 'contacts', 'read' ),
        'annotations' => array(
            'readOnlyHint'    => true,
            'destructiveHint' => false,
            'idempotentHint'  => true,
            'openWorldHint'   => false,
        ),
        'handler'     => function () {
            $response = rest_do_request( new WP_REST_Request( 'GET', '/jpcrm-improved/v1/contacts' ) );
            return array( 'total' => (int) $response->get_headers()['X-WP-Total'] );
        },
    ) );
} );
```

A tool definition supports the following keys:

| Key | Description |
|---|---|
| `name` | Unique tool name. |
| `description` | Human-readable description shown to the agent. |
| `inputSchema` | JSON schema for `arguments`. |
| `handler` | Callable invoked with the arguments. Return an array (success) or `WP_Error` (error). |
| `capability` | `[ resource, action ]` used for a static permission check. |
| `requires_any` | `read`, `write` or `delete`; available if the user can do it on any entity. |
| `confirm` | If `true`, the tool requires `confirm=true` in confirmation mode. |
| `annotations` | MCP annotations (`readOnlyHint`, `destructiveHint`, `idempotentHint`, `openWorldHint`). |
| `setting` | WordPress option name; the tool is hidden unless the option is enabled. |

If you add a new REST resource, update the entity registry so the generic CRUD
tools cover it automatically.

---

## Troubleshooting

| Symptom | Likely cause | Fix |
|---|---|---|
| `401 jpcrm_rest_unauthorized` | Missing or invalid `Authorization` header. | Send a valid Application Password. |
| `403 jpcrm_rest_forbidden` at the endpoint | The user lacks `admin_zerobs_usr`. | Grant the CRM capability or use another user. |
| Tools missing from `tools/list` | The user lacks the relevant capability, or read-only mode hides write tools. | Check `crm_me` and the MCP settings. |
| `405` on `/mcp` | Used `GET`. | Use `POST`. |
| `isError: true` "read-only" | Read-only mode is enabled. | Disable read-only mode if writes are intended. |
| `isError: true` asking for `confirm` | Confirmation mode is enabled. | Repeat the call with `confirm: true`. |
| `crm_raw` unavailable | The raw option is disabled. | Enable `jpcrm_improved_mcp_raw_enabled`. |
| Empty `202` response | The message had no `id` (treated as a notification). | Add an `id` to request a response. |

For protocol-level details, see the [MCP specification](https://modelcontextprotocol.io).
For CRM data fields and filters, call `crm_entities` or read the
[REST API reference](REST-API.md).
