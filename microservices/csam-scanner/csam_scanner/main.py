import os
import time
import hashlib
import mysql.connector
import numpy as np
import cv2
import pdqhash
from fastapi import FastAPI, File, UploadFile, HTTPException
import utils

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

    input_bytes = input.read()

    # calculate pdq hash vector and quality
    hash_vector, quality = utils.compute_pdq_hash(input_bytes)

    # calculate sha256
    hash_sha256 = hashlib.sha256(input_bytes)

    # store the data to database
    db = mysql.connector.connect(
        user=os.getenv('CS_DB_USER'),
        password=os.getenv('CS_DB_PASS'),
        host=os.getenv('CS_DB_HOST'),
        database=os.getenv('CS_DB_NAME')
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
    """, (hash_sha256.digest(), hash_vector.tobytes(), quality, os.getenv('CS_ORIGINATOR'), int(time.time())))
    db.commit()
    cr.close()
    db.close()

    return {'success': True}
