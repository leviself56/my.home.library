# `/homelib/book/list`

GET endpoint that returns library books with optional filtering.

## Usage

```
GET /homelib/book/list?status=all&borrowedBy=Ada
Accept: application/json
```

### Query Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `status` | string | No | Human-friendly filter for availability. Accepted values: `in`, `out`, `available`, `checkedout`, `true`, `false`, `1`, `0`, or `all` (no filter). |
| `inLibrary` | integer/bool | No | Alternative way to filter by availability using `1` or `0`. Takes precedence over `status` when both resolve to a value. |
| `borrowedBy` | string | No | Exact match on the `borrowedBy` column. |

### Successful Response

Status `200 OK`

```json
{
  "success": true,
  "data": {
    "filters": {
      "inLibrary": 0,
      "borrowedBy": "Ada Lovelace"
    },
    "count": 2,
    "books": [
      {
        "id": 7,
        "dateAdded": "2024-12-20 18:51:33",
        "isbn": "9780143127550",
        "inLibrary": 0,
        "borrowedBy": "Ada Lovelace",
        "returnBy": "2025-01-15",
        "title": "Algorithms to Live By",
        "author": "Brian Christian",
        "dateCreated_file_ids": [3, 4]
      }
    ]
  }
}
```

Books are sorted by title (then id).

### Errors

| Status | Body | When |
| --- | --- | --- |
| `405` | `{ "success": false, "error": "Method not allowed" }` | Request method is not `GET`. |
| `500` | `{ "success": false, "error": "Internal server error", "details": { ... } }` | Unexpected server exception.

### Example

```
curl "https://localhost/homelib/book/list?status=out"
```
