# `/homelib/files/details`

GET endpoint for retrieving metadata about a single stored file.

## Usage

```
GET /homelib/files/details?id=32
Accept: application/json
```

### Query Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `id` | integer | Yes | File ID assigned during upload. |

### Successful Response

Status `200 OK`

```json
{
  "success": true,
  "data": {
    "id": 32,
    "created_datetime": "2025-01-06 14:22:41",
    "filename": "cover.png",
    "show_url": "/homelib/files/show/?id=32"
  }
}
```

### Errors

| Status | Body | When |
| --- | --- | --- |
| `405` | `{ "success": false, "error": "Method not allowed" }` | Request method not `GET`. |
| `422` | `{ "success": false, "error": "A valid id query parameter is required" }` | Missing/invalid id. |
| `404` | `{ "success": false, "error": "File not found" }` | No file with the supplied id. |
| `500` | `{ "success": false, "error": "Internal server error", "details": { ... } }` | Unexpected server exception.

### Example

```
curl https://localhost/homelib/files/details?id=32
```
