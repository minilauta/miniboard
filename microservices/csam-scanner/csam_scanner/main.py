import os
import time
import logging
import hashlib
import mysql.connector
import numpy as np
import cv2
import pdqhash
from fastapi import FastAPI, File, UploadFile, HTTPException
import csam_scanner.utils as utils

app = FastAPI()

def check_mime(mime_type: str):
    if not mime_type.startswith('image/'):
        raise HTTPException(status_code=400, detail='unsupported MIME type')

@app.post('/check')
async def api_check(input: UploadFile):
    check_mime(input.content_type)
    
    return {'success': False}

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
        INSERT INTO csam_scanner (
            algorithm,
            type,
            sha256,
            hash,
            quality,
            originator,
            upvotes,
            downvotes,
            timestamp
        )
        VALUES (
            'pdq',
            'image',
            %s,
            %s,
            %s,
            %s,
            0,
            0,
            %s
        )
    """, (hash_sha256.digest(), hash_vector.tobytes(), quality, os.getenv('ORIGINATOR'), int(time.time())))
    db.commit()
    cr.close()
    db.close()

    logging.info('stored to database')

    return {'success': True}
