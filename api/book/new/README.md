# `/homelib/book/new`

POST endpoint for creating a new book entry.

## Usage

```
POST /homelib/book/new
Content-Type: application/json
Accept: application/json
```

### Request Body

| Field | Type | Required | Description |
| --- | --- | --- | --- |
| `title` | string | Yes | Display title of the book. |
| `author` | string | No | Author name. |
| `isbn` | string | No | Free-form ISBN or identifier. |
| `file_ids` | array<int> | No | IDs from `/homelib/files/new`. Stores attachments captured at creation time. |

All new books default to `inLibrary = 1`, no borrower, and `dateAdded = NOW()`.

### Successful Response

Status `201 Created`

```json
{
  "success": true,
  "data": {
    "id": 15,
    "dateAdded": "2025-01-05 09:42:11",
    "isbn": "9780143127550",
    "inLibrary": 1,
    "borrowedBy": null,
    "returnBy": null,
    "title": "Algorithms to Live By",
    "author": "Brian Christian"
  }
}
```

### Errors

| Status | Body | When |
| --- | --- | --- |
| `405` | `{ "success": false, "error": "Method not allowed" }` | Request method is not `POST`. |
| `422` | `{ "success": false, "error": "Title is required" }` | Missing/empty `title` or invalid payload. |
| `500` | `{ "success": false, "error": "Internal server error", "details": { ... } }` | Unexpected server exception.

### Example

```
curl -X POST https://localhost/homelib/book/new \
  -H 'Content-Type: application/json' \
  -d '{
    "title": "Algorithms to Live By",
    "author": "Brian Christian",
    "isbn": "9780143127550",
    "file_ids": [3, 4]
  }'
```
