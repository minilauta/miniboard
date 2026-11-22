import unittest
import io
import hashlib
import os
import numpy as np
from PIL import Image
import file_scanner.hashing

TESTDATA1_FILEPATH = os.path.join(os.path.dirname(__file__), 'images', 'image1.jpg')
TESTDATA2_FILEPATH = os.path.join(os.path.dirname(__file__), 'images', 'yotsuba.png')
TESTDATA3_FILEPATH = os.path.join(os.path.dirname(__file__), 'images', 'yotsuba.jpg')

class test_hashing_pdq_funcs(unittest.TestCase):
    def test_compute_pdq_hash_1(self):
        # prepare
        test_file = open(TESTDATA1_FILEPATH, 'rb')
        test_data = test_file.read()
        test_file.close()
        
        # run test
        hash_vector, quality = file_scanner.hashing.compute_pdq_hash(test_data)

        # assert
        self.assertTrue(np.array_equal(hash_vector, np.array([
            0, 0, 0, 1, 0, 1, 1, 0, 0, 0, 1, 0, 1, 1, 1, 1, 0, 1, 1, 0, 0, 1,
            0, 0, 1, 1, 0, 0, 1, 0, 1, 0, 1, 1, 1, 1, 1, 0, 0, 1, 0, 1, 0, 1,
            0, 0, 0, 0, 0, 1, 0, 0, 0, 0, 1, 1, 1, 0, 1, 1, 1, 0, 0, 1, 1, 0,
            1, 1, 1, 1, 1, 1, 0, 0, 1, 0, 1, 1, 1, 1, 0, 0, 1, 1, 1, 0, 1, 0,
            1, 1, 0, 0, 0, 1, 1, 0, 1, 1, 0, 1, 1, 0, 1, 0, 0, 1, 0, 1, 0, 0,
            1, 0, 1, 0, 0, 0, 1, 1, 0, 0, 0, 1, 1, 1, 1, 1, 1, 0, 0, 0, 0, 0,
            0, 0, 0, 0, 1, 0, 0, 0, 0, 0, 1, 1, 1, 1, 0, 1, 1, 0, 0, 1, 0, 1,
            0, 1, 0, 0, 0, 0, 0, 1, 1, 0, 0, 1, 1, 0, 1, 0, 1, 0, 1, 1, 0, 1,
            1, 1, 0, 1, 0, 0, 1, 1, 0, 1, 1, 0, 1, 0, 0, 1, 1, 1, 1, 0, 0, 0,
            1, 0, 1, 0, 0, 1, 1, 0, 0, 1, 1, 0, 1, 0, 0, 0, 0, 1, 0, 1, 1, 1,
            0, 0, 0, 0, 1, 0, 0, 1, 1, 1, 0, 1, 0, 1, 1, 1, 0, 0, 1, 0, 0, 0,
            1, 1, 1, 1, 0, 0, 1, 0, 0, 0, 1, 1, 1, 1
        ])))
        self.assertEqual(quality, 100)

    def test_compute_pdq_hash_2(self):
        # prepare
        test_file = open(TESTDATA2_FILEPATH, 'rb')
        test_data = test_file.read()
        test_file.close()
        
        # run test
        hash_vector, quality = file_scanner.hashing.compute_pdq_hash(test_data)

        # assert
        self.assertTrue(np.array_equal(hash_vector, np.array([
            1, 0, 1, 0, 1, 0, 1, 1, 0, 1, 1, 0, 1, 1, 0, 0, 1, 1, 0, 0, 1, 1,
            1, 0, 0, 1, 1, 0, 0, 1, 0, 0, 0, 1, 0, 0, 1, 1, 1, 1, 0, 0, 1, 1,
            0, 1, 0, 0, 1, 1, 0, 1, 0, 0, 0, 0, 1, 1, 1, 0, 0, 1, 1, 0, 1, 0,
            1, 0, 0, 1, 0, 1, 1, 1, 0, 1, 0, 0, 1, 1, 0, 1, 1, 0, 0, 1, 1, 1,
            0, 0, 0, 1, 1, 0, 0, 1, 0, 0, 1, 0, 0, 0, 1, 0, 1, 1, 0, 0, 1, 1,
            0, 0, 0, 1, 0, 0, 1, 1, 0, 0, 1, 0, 1, 1, 1, 0, 0, 0, 0, 1, 1, 1,
            0, 1, 0, 1, 0, 0, 1, 1, 1, 0, 0, 1, 0, 0, 0, 1, 1, 0, 1, 0, 1, 1,
            0, 0, 0, 1, 1, 1, 1, 0, 0, 0, 1, 0, 0, 1, 0, 0, 0, 0, 0, 1, 1, 1,
            0, 0, 0, 1, 1, 0, 1, 0, 0, 0, 0, 1, 0, 1, 0, 1, 0, 1, 0, 1, 0, 0,
            1, 0, 1, 0, 1, 0, 0, 0, 1, 1, 1, 1, 1, 0, 1, 1, 1, 0, 0, 1, 0, 0,
            1, 0, 1, 0, 0, 0, 1, 0, 0, 1, 1, 1, 0, 1, 1, 1, 1, 0, 0, 1, 0, 1,
            1, 1, 0, 1, 1, 1, 1, 1, 1, 1, 0, 0, 1, 0
        ])))
        self.assertEqual(quality, 100)

    def test_compute_pdq_similarity_similar(self):
        # prepare
        test_file_1 = open(TESTDATA2_FILEPATH, 'rb')
        test_data_1 = test_file_1.read()
        test_file_1.close()
        test_file_2 = open(TESTDATA3_FILEPATH, 'rb')
        test_data_2 = test_file_2.read()
        test_file_2.close()
        
        # run test
        hash_vector_1, _ = file_scanner.hashing.compute_pdq_hash(test_data_1)
        hash_vector_2, _ = file_scanner.hashing.compute_pdq_hash(test_data_2)
        similarity = file_scanner.hashing.compute_pdq_similarity(hash_vector_1, hash_vector_2)

        # assert
        self.assertGreaterEqual(similarity, 0.96)

    def test_compute_pdq_similarity_different(self):
        # prepare
        test_file_1 = open(TESTDATA1_FILEPATH, 'rb')
        test_data_1 = test_file_1.read()
        test_file_1.close()
        test_file_2 = open(TESTDATA3_FILEPATH, 'rb')
        test_data_2 = test_file_2.read()
        test_file_2.close()
        
        # run test
        hash_vector_1, _ = file_scanner.hashing.compute_pdq_hash(test_data_1)
        hash_vector_2, _ = file_scanner.hashing.compute_pdq_hash(test_data_2)
        similarity = file_scanner.hashing.compute_pdq_similarity(hash_vector_1, hash_vector_2)

        # assert
        self.assertLessEqual(similarity, 0.44)

