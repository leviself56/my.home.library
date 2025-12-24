# `/homelib/files/listAll`

GET endpoint that returns every stored file with optional filtering by filename.

## Usage

```
GET /homelib/files/listAll?filename=cover
Accept: application/json
```

### Query Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `filename` | string | No | Case-insensitive substring filter applied to sanitized storage names. |

### Successful Response

Status `200 OK`

```json
{
  "success": true,
  "data": {
    "filters": {
      "filename": "cover"
    },
    "count": 2,
    "files": [
      {
        "id": 32,
        "created_datetime": "2025-01-06 14:22:41",
        "filename": "cover.png",
        "show_url": "/homelib/files/show/?id=32"
      }
    ]
  }
}
```

Files are ordered newest first (`created_datetime` desc, then id).

### Errors

| Status | Body | When |
| --- | --- | --- |
| `405` | `{ "success": false, "error": "Method not allowed" }` | Request method not `GET`. |
| `500` | `{ "success": false, "error": "Internal server error", "details": { ... } }` | Unexpected server exception.

### Example

```
curl "https://localhost/homelib/files/listAll?filename=png"
```
