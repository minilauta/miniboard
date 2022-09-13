<?php

declare(strict_types=1);

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

  public function provide_clean_field_data() {
    return [
      ['<div>test</div>', '&lt;div&gt;test&lt;/div&gt;'],
      ['<span class="test">test</span>', '&lt;span class=&quot;test&quot;&gt;test&lt;/span&gt;'],
      ['<span class=\'test\'>test</span>', '&lt;span class=&#039;test&#039;&gt;test&lt;/span&gt;']
    ];
  }

  /**
   * @test
   * @dataProvider provide_clean_field_data
   */
  public function clean_field_escapes_html_entities(string $field, string $valid) {
    $result = clean_field($field);

    $this->assertEquals($valid, $result);
  }

  public function provide_human_filesize_data() {
    return [
      [512, '512B', '512.00B'],
      [2048, '2KB', '2.00KB'],
      [1024 * 1024, '1MB', '1.00MB'],
      [1024 * 1024 * 1024, '1GB', '1.00GB'],
    ];
  }

  /**
   * @test
   * @dataProvider provide_human_filesize_data
   */
  public function human_filesize_correct_conversions(int $bytes, string $size_0, string $size_2) {
    $result_0 = human_filesize($bytes, 0);
    $result_2 = human_filesize($bytes, 2);

    $this->assertEquals($size_0, $result_0);
    $this->assertEquals($size_2, $result_2);
  }

  /**
   * @test
   */
  public function generate_thumbnail_correct_results() {
    $result = generate_thumbnail(__DIR__ . '/../src/yotsuba.png', 'image/png', __DIR__ . '/../src/thumb_yotsuba.png', 250, 250);

    $this->assertNotNull($result);
    $this->assertEquals(364, $result['image_width']);
    $this->assertEquals(652, $result['image_height']);
    $this->assertEquals(139, $result['thumb_width']);
    $this->assertEquals(250, $result['thumb_height']);
  }

  /**
   * @test
   */
  public function test_truncate_message() {
    $message = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.';
    $truncated_message = truncate_message(message: $message, length: 10);
    print_r($truncated_message);
    $this->assertEquals(13, strlen($truncated_message));
  }
}
