import os
import logging
from typing import Annotated
import numpy as np
from fastapi import FastAPI, Form, HTTPException, UploadFile
import file_scanner.db as db
import file_scanner.hashing as hashing

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

app = FastAPI()

# a PDQ match requires a similarity strictly greater than this,
# the default matches at a hamming distance of 31 bits or less as recommended by the PDQ spec
PDQ_THRESHOLD = float(os.getenv('PDQ_THRESHOLD', '0.878'))

# stored as the originator of new hashes
ORIGINATOR = os.getenv('ORIGINATOR', 'localhost')

def read_sha256(input: UploadFile) -> bytes:
    input.file.seek(0)
    sha256 = hashing.compute_sha256(input.file)
    input.file.seek(0)
    return sha256

def read_pdq_hashes(input: UploadFile, mime_type: str) -> list[tuple[np.ndarray, int]]:
    """Compute the PDQ hashes of an image upload, an empty list if the file is not a usable image."""
    if not hashing.is_image(mime_type):
        return []

    try:
        input.file.seek(0)
        hashes = hashing.compute_pdq_hashes(input.file.read(), mime_type)
    except Exception:
        logger.exception('failed to compute PDQ hashes, name: %s', input.filename)
        return []

    logger.info('computed %d PDQ hashes, qualities: %s', len(hashes), [quality for _, quality in hashes])
    return hashes

def match_response(row: dict, similarity: float) -> dict:
    return {**db.serialize(row), 'match': True, 'similarity': similarity}

@app.post('/check')
def api_check(input: UploadFile):
    mime_type = input.content_type or ''
    sha256 = read_sha256(input)

    logger.info('checking file, name: %s, size: %s, MIME: %s, SHA256: %s', input.filename, input.size, mime_type, sha256.hex())

    pdq_hashes = read_pdq_hashes(input, mime_type)

    with db.connect() as conn:
        # exact match by SHA256
        row = db.find_by_sha256(conn, sha256)
        if row is not None:
            logger.info('SHA256 match, id: %s, content type: %s', row['id'], row['content_type'])
            return match_response(row, 1.0)

        # perceptual match by PDQ
        if pdq_hashes:
            ids, packed_hashes = db.load_pdq_hashes(conn, hashing.PDQ_QUALITY_MIN)
            best = hashing.pdq_best_match([hash_vector for hash_vector, _ in pdq_hashes], packed_hashes)
            if best is not None and best[1] > PDQ_THRESHOLD:
                row = db.find_by_id(conn, ids[best[0]])
                if row is not None:
                    logger.info('PDQ match, id: %s, content type: %s, similarity: %s', row['id'], row['content_type'], best[1])
                    return match_response(row, best[1])

    return {'match': False}

@app.post('/ban')
def api_ban(input: UploadFile, content_type: Annotated[str, Form()], comment: Annotated[str | None, Form()] = None):
    if content_type not in db.CONTENT_TYPES:
        raise HTTPException(status_code=400, detail='unsupported content type')

    # optional free text, blank is stored as NULL
    comment = (comment or '').strip() or None
    if comment is not None and len(comment) > db.COMMENT_MAX_LEN:
        raise HTTPException(status_code=400, detail='comment is too long')

    mime_type = input.content_type or ''
    sha256 = read_sha256(input)

    logger.info('banning file, name: %s, size: %s, MIME: %s, SHA256: %s, content type: %s', input.filename, input.size, mime_type, sha256.hex(), content_type)

    # every file is banned by its SHA256, images additionally by their PDQ hash(es)
    hashes = [('sha256', sha256, 100)]
    hashes += [('pdq', hashing.pdq_pack(hash_vector), quality) for hash_vector, quality in read_pdq_hashes(input, mime_type)]

    with db.connect() as conn:
        inserted = db.insert_hashes(conn, sha256, hashing.media_type(mime_type), content_type, hashes, ORIGINATOR, comment)

    logger.info('stored %d of %d hashes to database', inserted, len(hashes))

    return {'success': True, 'sha256': sha256.hex(), 'hashes': len(hashes), 'inserted': inserted}