def read_file(path: str) -> bytes:
    with open(path, 'rb') as f:
        return f.read()

def make_gif(frames: list[bytes]) -> bytes:
    images = [Image.open(io.BytesIO(frame)).convert('RGB') for frame in frames]
    output = io.BytesIO()
    images[0].save(output, format='GIF', save_all=True, append_images=images[1:], duration=100)
    return output.getvalue()

class test_hashing_sha256_funcs(unittest.TestCase):
    def test_compute_sha256(self):
        test_data = read_file(TESTDATA1_FILEPATH)

        sha256 = file_scanner.hashing.compute_sha256(io.BytesIO(test_data))

        self.assertEqual(sha256, hashlib.sha256(test_data).digest())

    def test_compute_sha256_chunked(self):
        test_data = read_file(TESTDATA2_FILEPATH)

        sha256 = file_scanner.hashing.compute_sha256(io.BytesIO(test_data), chunk_size=1000)

        self.assertEqual(sha256, hashlib.sha256(test_data).digest())

class test_hashing_mime_funcs(unittest.TestCase):
    def test_is_image(self):
        self.assertTrue(file_scanner.hashing.is_image('image/png'))
        self.assertTrue(file_scanner.hashing.is_image('IMAGE/JPEG'))
        self.assertFalse(file_scanner.hashing.is_image('video/webm'))
        self.assertFalse(file_scanner.hashing.is_image(''))
        self.assertFalse(file_scanner.hashing.is_image(None))

    def test_media_type(self):
        self.assertEqual(file_scanner.hashing.media_type('image/png'), 'image')
        self.assertEqual(file_scanner.hashing.media_type('video/webm'), 'video')
        self.assertEqual(file_scanner.hashing.media_type('audio/mpeg'), 'audio')
        self.assertEqual(file_scanner.hashing.media_type('application/pdf'), 'file')
        self.assertEqual(file_scanner.hashing.media_type(''), 'file')

