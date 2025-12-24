# `/homelib/book/checkOut`

POST endpoint for marking a book as checked out and logging a checkout event.

## Usage

```
POST /homelib/book/checkOut
Content-Type: application/json
Accept: application/json
```

### Request Body

| Field | Type | Required | Description |
| --- | --- | --- | --- |
| `bookId` or `id` | integer | Yes | Identifier of the book to check out. Either `bookId` or `id` may be supplied. |
| `borrowedBy` | string | Yes | Name of the person taking the book. |
| `returnBy` | string (`YYYY-MM-DD`) | No | Optional due date. Empty or missing = no due date. |
| `outComment` | string | No | Notes recorded at checkout time. |

Requests may be JSON or form-encoded. If both `bookId` and `id` are present, `bookId` wins.

### Successful Response

Status `200 OK`

```json
{
  "success": true,
  "data": {
    "id": 7,
    "dateAdded": "2024-12-20 18:51:33",
    "isbn": "9780143127550",
    "inLibrary": 0,
    "borrowedBy": "Ada Lovelace",
    "returnBy": "2025-01-15",
    "title": "Algorithms to Live By",
    "author": "Brian Christian",
    "lastCheckOutId": 42
  }
}
```

`lastCheckOutId` is included only on this response to reference the newly created `checkOuts` row.

### Errors

| Status | Body | When |
| --- | --- | --- |
| `405` | `{ "success": false, "error": "Method not allowed" }` | Request method is not `POST`. |
| `422` | `{ "success": false, "error": "bookId is required" }` | Missing/invalid `bookId`, missing `borrowedBy`, or invalid `returnBy` date. |
| `409` | `{ "success": false, "error": "Book is already checked out" }` | Book not found, already checked out, or write failure. Message reflects specific issue. |
| `500` | `{ "success": false, "error": "Internal server error", "details": { ... } }` | Unexpected server exception.

### Example

```
curl -X POST https://localhost/homelib/book/checkOut \
  -H 'Content-Type: application/json' \
  -d '{
    "bookId": 7,
    "borrowedBy": "Ada Lovelace",
    "returnBy": "2025-01-15",
    "outComment": "Needed for research"
  }'
```
