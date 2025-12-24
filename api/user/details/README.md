# `/homelib/user/details`

GET endpoint for fetching a single user record by id.

## Usage

```
GET /homelib/user/details?id=12
Accept: application/json
```

### Query Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `id` | integer | Yes | User ID to fetch. |

### Successful Response

Status `200 OK`

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
| `405` | `{ "success": false, "error": "Method not allowed" }` | Request method is not `GET`. |
| `422` | `{ "success": false, "error": "A valid id query parameter is required" }` | Missing/invalid `id`. |
| `404` | `{ "success": false, "error": "User not found" }` | No match for the provided id. |
| `500` | `{ "success": false, "error": "Internal server error", "details": { ... } }` | Unexpected server exception.

### Example

```
curl https://localhost/homelib/user/details?id=12
```
