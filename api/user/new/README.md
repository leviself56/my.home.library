# `/homelib/user/new`

POST endpoint for creating a new library user account.

## Usage

```
POST /homelib/user/new
Content-Type: application/json
Accept: application/json
```

### Request Body

| Field | Type | Required | Description |
| --- | --- | --- | --- |
| `username` | string | Yes | Unique handle for the user. 3-64 chars, lowercase letters, numbers, dot, dash, underscore. Stored in lowercase. |
| `name` | string | Yes | Display name of the patron or librarian. |
| `type` | string | No | `librarian` or `user`. Defaults to `user`. |

### Successful Response

Status `201 Created`

```json
{
  "success": true,
  "data": {
    "id": 12,
    "type": "user",
    "created_datetime": "2025-01-05 12:01:22",
    "username": "ada",
    "name": "Ada Lovelace"
  }
}
```

### Errors

| Status | Body | When |
| --- | --- | --- |
| `405` | `{ "success": false, "error": "Method not allowed" }` | Request method is not `POST`. |
| `422` | Validation message | Missing/invalid `username`, `name`, or `type`. |
| `409` | `{ "success": false, "error": "Username already exists" }` | Duplicate username. |
| `500` | `{ "success": false, "error": "Internal server error", "details": { ... } }` | Unexpected server exception.

### Example

```
curl -X POST https://localhost/homelib/user/new \
  -H 'Content-Type: application/json' \
  -d '{
    "username": "ada",
    "name": "Ada Lovelace",
    "type": "librarian"
  }'
```
