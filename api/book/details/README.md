# `/homelib/book/details`

GET endpoint for retrieving a single book record by id.

## Usage

```
GET /homelib/book/details?id=7
Accept: application/json
```

### Query Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `id` | integer | Yes | ID of the book to fetch. |

### Successful Response

Status `200 OK`

```json
{
  "success": true,
  "data": {
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
}
```

### Errors

| Status | Body | When |
| --- | --- | --- |
| `405` | `{ "success": false, "error": "Method not allowed" }` | Request method is not `GET`. |
| `422` | `{ "success": false, "error": "A valid id query parameter is required" }` | Missing/invalid `id`. |
| `404` | `{ "success": false, "error": "Book not found" }` | Requested book row does not exist. |
| `500` | `{ "success": false, "error": "Internal server error", "details": { ... } }` | Unexpected server exception.

### Example

```
curl https://localhost/homelib/book/details?id=7
```
