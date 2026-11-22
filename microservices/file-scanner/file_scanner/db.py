import os
import time
from contextlib import contextmanager
import mysql.connector
import numpy as np

TABLE = 'file_scanner_hashes'
CONTENT_TYPES = ('csam', 'illegal', 'gore', 'spam', 'malware', 'other')
PDQ_BYTES = 32
COMMENT_MAX_LEN = 256

COLUMNS = """
    id,
    sha256,
    type,
    content_type,
    comment,
    algorithm,
    hash,
    quality,
    originator,
    upvotes,
    downvotes,
    timestamp
"""

@contextmanager
def connect():
    db = mysql.connector.connect(
        user=os.getenv('DB_USER'),
        password=os.getenv('DB_PASS'),
        host=os.getenv('DB_HOST'),
        database=os.getenv('DB_NAME')
    )
    try:
        yield db
    finally:
        db.close()

def serialize(row: dict) -> dict:
    """Encode the binary columns of a row as hex for JSON responses."""
    row = dict(row)
    row['sha256'] = bytes(row['sha256']).hex()
    row['hash'] = bytes(row['hash']).hex()
    return row

def find_by_sha256(db, sha256: bytes) -> dict | None:
    """Find a hash row of a file with the given SHA256, preferring the exact `sha256` algorithm row."""
    cr = db.cursor(buffered=True, dictionary=True)
    cr.execute(f"""
        SELECT {COLUMNS}
        FROM {TABLE}
        WHERE sha256 = %s
        ORDER BY algorithm = 'sha256' DESC, id ASC
        LIMIT 1
    """, (sha256,))
    row = cr.fetchone()
    cr.close()
    return row

def find_by_id(db, id: int) -> dict | None:
    cr = db.cursor(buffered=True, dictionary=True)
    cr.execute(f"SELECT {COLUMNS} FROM {TABLE} WHERE id = %s", (id,))
    row = cr.fetchone()
    cr.close()
    return row

def load_pdq_hashes(db, min_quality: int) -> tuple[list[int], np.ndarray]:
    """Load the PDQ hashes of at least the given quality as a list of row ids and an (N, 32) uint8 array of packed hashes."""
    cr = db.cursor()
    cr.execute(f"SELECT id, hash FROM {TABLE} WHERE algorithm = 'pdq' AND quality >= %s", (min_quality,))
    ids, hashes = [], []
    for id, hash in cr:
        if len(hash) != PDQ_BYTES:
            continue
        ids.append(id)
        hashes.append(bytes(hash))
    cr.close()
    packed = np.frombuffer(b''.join(hashes), dtype=np.uint8).reshape(-1, PDQ_BYTES)
    return (ids, packed)

def insert_hashes(db, sha256: bytes, media_type: str, content_type: str, hashes: list[tuple[str, bytes, int]], originator: str, comment: str | None = None) -> int:
    """
    Store (algorithm, hash, quality) tuples of a file, hashes that are already stored are skipped.
    Returns the number of inserted rows.
    """
    timestamp = int(time.time())
    cr = db.cursor()
    cr.executemany(f"""
        INSERT IGNORE INTO {TABLE} (
            sha256,
            type,
            content_type,
            comment,
            algorithm,
            hash,
            quality,
            originator,
            upvotes,
            downvotes,
            timestamp
        )
        VALUES (%s, %s, %s, %s, %s, %s, %s, %s, 0, 0, %s)
    """, [
        (sha256, media_type, content_type, comment, algorithm, hash, quality, originator, timestamp)
        for algorithm, hash, quality in hashes
    ])
    inserted = cr.rowcount
    db.commit()
    cr.close()
    return inserted
