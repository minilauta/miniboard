import os
import time
import logging
import base64
import hashlib
import mysql.connector
import numpy as np
from fastapi import FastAPI, File, UploadFile, HTTPException
import csam_scanner.utils as utils

app = FastAPI()

MIME_TYPES_IMAGE = [
    'image/jpeg',
    'image/pjpeg',
    'image/png',
#    'image/gif',
    'image/bmp',
    'image/x-ms-bmp',
    'image/tiff',
    'image/webp'
]

def check_mime(mime_type: str):
    if mime_type.lower() not in MIME_TYPES_IMAGE:
        raise HTTPException(status_code=400, detail='unsupported MIME type')

@app.post('/check')
async def api_check(input: UploadFile):
    check_mime(input.content_type)

    input_bytes = await input.read()

    # calculate sha256
    hash_sha256 = hashlib.sha256(input_bytes)

    logging.info('checking file, name: %s, size: %s SHA256: %s', input.filename, input.size, hash_sha256)

    # calculate pdq hash vector and quality
    hash_vector, quality = utils.compute_pdq_hash(input_bytes)

    logging.info('computed PDQ hash, quality: %s', quality)

    # select whole database into memory in chunks
    db = mysql.connector.connect(
        user=os.getenv('DB_USER'),
        password=os.getenv('DB_PASS'),
        host=os.getenv('DB_HOST'),
        database=os.getenv('DB_NAME')
    )
    cr = db.cursor(buffered=True, dictionary=True)
    cr.execute("""
        SELECT
            id,
            sha256,
            type,
            algorithm,
            hash,
            quality,
            originator,
            upvotes,
            downvotes,
            timestamp
        FROM csam_scanner
        WHERE algorithm = 'pdq'
    """)

    # check similarity against each row
    # TODO: this is very slow and bad, idgaf right now
    tolerance = float(os.getenv('TOLERANCE'))
    result = {'match': False}
    for row in cr:
        # match found if similarity exceeds tolerance
        hash_vector_b = np.unpackbits(np.frombuffer(row['hash'], dtype=np.uint8))
        similarity = utils.compute_pdq_similarity(hash_vector, hash_vector_b)
        if similarity > tolerance:
            # encode binary and blob as base64
            row['sha256'] = base64.b64encode(row['sha256'])
            row['hash'] = base64.b64encode(row['hash'])

            # set result
            row['match'] = True
            row['similarity'] = similarity
            result = row
            break
    cr.close()
    db.close()

    # TODO: hamming distance in MySQL is difficult with > 64 bit hashes
    # # fuzzy search matches from database
    # db = mysql.connector.connect(
    #     user=os.getenv('DB_USER'),
    #     password=os.getenv('DB_PASS'),
    #     host=os.getenv('DB_HOST'),
    #     database=os.getenv('DB_NAME')
    # )
    # cr = db.cursor(dictionary=True)
    # cr.execute("""
    #     SELECT
    #         id,
    #         BIT_COUNT(hash ^ %s) AS hd,
    #         sha256,
    #         type,
    #         algorithm,
    #         hash,
    #         quality,
    #         originator,
    #         upvotes,
    #         downvotes,
    #         timestamp
    #     FROM csam_scanner
    #     HAVING hd < %s
    #     WHERE algorithm = 'pdq'
    # """, (np.packbits(hash_vector).tobytes(), int(os.getenv('HD_TOLERANCE'))))
    # results = []
    # for row in cr:
    #     # encode binary and blob as base64
    #     row['sha256'] = base64.b64encode(row['sha256'])
    #     row['hash'] = base64.b64encode(row['hash'])

    #     # append to results list
    #     row['match'] = True
    #     results.append(row)
    # cr.close()
    # db.close()

    return result

@app.post('/cp')
async def api_cp(input: UploadFile):
    check_mime(input.content_type)

    input_bytes = await input.read()

    # calculate sha256
    hash_sha256 = hashlib.sha256(input_bytes)

    logging.info('received file, name: %s, size: %s SHA256: %s', input.filename, input.size, hash_sha256)

    # calculate pdq hash vector and quality
    hash_vector, quality = utils.compute_pdq_hash(input_bytes)

    logging.info('computed PDQ hash, quality: %s', quality)

    # store the data to database
    db = mysql.connector.connect(
        user=os.getenv('DB_USER'),
        password=os.getenv('DB_PASS'),
        host=os.getenv('DB_HOST'),
        database=os.getenv('DB_NAME')
    )
    cr = db.cursor()
    cr.execute("""
        INSERT IGNORE INTO csam_scanner (
            sha256,
            type,
            algorithm,
            hash,
            quality,
            originator,
            upvotes,
            downvotes,
            timestamp
        )
        VALUES (
            %s,
            'image',
            'pdq',
            %s,
            %s,
            %s,
            0,
            0,
            %s
        )
    """, (hash_sha256.digest(), np.packbits(hash_vector).tobytes(), quality, os.getenv('ORIGINATOR'), int(time.time())))
    db.commit()
    cr.close()
    db.close()

    logging.info('stored to database')

    return {'success': True}
