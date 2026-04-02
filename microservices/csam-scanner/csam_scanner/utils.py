import io
import os
import numpy as np
import cv2
import pdqhash
from PIL import Image

# variables
TMP_DIR = os.path.join(os.path.dirname(__file__), '..', 'tmp')

# minimum PDQ quality score for a frame to be usable
PDQ_QUALITY_MIN = 90

# max frames to sample from a GIF
MAX_GIF_FRAMES = 10

# similarity threshold for deduplicating frames within a single GIF
FRAME_DEDUP_SIMILARITY = 0.98

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

def extract_gif_frames(file_bytes: bytes) -> list[np.ndarray]:
    """Extract evenly sampled frames from a GIF as RGB numpy arrays."""
    frames = []
    with Image.open(io.BytesIO(file_bytes)) as img:
        n_frames = getattr(img, 'n_frames', 1)
        if n_frames <= MAX_GIF_FRAMES:
            indices = range(n_frames)
        else:
            indices = [int(i * n_frames / MAX_GIF_FRAMES) for i in range(MAX_GIF_FRAMES)]
        for i in indices:
            img.seek(i)
            frames.append(np.array(img.convert('RGB')))
    return frames

def compute_pdq_hashes_gif(file_bytes: bytes) -> list[tuple[np.ndarray, int]]:
    """Compute deduplicated PDQ hashes for GIF frames above quality threshold."""
    frames = extract_gif_frames(file_bytes)
    results = []
    for frame in frames:
        hash_vector, quality = pdqhash.compute(frame)
        if quality < PDQ_QUALITY_MIN:
            continue
        hash_vector = hash_vector.astype(np.uint8)
        # skip frames that are near-identical to an already collected hash
        if any(compute_pdq_similarity(hash_vector, h) > FRAME_DEDUP_SIMILARITY for h, _ in results):
            continue
        results.append((hash_vector, quality))
    return results
