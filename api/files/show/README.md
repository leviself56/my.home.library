# `/homelib/files/show`

Binary endpoint that streams the stored file content back to the browser.

## Usage

```
GET /homelib/files/show?id=32
```

### Query Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `id` | integer | Yes | File ID to stream. |

### Response

- `200 OK` with `Content-Type` inferred from file bytes and `Content-Disposition: inline`. Body is the raw file.
- `400 Bad Request` when `id` is missing/invalid.
- `404 Not Found` if the file metadata or on-disk bytes are missing.

### Example

```
curl -OJ https://localhost/homelib/files/show?id=32
```
