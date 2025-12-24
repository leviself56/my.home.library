# `/homelib/book/checkIn`

POST endpoint for returning a book to the library and closing the latest checkout record.

## Usage

```
POST /homelib/book/checkIn
Content-Type: application/json
Accept: application/json
```

### Request Body

| Field | Type | Required | Description |
| --- | --- | --- | --- |
| `bookId` or `id` | integer | Yes | Identifier of the book to check in. Either `bookId` or `id` may be supplied. |
| `receivedBy` | string | Yes | Name of the staff member receiving the book. |
| `inComment` | string | No | Notes captured at check-in. |
| `file_ids` | array<int> | No | IDs of files (from `/homelib/files/new`) collected at check-in. |

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
    "author": "Brian Christian"
  }
}
```

The returned `data` mirrors the latest state of the book row after check-in.

### Errors

| Status | Body | When |
| --- | --- | --- |
| `405` | `{ "success": false, "error": "Method not allowed" }` | Request method is not `POST`. |
| `422` | `{ "success": false, "error": "bookId is required" }` | Missing/invalid `bookId` or `receivedBy`. Other validation issues also return 422. |
| `409` | `{ "success": false, "error": "Book is already checked in" }` | Book not found, already in the library, or checkout log could not be updated. Message reflects the root cause. |
| `500` | `{ "success": false, "error": "Internal server error", "details": { ... } }` | Unexpected server exception.

### Example

```
curl -X POST https://localhost/homelib/book/checkIn \
  -H 'Content-Type: application/json' \
  -d '{
    "bookId": 7,
    "receivedBy": "Grace Hopper",
    "inComment": "Returned in great condition",
    "file_ids": [8]
  }'
```
