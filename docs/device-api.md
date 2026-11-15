# TAD-PAK Attendance — Device API v1

Contract between the attendance terminal firmware (ESP32-S3, ESP-IDF 0.2.x) and
the TAD-PAK Attendance server. The same contract applies to Cloud and Custom
(self-hosted) installs.

- Base URL: `https://<server>/api/device/v1`
- JSON in and out. Send `Accept: application/json` and `Content-Type: application/json`.
- All times are UTC, ISO 8601 (`2026-11-15T08:00:00Z`).
- IDs issued by the server (`device_id`, `employee.id`, template `id`, command `id`) are UUID strings.
- Stateless: no cookies or sessions.

## Contents

1. [Authentication](#1-authentication)
2. [Errors and rate limits](#2-errors-and-rate-limits)
3. [Pairing — `POST /claim`](#3-pairing--post-claim)
4. [`GET /me`](#4-get-me)
5. [Attendance upload — `POST /attendance`](#5-attendance-upload--post-attendance)
6. [Employee sync — `GET /employees/changes`](#6-employee-sync--get-employeeschanges)
7. [On-device enrolment — `POST /employees/push`](#7-on-device-enrolment--post-employeespush)
8. [`POST /heartbeat`](#8-post-heartbeat)
9. [Live connection (WebSocket)](#9-live-connection-websocket)
10. [Commands](#10-commands)
11. [Biometric templates](#11-biometric-templates)
12. [Recommended firmware flow](#12-recommended-firmware-flow)
13. [Deviations from the original brief](#13-deviations-from-the-original-brief)

---

## 1. Authentication

Every endpoint except `POST /claim` needs the device token:

```
Authorization: Bearer tdt_3kq9...   (52 characters)
```

- The token is returned **once**, by `POST /claim`. Store it in NVS (encrypted
  if possible). The server keeps only a hash and cannot show it again.
- It does not expire. It stops working only when a user clicks **Revoke device**,
  or when the device is **re-paired** (the old token is revoked the moment the
  new code is claimed).
- A revoked token, or a token for a device that is not `active`, returns
  `401 {"error":"unauthenticated"}`. Firmware should then drop the token and
  show the pairing screen.
- Every authenticated request updates the device's `last_seen_at` and `last_ip`.

## 2. Errors and rate limits

| Status | Meaning                                             | Body                                                                                                       |
| ------ | --------------------------------------------------- | ---------------------------------------------------------------------------------------------------------- |
| 401    | Missing, invalid or revoked token                   | `{"error":"unauthenticated","message":"..."}`                                                              |
| 403    | Not allowed (for example, another device's channel) | `{"error":"forbidden"}`                                                                                    |
| 404    | Unknown resource                                    | `{"error":"not_found"}`                                                                                    |
| 409    | Command already closed                              | `{"error":"command_closed","status":"done"}`                                                               |
| 422    | Validation error                                    | `{"message":"...","errors":{"field":["..."]}}`, or for `/claim` `{"error":"code_invalid","message":"..."}` |
| 429    | Rate limited                                        | `{"error":"too_many_attempts", ...}`, with a `Retry-After` header                                          |

Limits (configurable in `config/devices.php`):

- `POST /claim`: 10 requests per minute per IP. After **5 wrong codes** from one
  IP, that IP is locked for 15 minutes, and even a correct code returns `429`.
- Everything else: 120 requests per minute per device.

On `429`, wait `Retry-After` seconds. On network errors or `5xx`, retry with
exponential backoff (for example 2 s, 4 s … capped at 5 min).

## 3. Pairing — `POST /claim`

No authentication. A user clicks **Create OTT** on the device's page in the web
app. A 12-digit one-time code appears with a countdown, for example
`4831-0927-5516`. The code is valid for **10 minutes** and works only once.
Creating a new code invalidates the previous one.

Request:

```http
POST /api/device/v1/claim
Content-Type: application/json

{
  "code": "483109275516",
  "hardware_id": "A1B2C3D409A4",
  "mac": "A1:B2:C3:D4:09:A4",
  "model": "TAD-PAK AD",
  "firmware_version": "0.2.1",
  "variant": "AD-DEV-1"
}
```

| Field                                  | Required | Notes                                              |
| -------------------------------------- | -------- | -------------------------------------------------- |
| `code`                                 | yes      | 12 digits. Dashes are accepted (`4831-0927-5516`). |
| `hardware_id`                          | yes      | Hex string. Users see the last 4 digits (`09A4`).  |
| `mac`                                  | no       | `AA:BB:CC:DD:EE:FF`                                |
| `model`, `firmware_version`, `variant` | no       | Stored on the device record.                       |

`201 Created`:

```json
{
    "device_id": "0194b9a2-6c1e-7d3a-9f00-4c2b1a0e9d11",
    "name": "Main gate",
    "status": "active",
    "hardware_id": "A1B2C3D409A4",
    "mac": "A1:B2:C3:D4:09:A4",
    "model": "TAD-PAK AD",
    "firmware_version": "0.2.1",
    "variant": "AD-DEV-1",
    "tenant": null,
    "site": { "id": 1, "name": "Head office" },
    "location": { "id": 3, "name": "Main gate" },
    "server_time": "2026-11-15T08:00:00Z",
    "device_token": "tdt_3kq9...",
    "ws": {
        "host": "ws.example.com",
        "port": 443,
        "scheme": "wss",
        "app_key": "gkfcnzuog2ckodv0kaqk",
        "channel": "private-device.0194b9a2-6c1e-7d3a-9f00-4c2b1a0e9d11",
        "auth_endpoint": "/api/device/v1/broadcasting/auth"
    }
}
```

- `tenant` is `null` on a Custom install. On Cloud it will be `{ "id", "name" }`.
- `site` and `location` are `null` when not set.

`422` failures:

| `error`        | Meaning                                            | Firmware action                     |
| -------------- | -------------------------------------------------- | ----------------------------------- |
| `code_invalid` | No such code (typo)                                | Let the user re-enter it.           |
| `code_expired` | Older than 10 minutes, or replaced by a newer code | Ask the user to click **New code**. |
| `code_used`    | Already claimed                                    | Ask for a new code.                 |

A wrong `code` format or missing `hardware_id` returns the standard Laravel
validation `422` (`errors` object, no `error` key).

After a successful claim, the web page closes the code dialog by itself.

## 4. `GET /me`

Returns the same device object as `/claim`, without `device_token`, plus `ws`.
Use it on boot to validate the token and read `server_time` to set the clock
when there is no SNTP.

```http
GET /api/device/v1/me
Authorization: Bearer tdt_...
```

```json
{
    "device_id": "0194b9a2-...",
    "name": "Main gate",
    "status": "active",
    "server_time": "2026-11-15T08:00:00Z",
    "ws": { "...": "..." }
}
```

## 5. Attendance upload — `POST /attendance`

The device stores events locally and uploads them in batches of **at most 500**.
Uploads are **idempotent**: `(device, device_event_id)` is unique, so re-sending
a batch after a timeout is safe.

```http
POST /api/device/v1/attendance
Authorization: Bearer tdt_...

{
  "events": [
    {
      "device_event_id": 1042,
      "occurred_at": "2026-11-15T07:59:31Z",
      "uptime_s": 86012,
      "employee_id": "0194b9a3-1111-7000-8000-000000000001",
      "employee_local_id": 17,
      "method": "face",
      "similarity": 0.91
    },
    {
      "device_event_id": 1043,
      "occurred_at": null,
      "uptime_s": 86050,
      "employee_id": null,
      "employee_local_id": 18,
      "method": "face",
      "similarity": 0.87
    }
  ]
}
```

| Field               | Type                              | Notes                                                                                                                              |
| ------------------- | --------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------- |
| `device_event_id`   | integer ≥ 0, required             | Device-side sequence. Never reuse a number on one device.                                                                          |
| `occurred_at`       | ISO 8601 or `null`                | `null` if the clock was not set. `uptime_s` and `received_at` are kept for later correction.                                       |
| `uptime_s`          | integer or `null`                 | Seconds since boot.                                                                                                                |
| `employee_id`       | server UUID or `null`             | `null` for a person enrolled on the device but not yet mapped. Old IDs of merged employees are resolved to the surviving employee. |
| `employee_local_id` | any                               | Stored as received.                                                                                                                |
| `method`            | `face` or `fingerprint`, required |                                                                                                                                    |
| `similarity`        | 0–1 or `null`                     |                                                                                                                                    |

`200 OK`:

```json
{
    "accepted": [1042, 1043],
    "duplicates": [],
    "rejected": [{ "id": 1050, "reason": "invalid: method" }]
}
```

- **accepted**: stored now.
- **duplicates**: already stored earlier, or repeated inside this batch. Treat
  them as success.
- **rejected**: invalid and not stored; retrying unchanged will fail again.

Delete an event locally once it is in `accepted` or `duplicates`. Keep
`rejected` events for diagnostics.

An `employee_id` the server does not know (or one belonging to another tenant)
does **not** reject the event. It is stored with no employee, and the original
value is kept in `raw`.

## 6. Employee sync — `GET /employees/changes`

The device pulls everything that changed since its last cursor.

```http
GET /api/device/v1/employees/changes?cursor=0&limit=200&types=face_mfn_s8_v1,fp_zn600
Authorization: Bearer tdt_...
```

| Param    | Default | Notes                                                              |
| -------- | ------- | ------------------------------------------------------------------ |
| `cursor` | `0`     | `0` = full sync. Afterwards, send the `cursor` returned last time. |
| `limit`  | 200     | Max 500. Counts change rows; one page can hold fewer employees.    |
| `types`  | all     | Template types this device supports. Other types are left out.     |

```json
{
    "cursor": "5812",
    "has_more": false,
    "changes": [
        {
            "op": "upsert",
            "employee": {
                "id": "0194b9a3-...-0001",
                "name": "Ayesha Khan",
                "code": "E1001"
            },
            "templates": [
                {
                    "id": "0194b9a4-...",
                    "type": "face_mfn_s8_v1",
                    "model_version": "MFN_S8_V1",
                    "data_base64": "AAAgQQAAoEA...",
                    "sha256": "9f2c...e1"
                }
            ],
            "merged_into": null
        },
        {
            "op": "delete",
            "employee": {
                "id": "0194b9a3-...-0002",
                "name": "Bilal",
                "code": "E1002"
            },
            "templates": [],
            "merged_into": null
        },
        {
            "op": "merge",
            "employee": {
                "id": "0194b9a3-...-0003",
                "name": "A. Khan",
                "code": null
            },
            "templates": [],
            "merged_into": "0194b9a3-...-0001"
        }
    ]
}
```

How to apply each op:

| `op`     | Device action                                                                                                                                                                                                                 |
| -------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `upsert` | Create or replace the employee. **Replace the whole template set** with `templates`: any local template whose `id` is not in the list must be removed.                                                                        |
| `delete` | Remove the employee and all their templates. Sent when the employee was deleted, set inactive, or is no longer allowed on this device. It may also arrive for an employee the device never had; ignore it then.               |
| `merge`  | `employee.id` was merged into `merged_into`. Delete the old employee locally and re-point local references (for example unsent attendance) to `merged_into`. The survivor arrives as an `upsert` in the same or a later page. |

Rules:

- Each employee appears at most once per page, with their **current** state.
- Keep requesting with the new `cursor` while `has_more` is `true`. Save the
  cursor only after the page has been applied.
- Only employees allowed on this device are upserted: those assigned to it, or
  those marked **All devices**.
- Templates only match templates of the same `type` **and** `model_version`.

When something changes, the server also sends `employees_changed` over the
WebSocket (section 9). The device should then pull at once instead of waiting
for its timer. A timer of about 15 minutes is still recommended as a fallback.

## 7. On-device enrolment — `POST /employees/push`

Sends employees enrolled on the device to the server. It is used by the Quick
start "upload with consent" step and for ongoing enrolment. **Consent is
required.**

```http
POST /api/device/v1/employees/push
Authorization: Bearer tdt_...

{
  "employees": [
    {
      "local_id": 17,
      "name": "Ayesha Khan",
      "code": "E1001",
      "templates": [
        {
          "type": "face_mfn_s8_v1",
          "model_version": "MFN_S8_V1",
          "data_base64": "AAAgQQAAoEA...",
          "sha256": "9f2c...e1",
          "quality": 0.82
        }
      ]
    },
    { "local_id": 18, "name": "New Visitor", "code": null, "templates": [] }
  ],
  "consent": { "given_by": "HR Manager — Sana Ali", "given_at": "2026-11-15T09:00:00Z" }
}
```

- At most 100 employees and 6 templates per employee in one request.
- **Matching**: an employee with a `code` is matched to the existing employee
  with that code. On a match the server keeps its own name. With no match or no
  code, a new employee is created.
- The employee is allowed on the pushing device automatically, so it syncs back.
- Templates are **deduplicated by SHA-256** of the decoded binary, so pushing
  the same template again does nothing. An employee keeps at most 3 templates
  per type; older ones are dropped.
- `sha256` is optional. If sent, it must match the data or the request fails.
- The consent record (who, when, which device, which employees) is stored and
  audited.
- The whole request is validated before anything is written: one bad template
  fails all of it with `422`, and the error key names it (for example
  `employees.0.templates.1`).

`200 OK`:

```json
{
    "mapping": [
        {
            "local_id": 17,
            "employee_id": "0194b9a3-...-0001",
            "created": false,
            "templates_added": 1
        },
        {
            "local_id": 18,
            "employee_id": "0194b9a3-...-0009",
            "created": true,
            "templates_added": 0
        }
    ]
}
```

Replace local IDs with `employee_id` and use it in attendance uploads.
`created` and `templates_added` are additions to the brief.

## 8. `POST /heartbeat`

A fallback when the WebSocket is down. Send it every 60 s while there is no
WebSocket connection.

```http
POST /api/device/v1/heartbeat
Authorization: Bearer tdt_...

{ "firmware_version": "0.2.1", "uptime_s": 86400, "free_heap": 182344, "stats": { "events_queued": 3 } }
```

```json
{
    "server_time": "2026-11-15T08:00:00Z",
    "commands": [
        {
            "id": "0194b9a5-...",
            "name": "sync_now",
            "args": {},
            "issued_at": "2026-11-15T07:59:00Z"
        }
    ]
}
```

`commands` (an addition to the brief) carries commands queued while the device
had no WebSocket. They are marked as sent when returned; report results as in
section 10. A heartbeat does **not** set the device online in the web app. Only
the WebSocket connection does.

## 9. Live connection (WebSocket)

The server runs **Laravel Reverb**, which speaks the Pusher Channels protocol
v7. The device is a plain WebSocket client.

1. Connect to
   `{ws.scheme}://{ws.host}:{ws.port}/app/{ws.app_key}?protocol=7&client=tadpak-fw&version=0.2`
2. Receive
   `{"event":"pusher:connection_established","data":"{\"socket_id\":\"123.456\",\"activity_timeout\":30}"}`.
   Keep the `socket_id`.
3. Authorise the channel over HTTP:

    ```http
    POST /api/device/v1/broadcasting/auth
    Authorization: Bearer tdt_...

    { "socket_id": "123.456", "channel_name": "private-device.0194b9a2-..." }
    ```

    ```json
    { "auth": "gkfcnzuog2ckodv0kaqk:6c9e0f...hex" }
    ```

    The only channel a device may authorise is its own,
    `private-device.{device_id}`; anything else returns `403`.

4. Subscribe:

    ```json
    {
        "event": "pusher:subscribe",
        "data": {
            "channel": "private-device.0194b9a2-...",
            "auth": "gkfcnzuog2ckodv0kaqk:6c9e0f..."
        }
    }
    ```

    and wait for `pusher_internal:subscription_succeeded`. The web app now shows
    the device as **online**.

5. Every `activity_timeout` seconds (30) with no traffic, send
   `{"event":"pusher:ping","data":{}}` and expect `pusher:pong`. If the server
   hears nothing for about 90 s, the device is marked offline.
6. On disconnect, reconnect with backoff (1 s, 2 s, 4 s … max 60 s) and repeat
   steps 2–4. Pull `/employees/changes` after reconnecting, since hints may have
   been missed.

Events the server sends on `private-device.{id}` (the `data` field is a JSON
**string**, as in Pusher):

| `event`             | `data`                             | Device action                  |
| ------------------- | ---------------------------------- | ------------------------------ |
| `command`           | `{"id","name","args","issued_at"}` | Run it; see section 10.        |
| `employees_changed` | `{"at":"2026-11-15T08:00:00Z"}`    | Pull `/employees/changes` now. |

## 10. Commands

Format: `{ "id": "<uuid>", "name": "<string>", "args": { ... }, "issued_at": "<iso>" }`

| `name`         | `args`                                       | Behaviour                                                |
| -------------- | -------------------------------------------- | -------------------------------------------------------- |
| `sync_now`     | `{}`                                         | Pull `/employees/changes` and upload pending attendance. |
| `reboot`       | `{}`                                         | Report the result **first**, then reboot.                |
| `show_message` | `{ "text": "≤120 chars", "seconds": 1–600 }` | Show the text on screen for `seconds`.                   |

Unknown command names must be answered with `status: "error"` and
`detail: "unknown_command"`, so new commands can be added without breaking
older firmware.

Report the result:

```http
POST /api/device/v1/commands/{id}/result
Authorization: Bearer tdt_...

{ "status": "ok", "detail": "message shown" }
```

```json
{ "id": "0194b9a5-...", "status": "done" }
```

- `status` is `ok` or `error`; `detail` is optional (up to 2000 chars).
- Server-side states: `queued` → `sent` → `done` | `failed`. A command not
  completed within **10 minutes** becomes `expired`.
- Reporting twice, or reporting an expired command, returns `409`. Another
  device's command returns `404`.

## 11. Biometric templates

| `type`           | `model_version` | Data                                                                     |
| ---------------- | --------------- | ------------------------------------------------------------------------ |
| `face_mfn_s8_v1` | `MFN_S8_V1`     | Exactly 2048 bytes: 512 × float32, little-endian, as produced by esp-dl. |
| `fp_zn600`       | `ZN600`         | Opaque module template, up to 8192 bytes.                                |

- `data_base64` is standard base64 (RFC 4648, with padding) of the raw bytes.
- `sha256` is lowercase hex of the raw bytes, not of the base64 text.
- There are up to 3 face templates per employee.
- The server encrypts template data at rest, never logs it, and never shows it
  to web users (they see counts only).

## 12. Recommended firmware flow

```
boot
 ├─ no token → show "Pair with server" → enter code → POST /claim → save token, cursor=0
 └─ token → GET /me
       ├─ 401 → delete token → pairing screen
       └─ 200 → set clock from server_time if SNTP failed
connect WebSocket → auth → subscribe
sync loop: GET /employees/changes?cursor=… until has_more=false
timers:  attendance upload every 30 s (when events are queued)
         employee sync every 15 min (plus on employees_changed / sync_now)
         heartbeat every 60 s only while the WebSocket is down
```

## 13. Deviations from the original brief

- **`device_event_id` is an integer.** It is a per-device counter; it is
  simpler to index and to dedupe than a free-form string.
- **`tenant` is `null` on Custom installs.** Tenancy is not live yet; every
  table has a nullable `tenant_id` ready for it.
- **Brute-force lock is per IP, not per code.** A wrong code cannot be traced
  to a specific code, so the lock is per IP (5 failures → 15 min), on top of
  10 requests/min. With 10¹² possible codes, 10 minutes of life and these
  limits, guessing is not practical.
- **Additive fields:**
    - `/claim` and `/me`: `status`, `hardware_id`, `mac`, `model`,
      `firmware_version`, `variant` and `ws.auth_endpoint`.
    - `/heartbeat`: `commands`.
    - `/employees/push`: `created` and `templates_added` in each `mapping` entry.
- **`sync_versions` is a change log.** Its auto-increment `id` is the cursor;
  each row only names the employee that changed. This handles deletions,
  merges and removal from a device without separate tombstone rows.
