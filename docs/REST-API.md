# Jetpack CRM REST API Improved — REST API Reference

Version: **0.9.0** · Namespace: **`jpcrm-improved/v1`**

This document is the complete reference for the REST API implemented by the
Jetpack CRM REST API Improved plugin. It covers authentication, request and
response conventions, common query parameters, every resource and sub-resource,
error codes, batch operations, the OpenAPI document and the capability map.

> The machine-readable version of this specification is generated at runtime and
> is always in sync with the code:
> `GET https://<your-site>/wp-json/jpcrm-improved/v1/openapi.json`

---

## Table of contents

- [Base URL](#base-url)
- [Authentication](#authentication)
- [Request conventions](#request-conventions)
- [Collection parameters](#collection-parameters)
- [Pagination](#pagination)
- [Sorting](#sorting)
- [Searching](#searching)
- [Response headers](#response-headers)
- [HTTP status codes](#http-status-codes)
- [Error format and codes](#error-format-and-codes)
- [Field selection and embedding](#field-selection-and-embedding)
- [Dates, numbers and booleans](#dates-numbers-and-booleans)
- [Batch operations](#batch-operations)
- [OpenAPI document](#openapi-document)
- [Permissions](#permissions)
- [Resources](#resources)
  - [Contacts](#contacts)
  - [Companies](#companies)
  - [Invoices](#invoices)
  - [Quotes](#quotes)
  - [Transactions](#transactions)
  - [Tasks](#tasks)
  - [Task reminders](#task-reminders)
  - [Logs](#logs)
  - [Forms](#forms)
  - [Segments](#segments)
  - [Quote templates](#quote-templates)
  - [Line items](#line-items)
  - [Tags](#tags)
  - [Emails](#emails)
  - [Email threads](#email-threads)
  - [Email templates](#email-templates)
  - [System: status and me](#system-status-and-me)
- [Sub-resources](#sub-resources)
- [Hooks and filters](#hooks-and-filters)
- [Known limitations](#known-limitations)

---

## Base URL

```
https://<your-site>/wp-json/jpcrm-improved/v1
```

All paths in this document are relative to this base URL. The namespace is
registered only when Jetpack CRM (`zero-bs-crm`) is active.

---

## Authentication

The plugin relies on standard WordPress authentication:

| Method | How to send it | Typical use |
|---|---|---|
| Application Password | `Authorization: Basic base64(login:application_password)` | Server-to-server and AI agents |
| Cookie + nonce | WordPress login cookie plus the `X-WP-Nonce` header | Browser / same-origin JavaScript |

There is no plugin-specific key or token. To create an Application Password, open
your WordPress profile and use the **Application Passwords** section.

```bash
curl -u "admin:xxxx xxxx xxxx xxxx xxxx xxxx" \
  "https://example.com/wp-json/jpcrm-improved/v1/contacts"
```

Application Passwords require HTTPS. In a local environment
(`WP_ENVIRONMENT_TYPE=local`) the plugin enables them automatically over HTTP.

Every request is authorized against a Jetpack CRM capability. See
[Permissions](#permissions).

---

## Request conventions

- **JSON in, JSON out.** Send `Content-Type: application/json` for bodies with
  `POST`, `PUT` and `PATCH`.
- **Update methods.** `PATCH` performs a partial update (only the supplied fields
  change). `PUT` is accepted for a full update. Both are registered for
  update routes and behave identically in most resources.
- **Resource identifiers** are positive integers and appear as `{id}` in paths.
- **Create** returns HTTP `201 Created` with the created object. Depending on the
  resource, a `Location` header pointing to the new resource may also be returned.
- **Delete** returns HTTP `200 OK` with a body such as
  `{ "deleted": true, "previous": { ... } }`.
- Representations use **reader-friendly field names** (`first_name`, `last_name`,
  `line_items`, `date_created_gmt`) rather than raw CRM column names.

---

## Collection parameters

Every collection (`GET` on a plural resource) accepts the following parameters.

| Parameter | Type | Default | Description |
|---|---|---|---|
| `page` | integer | `1` | Page number. For most resources it is 1-based; emails and email threads use a 0-based offset (see [Pagination](#pagination)). |
| `per_page` | integer | `20` | Items per page. `-1` returns everything. Capped at `1000`. |
| `offset` | integer | `0` | Number of items to skip. Emulated when `page` is not supplied. |
| `orderby` | string | `id` | Field to sort by (REST field name where supported). |
| `order` | `asc` \| `desc` | `desc` | Sort direction. |
| `search` | string | — | Free-text search. |
| `s` | string | — | Alias for `search`. |
| `status` | string | — | Filter by status (where the entity has one). |
| `owner` | integer | — | Filter by owning WordPress user ID. |
| `owned_by` | integer | — | Alias for `owner`. |

Additional per-resource parameters are listed in the corresponding section.

The `per_page` cap can be changed with the `jpcrm_improved_rest_max_per_page`
filter (return `0` or a negative value to remove the cap).

---

## Pagination

- Most resources pass `page` and `per_page` straight to the Jetpack CRM data
  layer, which uses a 1-based page number. `per_page = -1` returns all records.
- **Emails** and **email threads** use `page` as a **0-based offset** instead
  (default `0`), matching the underlying mail history query.
- **Line items** accept a 1-based `page` and translate it internally to the
  0-based value the data layer expects.
- `offset` is always emulated: the plugin fetches `offset + per_page` items and
  slices the result.
- When `per_page = -1`, `X-WP-TotalPages` is `1` if there is at least one record
  and `0` otherwise.

Example:

```bash
curl -u "admin:APP_PASSWORD" \
  "https://example.com/wp-json/jpcrm-improved/v1/contacts?page=2&per_page=50&orderby=last_name&order=asc"
```

---

## Sorting

The default sort is `orderby=id&order=desc`. Supported `orderby` values per
resource:

| Resource | `orderby` values |
|---|---|
| Contacts | `id`, `email`, `first_name`, `last_name`, `status`, `date_created_gmt`, `date_modified_gmt` |
| Companies | `id`, `name`, `status`, `email`, `date_created_gmt` |
| Invoices | `id`, `status`, `date`, `date_created_gmt` |
| Transactions | `id`, `status`, `date`, `date_created_gmt` |
| Tasks | `id`, `start`, `date_created_gmt` |
| Logs | `id`, `date_created_gmt` |
| Forms | `id`, `title`, `date_created_gmt` |
| Quote templates | `id`, `title`, `date_created_gmt` |
| Others | Default `id` behaviour |

---

## Searching

Pass `search` (or its alias `s`) to run a free-text search on the resource:

```bash
curl -u "admin:APP_PASSWORD" \
  "https://example.com/wp-json/jpcrm-improved/v1/companies?search=Acme"
```

Search support and the searched fields are determined by the underlying Jetpack
CRM data layer. Contacts also support the `has_email` filter to restrict results to
records with an email address.

---

## Response headers

Collection responses include standard pagination headers:

| Header | Description |
|---|---|
| `X-WP-Total` | Total number of records matching the query. |
| `X-WP-TotalPages` | Total number of pages for the requested `per_page`. |

Example:

```
X-WP-Total: 128
X-WP-TotalPages: 7
```

---

## HTTP status codes

| Status | Meaning |
|---|---|
| `200 OK` | Successful read, update, delete or batch request. |
| `201 Created` | A resource was created. |
| `400 Bad Request` | Malformed input or an invalid parameter value. |
| `401 Unauthorized` | Authentication is missing or invalid. |
| `403 Forbidden` | The authenticated user lacks the required CRM capability. |
| `404 Not Found` | The resource or route does not exist. |
| `405 Method Not Allowed` | The method is not registered for the route (for example, `GET /mcp`). |
| `422 Unprocessable Entity` | The payload failed validation. |
| `500 Internal Server Error` | An unexpected server-side error, for example when the CRM data layer is unavailable. |

---

## Error format and codes

Errors use the standard WordPress `WP_Error` JSON shape:

```json
{
  "code": "jpcrm_rest_not_found",
  "message": "The requested resource was not found.",
  "data": { "status": 404 }
}
```

| Code | HTTP | When |
|---|---|---|
| `jpcrm_rest_invalid_param` | `400` | An invalid parameter value was supplied (for example, too many batch requests). |
| `jpcrm_rest_unauthorized` | `401` | The request is not authenticated. |
| `jpcrm_rest_forbidden` | `403` | The current user lacks the required capability. |
| `jpcrm_rest_not_found` | `404` | The object or route could not be found. |
| `jpcrm_rest_method_not_allowed` | `405` | The HTTP method is not allowed (used by the MCP endpoint). |
| `jpcrm_rest_validation_error` | `422` (sometimes `400`) | The request body failed validation. |
| `jpcrm_rest_unknown_error` | `500` | An unexpected internal error occurred. |
| `jpcrm_rest_no_contact` | `422` | An email could not be sent because no matching contact exists. |
| `jpcrm_rest_send_failed` | `500` | Sending an email failed. |

---

## Field selection and embedding

`_fields` and `_embed` are handled by **WordPress core** for REST responses; the
plugin does not register additional `context` handling. `_fields` lets a client
request only specific top-level fields:

```bash
curl -u "admin:APP_PASSWORD" \
  "https://example.com/wp-json/jpcrm-improved/v1/contacts?_fields=id,email,status"
```

The `context` parameter is not implemented by the plugin, so `view`/`edit`
contexts do not change the representation. Custom fields are returned inline in
the `custom_fields` object for the entities that support them.

---

## Dates, numbers and booleans

- **Dates** are returned as ISO-8601 strings in GMT, using the `date_*_gmt` naming
  convention (for example, `date_created_gmt`, `date_modified_gmt`). Empty or
  unset dates are returned as `null`.
- **Numbers** (money, quantities, totals) are returned as JSON numbers.
- **Booleans** such as `complete`, `pinned`, `starred` are returned as JSON
  `true`/`false`.

---

## Batch operations

Run multiple operations in a single request:

```
POST /jpcrm-improved/v1/batch
```

Request body:

```json
{
  "requests": [
    { "method": "POST",   "path": "/jpcrm-improved/v1/contacts", "body": { "email": "a@b.c" } },
    { "method": "PATCH",  "path": "/jpcrm-improved/v1/contacts/123", "body": { "status": "Customer" } },
    { "method": "DELETE", "path": "/jpcrm-improved/v1/contacts/456" }
  ]
}
```

The response is an array with one entry per sub-request:

```json
[
  { "status": 201, "body": { "id": 789, "email": "a@b.c" } },
  { "status": 200, "body": { "id": 123, "status": "Customer" } },
  { "status": 200, "body": { "deleted": true } }
]
```

- The maximum number of sub-requests is `25` by default and can be changed with
  the `jpcrm_improved_rest_batch_max_requests` filter.
- Exceeding the limit returns `400 jpcrm_rest_invalid_param`.
- Each sub-request is executed with the permissions and validation of the calling
  user.

There are no separate `/bulk` endpoints; use `/batch`.

---

## OpenAPI document

The plugin generates an **OpenAPI 3.0.3** document from the registered routes and
entity registry:

```
GET /jpcrm-improved/v1/openapi.json
```

- By default the document requires authentication.
- Make it public with the `jpcrm_improved_openapi_public` filter:

  ```php
  add_filter( 'jpcrm_improved_openapi_public', '__return_true' );
  ```

- The document uses HTTP Basic (`basicAuth`) as the security scheme, because
  Application Passwords are transmitted with the `Authorization: Basic` header.

---

## Permissions

Authorization is based on Jetpack CRM capabilities. `delete` falls back to the
`write` capability when no dedicated delete capability is defined. Line items are
allowed if the user has either the invoice or the quote capability.

| Resource | Read | Write / Create / Update | Delete |
|---|---|---|---|
| Contacts | `admin_zerobs_view_customers` | `admin_zerobs_customers` | `admin_zerobs_customers` |
| Companies | `admin_zerobs_view_customers` | `admin_zerobs_customers` | `admin_zerobs_customers` |
| Segments | `admin_zerobs_view_customers` | `admin_zerobs_customers` | `admin_zerobs_customers` |
| Quotes | `admin_zerobs_view_quotes` | `admin_zerobs_quotes` | `admin_zerobs_quotes` |
| Quote templates | `admin_zerobs_view_quotes` | `admin_zerobs_quotes` | `admin_zerobs_quotes` |
| Invoices | `admin_zerobs_view_invoices` | `admin_zerobs_invoices` | `admin_zerobs_invoices` |
| Transactions | `admin_zerobs_view_transactions` | `admin_zerobs_transactions` | `admin_zerobs_transactions` |
| Tasks | `admin_zerobs_view_events` | `admin_zerobs_events` | `admin_zerobs_events` |
| Task reminders | `admin_zerobs_view_events` | `admin_zerobs_events` | `admin_zerobs_events` |
| Forms | `admin_zerobs_forms` | `admin_zerobs_forms` | `admin_zerobs_forms` |
| Logs | `admin_zerobs_logs_addedit` | `admin_zerobs_logs_addedit` | `admin_zerobs_logs_delete` |
| Line items | `admin_zerobs_view_invoices` *or* `admin_zerobs_view_quotes` | `admin_zerobs_invoices` *or* `admin_zerobs_quotes` | same as write |
| Tags | `admin_zerobs_view_customers` | `admin_zerobs_customers` | `admin_zerobs_customers` |
| Custom fields | `admin_zerobs_view_customers` | `admin_zerobs_manage_options` | `admin_zerobs_manage_options` |
| Meta | `admin_zerobs_view_customers` | `admin_zerobs_customers` | `admin_zerobs_customers` |
| Emails | `admin_zerobs_sendemails_contacts` | `admin_zerobs_sendemails_contacts` | `admin_zerobs_sendemails_contacts` |
| Email threads | `admin_zerobs_sendemails_contacts` | `admin_zerobs_sendemails_contacts` | `admin_zerobs_sendemails_contacts` |
| Email templates | `admin_zerobs_manage_options` | `admin_zerobs_manage_options` | `admin_zerobs_manage_options` |
| System (`/status`, `/me`) | `admin_zerobs_usr` | — | — |

Use `GET /me` to discover the current user's effective permissions.

---

## Resources

Each section lists the routes, the entity-specific parameters, the main fields and
a short example. `{id}` is the numeric object identifier.

### Contacts

```
GET    /contacts
POST   /contacts
GET    /contacts/{id}
PUT    /contacts/{id}
PATCH  /contacts/{id}
DELETE /contacts/{id}
```

**Collection parameters:** `search`/`s`, `status`, `owner`/`owned_by`, `company`
(company ID), `tags` (array of tag IDs), `has_email` (boolean), plus the common
collection parameters.

**Fields:**

| Field | Type | Notes |
|---|---|---|
| `id` | integer | |
| `owner` | integer | WordPress user ID |
| `status` | string | For example `Lead`, `Customer` |
| `email` | string | Required on create; expected to be unique |
| `prefix` | string | |
| `first_name` | string | |
| `last_name` | string | |
| `full_name` | string | Read-only, derived |
| `address` | object | `line1`, `line2`, `city`, `county`, `postcode`, `country` |
| `secondary_address` | object | Same keys as `address` |
| `telephones` | object | `home`, `work`, `mobile` |
| `social` | object | `twitter`, `facebook`, `linkedin` |
| `wp_user_id` | integer | Linked WordPress user, if any |
| `avatar` | string | Read-only |
| `aliases` | array | |
| `tags` | array | Tag IDs |
| `companies` | array | Related company IDs |
| `custom_fields` | object | Custom field values |
| `date_created_gmt` | string | ISO-8601, read-only |
| `date_modified_gmt` | string | ISO-8601, read-only |
| `date_last_contacted_gmt` | string | ISO-8601, read-only |

**Create example:**

```bash
curl -u "admin:APP_PASSWORD" \
  -H "Content-Type: application/json" \
  -X POST "https://example.com/wp-json/jpcrm-improved/v1/contacts" \
  -d '{
        "email": "jane@example.com",
        "first_name": "Jane",
        "last_name": "Doe",
        "status": "Lead",
        "address": { "city": "Berlin", "country": "DE" },
        "telephones": { "mobile": "+490000000" },
        "tags": ["web"],
        "custom_fields": { "source": "landing" }
      }'
```

---

### Companies

```
GET    /companies
POST   /companies
GET    /companies/{id}
PUT    /companies/{id}
PATCH  /companies/{id}
DELETE /companies/{id}
```

**Collection parameters:** `search`/`s`, `status`, `owner`/`owned_by`, `tags`.

**Fields:** `id`, `owner`, `status`, `name` (required on create), `email`,
`address` (object), `secondary_address` (object), `telephones` (object with
`main`/`secondary`), `social` (object), `tags` (array), `contacts` (array),
`custom_fields` (object), `date_created_gmt`, `date_modified_gmt`,
`date_last_contacted_gmt`.

```bash
curl -u "admin:APP_PASSWORD" \
  -H "Content-Type: application/json" \
  -X POST "https://example.com/wp-json/jpcrm-improved/v1/companies" \
  -d '{ "name": "Acme Inc.", "email": "hello@acme.example", "status": "Lead" }'
```

---

### Invoices

```
GET    /invoices
POST   /invoices
GET    /invoices/{id}
PUT    /invoices/{id}
PATCH  /invoices/{id}
DELETE /invoices/{id}
```

**Collection parameters:** `search`/`s`, `status`, `contact`, `company`, `tags`.

**Fields:**

| Field | Type | Notes |
|---|---|---|
| `id` | integer | |
| `owner` | integer | |
| `status` | string | For example `Draft`, `Sent`, `Paid` |
| `number` | string | |
| `reference` | string | |
| `date` | string | Invoice date |
| `due_date` | string | |
| `paid_date` | string | |
| `currency` | string | |
| `net`, `discount`, `shipping`, `taxes`, `total` | number | Recalculated by the CRM (read-only) |
| `line_items` | array | Invoice line items |
| `contacts` | array | Related contact IDs |
| `companies` | array | Related company IDs |
| `tags` | array | |
| `custom_fields` | object | |
| `date_created_gmt`, `date_modified_gmt` | string | ISO-8601 |

`line_items` can be supplied inline on create/update, or managed separately via the
[line items](#line-items) resource.

---

### Quotes

```
GET    /quotes
POST   /quotes
GET    /quotes/{id}
PUT    /quotes/{id}
PATCH  /quotes/{id}
DELETE /quotes/{id}
POST   /quotes/{id}/accept
DELETE /quotes/{id}/accept
```

**Collection parameters:** `search`/`s`, `status`, `contact`, `company`.

**Fields:** `id`, `owner`, `title` (required on create), `status`, `currency`,
`value`, `date`, `template`, `content`, `notes`, `send_attachments` (boolean),
`line_items` (array), `contacts` (array), `companies` (array), `tags` (array),
`custom_fields` (object). Read-only: `hash`, `viewed_count`, `accepted` (boolean),
`date_accepted_gmt`, `date_created_gmt`, `date_modified_gmt`.

**Accept a quote:**

```bash
curl -u "admin:APP_PASSWORD" \
  -H "Content-Type: application/json" \
  -X POST "https://example.com/wp-json/jpcrm-improved/v1/quotes/5/accept" \
  -d '{ "signed_by": "Jane Doe" }'
```

`DELETE /quotes/{id}/accept` clears the accepted state.

---

### Transactions

```
GET    /transactions
POST   /transactions
GET    /transactions/{id}
PUT    /transactions/{id}
PATCH  /transactions/{id}
DELETE /transactions/{id}
```

**Collection parameters:** `search`/`s`, `status`, `contact`, `company`,
`invoice`.

**Fields:** `id`, `owner`, `status`, `type`, `reference`, `origin`, `parent`,
`title`, `description`, `date`, `currency`, `net`, `fee`, `discount`, `shipping`,
`taxes`, `total`, `date_paid`, `date_completed`, `invoice` (invoice ID),
`contacts` (array), `companies` (array), `tags` (array), `custom_fields` (object),
`date_created_gmt`, `date_modified_gmt`.

---

### Tasks

```
GET    /tasks
POST   /tasks
GET    /tasks/{id}
PUT    /tasks/{id}
PATCH  /tasks/{id}
DELETE /tasks/{id}
GET    /tasks/{id}/reminders
POST   /tasks/{id}/reminders
```

**Collection parameters:** `search`/`s`, `owner`/`owned_by`, `contact`, `company`,
`complete` (boolean).

**Fields:** `id`, `owner`, `title` (required on create), `description`, `start`,
`end`, `complete` (boolean), `show_on_portal` (boolean), `show_on_calendar`
(boolean), `reminders` (array), `contacts` (array), `companies` (array), `tags`
(array), `custom_fields` (object), `date_created_gmt`, `date_modified_gmt`.

---

### Task reminders

Reminders can be managed at the top level or nested under a task.

```
GET    /task-reminders
POST   /task-reminders
GET    /task-reminders/{id}
PUT    /task-reminders/{id}
PATCH  /task-reminders/{id}
DELETE /task-reminders/{id}
GET    /tasks/{id}/reminders
POST   /tasks/{id}/reminders
```

**Collection parameters:** `event` (task ID).

**Fields:** `id`, `event` (task ID; with `remind_at` required on create),
`remind_at` (date/time), `sent` (boolean), `date_created_gmt`.

```bash
curl -u "admin:APP_PASSWORD" \
  -H "Content-Type: application/json" \
  -X POST "https://example.com/wp-json/jpcrm-improved/v1/tasks/12/reminders" \
  -d '{ "remind_at": "2026-10-01T09:00:00" }'
```

---

### Logs

```
GET    /logs
POST   /logs
GET    /logs/{id}
PUT    /logs/{id}
PATCH  /logs/{id}
DELETE /logs/{id}
```

**Collection parameters:** `object_type` (integer CRM type; alias `objtype`),
`object_id` (alias `objid`), `type` (string; alias `notetype`), `pinned`
(boolean), `owner`, plus the standard `search`/`s`, `orderby`, `order`, `page`,
`per_page` and `offset`. `X-WP-Total`/`X-WP-TotalPages` reflect all applied
filters.

> `object_id` is only applied together with `object_type`, because the CRM
> activity table has no object-id-only lookup. Pass both to read one object's
> activity, for example `/logs?object_type=1&object_id=1464`.

**Fields:** `id`, `owner`, `object_type` (integer), `object_id` (integer),
`type`, `short_description`, `long_description`, `pinned` (boolean),
`date_created_gmt`.

**Create example:**

```bash
curl -u "admin:APP_PASSWORD" \
  -H "Content-Type: application/json" \
  -X POST "https://example.com/wp-json/jpcrm-improved/v1/logs" \
  -d '{
        "object_type": 1,
        "object_id": 123,
        "type": "Call",
        "short_description": "Intro call"
      }'
```

---

### Forms

```
GET    /forms
POST   /forms
GET    /forms/{id}
PUT    /forms/{id}
PATCH  /forms/{id}
DELETE /forms/{id}
```

**Collection parameters:** `search`/`s`.

**Fields:** `id`, `owner`, `title` (required on create), `style`, `tags` (array),
`views` (integer, read-only), `conversions` (integer, read-only). Additional
form-definition fields (`label_*`, `include_terms_check`, `terms_url`,
`redirect_url`, `font`, `colours`) may be supplied and are passed through to the
CRM.

---

### Segments

```
GET    /segments
POST   /segments
GET    /segments/{id}
PUT    /segments/{id}
PATCH  /segments/{id}
DELETE /segments/{id}
POST   /segments/{id}/compile
```

**Collection parameters:** `search`/`s`.

**Fields:** `id`, `name` (required on create), `slug`, `match_type`
(`all`/`any`), `conditions` (array), `compile_count` (integer, read-only),
`date_last_compiled_gmt` (read-only).

**Compile a segment** (recalculate its matching records):

```bash
curl -u "admin:APP_PASSWORD" \
  -X POST "https://example.com/wp-json/jpcrm-improved/v1/segments/7/compile"
```

The response includes the updated `compile_count` and a `compiled` flag.

---

### Quote templates

```
GET    /quote-templates
POST   /quote-templates
GET    /quote-templates/{id}
PUT    /quote-templates/{id}
PATCH  /quote-templates/{id}
DELETE /quote-templates/{id}
```

**Collection parameters:** `search`/`s`.

**Fields:** `id`, `owner`, `title` (required on create), `value` (number),
`date`, `content`, `notes`, `currency`, `date_created_gmt`, `date_modified_gmt`.

---

### Line items

Line items belong to an invoice or a quote. They are usually managed inline through
`invoices`/`quotes` (`line_items[]`) but are also exposed as a standalone resource
for targeted operations.

```
GET    /line-items
POST   /line-items
GET    /line-items/{id}
PUT    /line-items/{id}
PATCH  /line-items/{id}
DELETE /line-items/{id}
```

**Collection parameters:** `search`/`s`, `parent_object_type`,
`parent_object_id`.

**Fields:** `id`, `order` (integer), `title` (required on create), `description`,
`quantity`, `price`, `currency`, `net`, `discount`, `fee`, `shipping`, `tax`,
`total`, `parent_object_type` (integer, required on create), `parent_object_id`
(integer, required on create), `date_created_gmt`.

---

### Tags

```
GET    /tags
POST   /tags
GET    /tags/{id}
PUT    /tags/{id}
PATCH  /tags/{id}
DELETE /tags/{id}
```

**Collection parameters:** `search`, `object_type` (integer CRM type).

**Fields:** `id`, `name` (required on create), `slug`, `object_type`, `count`
(integer, read-only).

Tags are also available as a [sub-resource](#tags-sub-resource) of most entities.

---

### Emails

The mailbox of individual messages and threads, backed by the CRM email history
table through `MailAdapter`. Emails do not support `update`; create sends a message
to an existing contact.

```
GET    /emails
POST   /emails
GET    /emails/{id}
DELETE /emails/{id}
```

**Collection parameters:** `contact` (contact ID), `status` (`inbox` or `sent`),
`starred` (boolean), `thread` (thread ID), `type` (integer CRM type), `search`,
`date_after`, `date_before`. `page` uses a **0-based offset** (default `0`).

**Fields (selection):**

| Field | Type | Notes |
|---|---|---|
| `id` | integer | |
| `thread_id` | integer | Conversation identifier |
| `contact_id` | integer | Related contact |
| `assoc_object_id` | integer | Related CRM object |
| `type` | integer | CRM mail type |
| `status` | string | `inbox` or `sent` |
| `sender_email` | string | |
| `receiver_email` | string | |
| `subject` | string | |
| `content` | string | HTML body |
| `starred` | boolean | |
| `opened` | boolean | Read-only |
| `clicked` | boolean | Read-only |
| `sent` | boolean | `true` when the message was sent |
| `date_sent` | string | ISO-8601 send time; `null` when not sent |
| `date_created_gmt` | string | ISO-8601 |

> The underlying `zbsmail_sent` column is a flag (`-1` = logged, `1` = sent), not
> a timestamp, so it is exposed as the boolean `sent`. `date_sent` is the real
> history timestamp (the same value as `date_created_gmt`) for sent messages and
> `null` otherwise.

**Send an email:**

```bash
curl -u "admin:APP_PASSWORD" \
  -H "Content-Type: application/json" \
  -X POST "https://example.com/wp-json/jpcrm-improved/v1/emails" \
  -d '{
        "contact_id": 123,
        "subject": "Hello!",
        "content": "<p>Message body</p>",
        "delivery_method": "zbs-smtp-1"
      }'
```

Requirements and behaviour:

- `content` is required, and either `contact_id` or `email` must identify an
  **existing** contact.
- `delivery_method` is optional; the CRM default account is used otherwise.
- CRM placeholders in the body are processed by the CRM.
- Sending writes to the email history and adds a log entry to the contact.

---

### Email threads

```
GET    /email-threads
GET    /email-threads/{id}
DELETE /email-threads/{id}
POST   /email-threads/{id}/star
DELETE /email-threads/{id}/star
POST   /email-threads/{id}/read
POST   /email-threads/{id}/reply
```

- `GET /email-threads` returns the latest message of each conversation.
- `GET /email-threads/{id}` returns all messages in a thread, oldest first.
- `DELETE /email-threads/{id}` deletes the entire thread.
- `POST .../star` adds the thread to favourites; `DELETE .../star` removes it.
- `POST .../read` marks the thread as read.
- `POST .../reply` sends a reply into the thread.

`page` uses a **0-based offset**.

**Reply example:**

```bash
curl -u "admin:APP_PASSWORD" \
  -H "Content-Type: application/json" \
  -X POST "https://example.com/wp-json/jpcrm-improved/v1/email-threads/42/reply" \
  -d '{ "content": "<p>Thanks for your message.</p>", "subject": "Re: Hello!" }'
```

Thread content is HTML: in `context=view` it is sanitized, otherwise returned as
stored.

---

### Email templates

System email templates backed by the CRM `system_mail_templates` table.

```
GET    /email-templates
POST   /email-templates
GET    /email-templates/{id}
PUT    /email-templates/{id}
PATCH  /email-templates/{id}
DELETE /email-templates/{id}
```

**Fields:** `id`, `active` (boolean), `delivery_method`, `from_name`,
`from_address`, `reply_to`, `cc_to`, `bcc_to`, `subject` (required on create),
`body` (HTML), `date_created_gmt`, `date_modified_gmt`.

---

### System: status and me

```
GET /status
GET /me
```

`GET /status` — availability, versions and diagnostics:

```json
{
  "status": "ok",
  "message": "Jetpack CRM REST API Improved is available.",
  "crm_version": "6.8.4",
  "api_version": "v1",
  "plugin": "0.9.0",
  "diagnostics": {
    "object_cache": false,
    "object_cache_dropin": false,
    "application_passwords_available": true,
    "application_passwords_count": 3,
    "environment_type": "local",
    "php_version": "8.3.27"
  }
}
```

The `diagnostics` block exists to debug `401` authentication problems. When
`object_cache` (or `object_cache_dropin`) is `true`, an Application Password
created with `wp user application-password create` may not be recognised by web
requests: WP-CLI does not invalidate the cached `_application_passwords`
usermeta. Create the password in the WordPress admin, or flush the cache from a
web context (`wp cache flush` from CLI is not enough).

`GET /me` — the current user and effective CRM permissions:

```json
{
  "id": 1,
  "login": "admin",
  "display_name": "Admin",
  "capabilities": {
    "contacts": { "read": true, "create": true, "update": true, "delete": true },
    "invoices": { "read": true, "create": true, "update": true, "delete": true }
  }
}
```

---

## Sub-resources

For the following entities:

`contacts`, `companies`, `invoices`, `quotes`, `transactions`, `tasks`, `forms`,
`segments`, `quote-templates`, `logs`

the plugin registers the sub-resources below. Replace `{resource}` with the plural
path (for example `contacts`) and `{id}` with the object ID. Each route uses the
read/write capability of its parent resource.

### Tags sub-resource

| Method | Path | Description |
|---|---|---|
| `GET` | `/{resource}/{id}/tags` | List the tags attached to the object. |
| `POST` | `/{resource}/{id}/tags` | Set tags. Body: `{ "tags": ["vip"], "mode": "replace" }`. `mode` is `replace`, `append` or `remove`. |
| `DELETE` | `/{resource}/{id}/tags/{tag}` | Remove a single tag. |

### Meta sub-resource

| Method | Path | Description |
|---|---|---|
| `GET` | `/{resource}/{id}/meta` | Return all meta for the object. |
| `GET` | `/{resource}/{id}/meta/{key}` | Return one meta value. |
| `POST`/`PUT`/`PATCH` | `/{resource}/{id}/meta/{key}` | Set a value. Body: `{ "value": ... }`. |
| `DELETE` | `/{resource}/{id}/meta/{key}` | Delete a meta key. |

### Custom fields sub-resource

| Method | Path | Description |
|---|---|---|
| `GET` | `/{resource}/{id}/custom-fields` | Return all custom field values. |
| `POST`/`PUT`/`PATCH` | `/{resource}/{id}/custom-fields` | Set multiple values. Body: `{ "values": { "key": "value" } }`. |
| `GET` | `/{resource}/{id}/custom-fields/{key}` | Return one value. |
| `POST`/`PUT`/`PATCH` | `/{resource}/{id}/custom-fields/{key}` | Set one value. Body: `{ "value": ... }`. |
| `DELETE` | `/{resource}/{id}/custom-fields/{key}` | Delete a custom field value. |

### External sources sub-resource

| Method | Path | Description |
|---|---|---|
| `GET` | `/{resource}/{id}/external-sources` | List external source records. |
| `POST` | `/{resource}/{id}/external-sources` | Add a record. Body: `{ "source": "shopify", "uid": "123", "origin": "api" }`. |
| `DELETE` | `/{resource}/{id}/external-sources/{ext_id}` | Delete a record by its ID. |

### Object links sub-resource

| Method | Path | Description |
|---|---|---|
| `GET` | `/{resource}/{id}/links` | List linked objects. |
| `POST` | `/{resource}/{id}/links` | Create a link. Body: `{ "object_type": 2, "object_id": 55 }`. |
| `DELETE` | `/{resource}/{id}/links/{object_type}/{object_id}` | Delete a link. |

`object_type` is the numeric CRM type (contacts `1`, companies `2`, quotes `3`,
invoices `4`, transactions `5`, tasks `6`, forms `7`, logs `8`, segments `9`,
quote templates `12`).

---

## Hooks and filters

| Hook | Type | Default | Purpose |
|---|---|---|---|
| `jpcrm_improved_rest_controllers` | filter | controller list | Add or remove REST controllers. |
| `jpcrm_improved_rest_max_per_page` | filter | `1000` | Maximum allowed `per_page`. `0` or less disables the cap. |
| `jpcrm_improved_rest_batch_max_requests` | filter | `25` | Maximum number of sub-requests in `POST /batch`. |
| `jpcrm_improved_openapi_public` | filter | `false` | Make `/openapi.json` publicly readable. |

---

## Known limitations

- **`context` is not implemented.** WordPress core provides `_fields` and `_embed`
  for REST responses, but the plugin does not define per-field `view`/`edit`
  contexts.
- **No `/bulk` endpoints.** Use `POST /batch` for multiple operations.
- **Page semantics differ for mail.** Emails and email threads treat `page` as a
  0-based offset; line items accept a 1-based page and convert it internally.
- **`per_page = -1` returns all records** and bypasses pagination.
- **Filter support varies.** Not every entity exposes every filter. The
  authoritative list for a given deployment is available at
  `GET /me` (permissions) and via the MCP `crm_entities` catalog.
- **Custom fields** are returned inline only for entities that support them
  (contacts, companies, invoices, quotes, transactions, tasks); use the
  custom-fields sub-resource for the others.