class test_hashing_pdq_matching(unittest.TestCase):
    def test_compute_pdq_hash_invalid(self):
        with self.assertRaises(ValueError):
            file_scanner.hashing.compute_pdq_hash(b'not an image')

    def test_compute_pdq_hashes_still(self):
        hashes = file_scanner.hashing.compute_pdq_hashes(read_file(TESTDATA2_FILEPATH), 'image/png')

        self.assertEqual(len(hashes), 1)

    def test_compute_pdq_hashes_featureless(self):
        output = io.BytesIO()
        Image.new('RGB', (256, 256), (128, 128, 128)).save(output, format='PNG')

        _, quality = file_scanner.hashing.compute_pdq_hash(output.getvalue())
        hashes = file_scanner.hashing.compute_pdq_hashes(output.getvalue(), 'image/png')

        self.assertLess(quality, file_scanner.hashing.PDQ_QUALITY_MIN)
        self.assertEqual(hashes, [])

    def test_compute_pdq_hashes_gif_dedup(self):
        frame = read_file(TESTDATA2_FILEPATH)
        gif = make_gif([frame, frame, frame])

        hashes = file_scanner.hashing.compute_pdq_hashes(gif, 'image/gif')

        self.assertEqual(len(hashes), 1)

    def test_compute_pdq_hashes_gif_distinct(self):
        gif = make_gif([read_file(TESTDATA1_FILEPATH), read_file(TESTDATA2_FILEPATH)])

        hashes = file_scanner.hashing.compute_pdq_hashes(gif, 'image/gif')

        self.assertEqual(len(hashes), 2)

    def test_pdq_pack(self):
        hash_vector, _ = file_scanner.hashing.compute_pdq_hash(read_file(TESTDATA1_FILEPATH))

        packed = file_scanner.hashing.pdq_pack(hash_vector)

        self.assertEqual(len(packed), 32)
        self.assertTrue(np.array_equal(np.unpackbits(np.frombuffer(packed, dtype=np.uint8)), hash_vector))

    def test_pdq_best_match(self):
        hash_1, _ = file_scanner.hashing.compute_pdq_hash(read_file(TESTDATA1_FILEPATH))
        hash_2, _ = file_scanner.hashing.compute_pdq_hash(read_file(TESTDATA2_FILEPATH))
        hash_3, _ = file_scanner.hashing.compute_pdq_hash(read_file(TESTDATA3_FILEPATH))
        stored = np.array([np.packbits(hash_1), np.packbits(hash_2)], dtype=np.uint8)

        index, similarity = file_scanner.hashing.pdq_best_match([hash_3], stored)

        self.assertEqual(index, 1)
        self.assertAlmostEqual(similarity, file_scanner.hashing.compute_pdq_similarity(hash_3, hash_2))

    def test_pdq_best_match_multiple_queries(self):
        hash_1, _ = file_scanner.hashing.compute_pdq_hash(read_file(TESTDATA1_FILEPATH))
        hash_2, _ = file_scanner.hashing.compute_pdq_hash(read_file(TESTDATA2_FILEPATH))
        stored = np.array([np.packbits(hash_2)], dtype=np.uint8)

        index, similarity = file_scanner.hashing.pdq_best_match([hash_1, hash_2], stored)

        self.assertEqual(index, 0)
        self.assertEqual(similarity, 1.0)

    def test_pdq_best_match_empty(self):
        hash_1, _ = file_scanner.hashing.compute_pdq_hash(read_file(TESTDATA1_FILEPATH))

        self.assertIsNone(file_scanner.hashing.pdq_best_match([hash_1], np.empty((0, 32), dtype=np.uint8)))
        self.assertIsNone(file_scanner.hashing.pdq_best_match([], np.array([np.packbits(hash_1)], dtype=np.uint8)))
