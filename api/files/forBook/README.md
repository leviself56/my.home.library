# `/homelib/files/forBook`

GET endpoint that aggregates every file linked to a book through either `books.dateCreated_file_ids` or any checkout record's `in_file_ids`.

## Usage

```
GET /homelib/files/forBook?bookId=7
Accept: application/json
```

### Query Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `bookId` or `id` | integer | Yes | Book identifier to inspect. |

### Successful Response

Status `200 OK`

```json
{
  "success": true,
  "data": {
    "bookId": 7,
    "file_ids": [3, 4, 9],
    "files": [
      {
        "id": 3,
        "created_datetime": "2025-01-01 09:33:11",
        "filename": "file_20250101_093311_a1b2c3d4.png",
        "show_url": "/homelib/files/show/?id=3"
      }
    ]
  }
}
```

Files are deduplicated and returned newest-to-oldest according to the stored ids (order of discovery).

### Errors

| Status | Body | When |
| --- | --- | --- |
| `405` | `{ "success": false, "error": "Method not allowed" }` | Request method is not `GET`. |
| `422` | `{ "success": false, "error": "A valid bookId query parameter is required" }` | Missing/invalid book id. |
| `409` | `{ "success": false, "error": "Book not found" }` | Supplied id does not exist. |
| `500` | `{ "success": false, "error": "Internal server error", "details": { ... } }` | Unexpected server exception.

### Example

```
curl https://localhost/homelib/files/forBook?bookId=7
```
