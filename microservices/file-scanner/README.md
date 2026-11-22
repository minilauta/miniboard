# File scanner microservice

Microservice for banning files by their SHA256 and perceptual (PDQ) hashes, used to automatically remove known CSAM, illegal content, spam etc.

Hashes are stored in the `file_scanner_hashes` table. It is a superset of the [CSAM-buster spec](https://gitgud.io/lukesmithxyz/csam-buster/-/blob/master/spec/spec.md).

## API

### `POST /check`

Input fields:

- `input`: the file

Response when there is no match:

```json
{"match": false}
```

Response when there is a match:

```json
{
  "match": true,
  "similarity": 0.97,
  "id": 1,
  "sha256": "<hex>",
  "type": "image",
  "content_type": "csam",
  "comment": null,
  "algorithm": "pdq",
  "hash": "<hex>",
  "quality": 100,
  "originator": "miniboard",
  "upvotes": 0,
  "downvotes": 0,
  "timestamp": 1700000000
}
```

### `POST /ban`

Input fields:

- `input`: the file
- `content_type`: enum
- `comment`: optional free text, max 256 characters

Response:

```json
{"success": true, "sha256": "<hex>", "hashes": 2, "inserted": 2}
```

## Environment variables

| Name | Description |
|---|---|
| `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` | MariaDB connection |
| `ORIGINATOR` | stored as the originator of new hashes (default `localhost`) |
| `PDQ_THRESHOLD` | a PDQ match requires a similarity strictly greater than this, 0.0 - 1.0 (default `0.878`, i.e. a hamming distance of 31 bits or less as recommended by the PDQ spec) |

## Tests

```sh
python -m unittest
```
