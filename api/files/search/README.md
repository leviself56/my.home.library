# `/homelib/files/search`

GET endpoint for quick filename lookups.

## Usage

```
GET /homelib/files/search?q=cover
Accept: application/json
```

### Query Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `q` or `term` | string | No* | Keyword. Blank input returns an empty set. |

### Successful Response

Status `200 OK`

```json
{
  "success": true,
  "data": {
    "query": "cover",
    "count": 1,
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

### Errors

| Status | Body | When |
| --- | --- | --- |
| `405` | `{ "success": false, "error": "Method not allowed" }` | Request method not `GET`. |
| `500` | `{ "success": false, "error": "Internal server error", "details": { ... } }` | Unexpected server exception.

### Example

```
curl "https://localhost/homelib/files/search?term=cover"
```
