import unittest
import os
import numpy as np
import csam_scanner.utils

TESTDATA1_FILEPATH = os.path.join(os.path.dirname(__file__), 'images', 'image1.jpg')
TESTDATA2_FILEPATH = os.path.join(os.path.dirname(__file__), 'images', 'yotsuba.png')
TESTDATA3_FILEPATH = os.path.join(os.path.dirname(__file__), 'images', 'yotsuba.jpg')

class test_utils_pdq_funcs(unittest.TestCase):
    def test_compute_pdq_hash_1(self):
        # prepare
        test_file = open(TESTDATA1_FILEPATH, 'rb')
        test_data = test_file.read()
        test_file.close()
        
        # run test
        hash_vector, quality = csam_scanner.utils.compute_pdq_hash(test_data)

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
        hash_vector, quality = csam_scanner.utils.compute_pdq_hash(test_data)

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
        hash_vector_1, _ = csam_scanner.utils.compute_pdq_hash(test_data_1)
        hash_vector_2, _ = csam_scanner.utils.compute_pdq_hash(test_data_2)
        similarity = csam_scanner.utils.compute_pdq_similarity(hash_vector_1, hash_vector_2)

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
        hash_vector_1, _ = csam_scanner.utils.compute_pdq_hash(test_data_1)
        hash_vector_2, _ = csam_scanner.utils.compute_pdq_hash(test_data_2)
        similarity = csam_scanner.utils.compute_pdq_similarity(hash_vector_1, hash_vector_2)

        # assert
        self.assertLessEqual(similarity, 0.44)
