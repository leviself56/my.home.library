# `/homelib/files/new`

POST endpoint for uploading a file into the homelib storage bucket (`_files/`).

## Usage

```
POST /homelib/files/new
Content-Type: application/json
Accept: application/json
```

### Request Body

| Field | Type | Required | Description |
| --- | --- | --- | --- |
| `content` | string | Yes | Base64-encoded file bytes. Data-URI strings (`data:image/png;base64,...`) are accepted. Use `content_base64` as an alias if preferred. |
| `filename` | string | No | Optional hint used only to derive the extension (e.g., `.png`). The stored name is always server-generated. |

### Successful Response

Status `201 Created`

```json
{
  "success": true,
  "data": {
    "id": 32,
    "created_datetime": "2025-01-06 14:22:41",
    "filename": "file_20250106_142241_a1b2c3d4.png",
    "show_url": "/homelib/files/show/?id=32"
  }
}
```

`filename` reflects the generated storage name within `_files/`.

### Errors

| Status | Body | When |
| --- | --- | --- |
| `405` | `{ "success": false, "error": "Method not allowed" }` | Request method not `POST`. |
| `422` | Validation message | Missing filename/content or invalid base64 payload. |
| `500` | `{ "success": false, "error": "Internal server error", "details": { ... } }` | Unable to persist file or metadata.

### Example

```
base64_file=$(base64 -w0 cover.png)
curl -X POST https://localhost/homelib/files/new \
  -H 'Content-Type: application/json' \
  -d "{\"filename\":\"cover.png\",\"content\":\"$base64_file\"}"
```
