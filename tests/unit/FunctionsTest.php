<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require __DIR__ . '/../../public/functions.php';

final class FunctionsTest extends TestCase {
  /**
   * @test
   */
  public function validate_get_board_exists_success() {
    $result = validate_get(['board_id' => 'b']);

    $this->assertArrayHasKey('board_cfg', $result);
    $this->assertArrayHasKey('id', $result['board_cfg']);
  }

  /**
   * @test
   */
  public function validate_get_board_invalid_fails() {
    $result = validate_get(['board_id' => 'none']);

    $this->assertArrayHasKey('error', $result);
  }

  public function provide_validate_post_success_data() {
    return [
      [
        ['board_id' => 'b'], [
          'name' => 'Anonymous',
          'email' => 'noko',
          'subject' => 'test subject',
          'message' => 'Test postings!'
        ]
      ], [
        ['board_id' => 'a'], [
          'name' => null,
          'email' => null,
          'subject' => null,
          'message' => 'Test postings!'
        ]
      ], [
        ['board_id' => 'a'], [
          'name' => random_bytes(70),
          'email' => random_bytes(256),
          'subject' => random_bytes(60),
          'message' => random_bytes(8000)
        ]
      ]
    ];
  }

  /**
   * @test
   * @dataProvider provide_validate_post_success_data
   */
  public function validate_post_valid_args_success(array $args, array $params) {
    $result = validate_post($args, $params);

    $this->assertArrayHasKey('board_cfg', $result);
    $this->assertArrayHasKey('id', $result['board_cfg']);
  }

  public function provide_validate_post_failure_data() {
    return [
      [
        ['board_id' => 'none'], [
          'name' => 'Anonymous',
          'email' => 'noko',
          'subject' => 'test subject',
          'message' => 'Test postings!'
        ]
      ], [
        ['board_id' => 'a'], [
          'name' => random_bytes(76),
          'email' => 'noko',
          'subject' => 'test subject',
          'message' => 'Test postings!'
        ]
      ], [
        ['board_id' => 'b'], [
          'name' => 'Anonymous',
          'email' => random_bytes(340),
          'subject' => 'test subject',
          'message' => 'Test postings!'
        ]
      ], [
        ['board_id' => 'a'], [
          'name' => 'Anonymous',
          'email' => 'noko',
          'subject' => random_bytes(90),
          'message' => 'Test postings!'
        ]
      ], [
        ['board_id' => 'b'], [
          'name' => 'Anonymous',
          'email' => 'noko',
          'subject' => 'test subject',
          'message' => random_bytes(9001)
        ]
      ]
    ];
  }

  /**
   * @test
   * @dataProvider provide_validate_post_failure_data
   */
  public function validate_post_valid_args_fails(array $args, array $params) {
    $result = validate_post($args, $params);

    $this->assertArrayHasKey('error', $result);
  }
}
