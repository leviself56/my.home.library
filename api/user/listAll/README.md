# `/homelib/user/listAll`

GET endpoint that lists every user, optionally filtered by type or username prefix.

## Usage

```
GET /homelib/user/listAll?type=librarian&username=a
Accept: application/json
```

### Query Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `type` | string | No | `librarian`, `user`, or `all`. Defaults to all. |
| `username` | string | No | Prefix filter for usernames (case-insensitive). |

### Successful Response

Status `200 OK`

```json
{
  "success": true,
  "data": {
    "filters": {
      "type": "librarian"
    },
    "count": 2,
    "users": [
      {
        "id": 12,
        "type": "librarian",
        "created_datetime": "2025-01-05 12:01:22",
        "username": "ada",
        "name": "Ada Lovelace"
      }
    ]
  }
}
```

Users are ordered by newest first (`created_datetime` desc, then id).

### Errors

| Status | Body | When |
| --- | --- | --- |
| `405` | `{ "success": false, "error": "Method not allowed" }` | Request method is not `GET`. |
| `422` | Validation message | Invalid type filter value. |
| `500` | `{ "success": false, "error": "Internal server error", "details": { ... } }` | Unexpected server exception.

### Example

```
curl "https://localhost/homelib/user/listAll?type=all"
```
