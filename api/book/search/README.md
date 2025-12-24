# `/homelib/book/search`

GET endpoint for keyword searching across title, author, and ISBN fields.

## Usage

```
GET /homelib/book/search?q=algorithms
Accept: application/json
```

### Query Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `q` or `term` | string | No* | Search term. If omitted/blank, the API returns an empty result set. |

### Successful Response

Status `200 OK`

```json
{
  "success": true,
  "data": {
    "query": "algorithms",
    "count": 1,
    "books": [
      {
        "id": 7,
        "dateAdded": "2024-12-20 18:51:33",
        "isbn": "9780143127550",
        "inLibrary": 1,
        "borrowedBy": null,
        "returnBy": null,
        "title": "Algorithms to Live By",
        "author": "Brian Christian",
        "dateCreated_file_ids": [3, 4]
      }
    ]
  }
}
```

### Errors

| Status | Body | When |
| --- | --- | --- |
| `405` | `{ "success": false, "error": "Method not allowed" }` | Request method is not `GET`. |
| `500` | `{ "success": false, "error": "Internal server error", "details": { ... } }` | Unexpected server exception.

### Example

```
curl "https://localhost/homelib/book/search?term=lovelace"
```
