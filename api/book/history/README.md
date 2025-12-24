# `/homelib/book/history`

GET endpoint returning the full checkout / check-in log for a single book.

## Usage

```
GET /homelib/book/history?bookId=7
Accept: application/json
```

### Query Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `bookId` or `id` | integer | Yes | Book identifier. |

### Successful Response

Status `200 OK`

```json
{
  "success": true,
  "data": {
    "bookId": 7,
    "events": [
      {
        "id": 56,
        "bookID": 7,
        "created_datetime": "2025-01-08 09:15:22",
        "checkedOutBy": "Ada Lovelace",
        "dueDate": "2025-01-29",
        "outComment": "Needed for study group",
        "inComment": "Returned with sticky note",
        "receivedBy": "Grace Hopper",
        "receivedDateTime": "2025-01-20 18:04:11",
        "in_file_ids": [12, 13],
        "status": "returned"
      }
    ]
  }
}
```

Events are ordered newest first. `status` is `checked_out` when the entry is still open (book not yet returned) and `returned` when `receivedDateTime` is populated.

### Errors

| Status | Body | When |
| --- | --- | --- |
| `405` | `{ "success": false, "error": "Method not allowed" }` | Request method not `GET`. |
| `422` | `{ "success": false, "error": "A valid bookId query parameter is required" }` | Missing/invalid book id. |
| `409` | `{ "success": false, "error": "Book not found" }` | Should a downstream lookup fail. |
| `500` | `{ "success": false, "error": "Internal server error", "details": { ... } }` | Unexpected server exception.

### Example

```
curl https://localhost/homelib/book/history?bookId=7
```
