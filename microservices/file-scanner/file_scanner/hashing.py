import io
import hashlib
from typing import BinaryIO
import numpy as np
import cv2
import pdqhash
from PIL import Image

# PDQ hash length in bits
PDQ_BITS = 256

# minimum PDQ quality score for a hash to be usable, lower quality hashes come from
# near-featureless images and match unrelated images too easily (per the PDQ spec)
PDQ_QUALITY_MIN = 50

# minimum PDQ quality score for a GIF frame to be usable
PDQ_GIF_QUALITY_MIN = 90

# max frames to sample from a GIF
MAX_GIF_FRAMES = 10

# similarity threshold for deduplicating frames within a single GIF
FRAME_DEDUP_SIMILARITY = 0.98

# MIME types that can be perceptually hashed
MIME_TYPES_IMAGE = [
    'image/jpeg',
    'image/pjpeg',
    'image/png',
    'image/gif',
    'image/bmp',
    'image/x-ms-bmp',
    'image/tiff',
    'image/webp'
]

# number of set bits for every possible byte value
_POPCOUNT = np.array([bin(i).count('1') for i in range(256)], dtype=np.uint16)

def is_image(mime_type: str) -> bool:
    return (mime_type or '').lower() in MIME_TYPES_IMAGE

def media_type(mime_type: str) -> str:
    """Map a MIME type to the media type stored in the `type` column."""
    major = (mime_type or '').lower().split('/')[0]
    if major in ('image', 'video', 'audio'):
        return major
    return 'file'

def compute_sha256(file: BinaryIO, chunk_size: int = 1 << 20) -> bytes:
    """Compute the SHA256 digest of a file object without reading it all into memory."""
    digest = hashlib.sha256()
    for chunk in iter(lambda: file.read(chunk_size), b''):
        digest.update(chunk)
    return digest.digest()

def compute_pdq_hash(file_bytes: bytes):
    # convert image to opencv format
    image = np.frombuffer(file_bytes, dtype=np.uint8)
    image = cv2.imdecode(image, cv2.IMREAD_COLOR)
    if image is None:
        raise ValueError('failed to decode image')

    # set colorspace for pdqhash
    image = cv2.cvtColor(image, cv2.COLOR_BGR2RGB)

    # return (hash_vector, quality) tuple
    hash_vector, quality = pdqhash.compute(image)
    hash_vector = hash_vector.astype(np.uint8)
    return (hash_vector, quality)

def compute_pdq_similarity(hash_vec_a, hash_vec_b) -> float:
    same_bits = [a == b for a, b in zip(hash_vec_a, hash_vec_b)]
    return np.count_nonzero(same_bits) / float(PDQ_BITS)

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
        if quality < PDQ_GIF_QUALITY_MIN:
            continue
        hash_vector = hash_vector.astype(np.uint8)
        # skip frames that are near-identical to an already collected hash
        if any(compute_pdq_similarity(hash_vector, h) > FRAME_DEDUP_SIMILARITY for h, _ in results):
            continue
        results.append((hash_vector, quality))
    return results

def compute_pdq_hashes(file_bytes: bytes, mime_type: str) -> list[tuple[np.ndarray, int]]:
    """Compute the usable PDQ hash(es) of an image, GIFs yield one hash per distinct usable frame."""
    if mime_type.lower() == 'image/gif':
        return compute_pdq_hashes_gif(file_bytes)
    hash_vector, quality = compute_pdq_hash(file_bytes)
    return [(hash_vector, quality)] if quality >= PDQ_QUALITY_MIN else []

def pdq_pack(hash_vector: np.ndarray) -> bytes:
    """Pack a 256 element bit vector into the 32 byte form stored in the database."""
    return np.packbits(hash_vector).tobytes()

def pdq_best_match(hash_vectors: list[np.ndarray], packed_hashes: np.ndarray) -> tuple[int, float] | None:
    """
    Find the most similar stored hash for any of the given hash vectors.

    `packed_hashes` is an (N, 32) uint8 array of packed PDQ hashes.
    Returns (row index, similarity) of the best match, or None if there is nothing to compare.
    """
    if not hash_vectors or packed_hashes.size == 0:
        return None

    best_index, best_distance = -1, PDQ_BITS + 1
    for hash_vector in hash_vectors:
        packed = np.packbits(hash_vector)
        distances = _POPCOUNT[np.bitwise_xor(packed_hashes, packed)].sum(axis=1)
        index = int(np.argmin(distances))
        if distances[index] < best_distance:
            best_index, best_distance = index, int(distances[index])

    return (best_index, 1.0 - best_distance / float(PDQ_BITS))
