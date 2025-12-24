# `/homelib/book/listAllOut`

GET endpoint that returns every book currently checked out (`inLibrary = 0`).

## Usage

```
GET /homelib/book/listAllOut
Accept: application/json
```

### Successful Response

Status `200 OK`

```json
{
  "success": true,
  "data": {
    "count": 3,
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

Books are ordered by soonest `returnBy`, then title.

### Errors

| Status | Body | When |
| --- | --- | --- |
| `405` | `{ "success": false, "error": "Method not allowed" }` | Request method is not `GET`. |
| `500` | `{ "success": false, "error": "Internal server error", "details": { ... } }` | Unexpected server exception.

### Example

```
curl https://localhost/homelib/book/listAllOut
```
