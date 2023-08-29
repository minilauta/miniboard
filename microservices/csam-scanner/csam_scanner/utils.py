import os
import numpy as np
import cv2
import pdqhash

# variables
TMP_DIR = os.path.join(os.path.dirname(__file__), '..', 'tmp')

def compute_pdq_hash(file_bytes: bytes):
    # convert image to opencv format
    image = np.frombuffer(file_bytes, dtype=np.uint8)
    image = cv2.imdecode(image, cv2.IMREAD_COLOR)

    # set colorspace for pdqhash
    image = cv2.cvtColor(image, cv2.COLOR_BGR2RGB)
    
    # return (hash_vector, quality) tuple
    hash_vector, quality = pdqhash.compute(image)
    hash_vector = hash_vector.astype(np.uint8)
    return (hash_vector, quality)

def compute_pdq_similarity(hash_vec_a, hash_vec_b) -> float:
    same_bits = [a == b for a, b in zip(hash_vec_a, hash_vec_b)]
    return np.count_nonzero(same_bits) / 256.0
