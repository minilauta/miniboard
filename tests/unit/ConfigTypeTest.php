<?php

use PHPUnit\Framework\TestCase;
use minichan\core\ConfigType;

require_once __ROOT__ . '/core/config_schema.php';

class ConfigTypeTest extends TestCase
{
	public function roundtrip_provider(): array
	{
		return [
			'bool true' => [ConfigType::Bool, true, '1'],
			'bool false' => [ConfigType::Bool, false, '0'],
			'int' => [ConfigType::Int, 432000, '432000'],
			'int negative' => [ConfigType::Int, -5, '-5'],
			'int zero' => [ConfigType::Int, 0, '0'],
			'float' => [ConfigType::Float, 1.5, '1.5'],
			'float zero fraction' => [ConfigType::Float, 2.0, '2.0'],
			'string' => [ConfigType::String, 'Miniboard', 'Miniboard'],
			'string empty' => [ConfigType::String, '', ''],
			'string unicode' => [ConfigType::String, '(┛ಠ_ಠ)┛彡┻━┻', '(┛ಠ_ಠ)┛彡┻━┻'],
			'string html' => [ConfigType::String, "\n  <ol>\n    <li>a</li>\n  </ol>\n", "\n  <ol>\n    <li>a</li>\n  </ol>\n"],
			'json list' => [ConfigType::Json, ['futaba', 'yotsuba'], '["futaba","yotsuba"]'],
			'json empty' => [ConfigType::Json, [], '[]'],
			'json int keys' => [ConfigType::Json, [1 => 'a', 2 => 'b'], '{"1":"a","2":"b"}'],
			'json nested int keys' => [ConfigType::Json, [404 => ['mini1.jpg', 'mini2.png']], '{"404":["mini1.jpg","mini2.png"]}'],
			'json slashes' => [ConfigType::Json, ['image/jpeg' => ['jpg']], '{"image/jpeg":["jpg"]}'],
			'json unicode' => [ConfigType::Json, ['desc' => '┏━┓'], '{"desc":"┏━┓"}'],
		];
	}

	/**
	 * @dataProvider roundtrip_provider
	 */
	public function test_roundtrip(ConfigType $type, mixed $value, string $encoded): void
	{
		$this->assertSame($encoded, $type->encode($value));
		$this->assertSame($value, $type->decode($encoded));
	}

	public function test_null_is_stored_as_null(): void
	{
		foreach (ConfigType::cases() as $type) {
			$this->assertNull($type->encode(null));
			$this->assertNull($type->decode(null));
		}
	}

	public function test_float_accepts_int(): void
	{
		$this->assertTrue(ConfigType::Float->accepts(3));
		$this->assertSame(3.0, ConfigType::Float->decode(ConfigType::Float->encode(3)));
	}

	public function test_accepts_only_its_type(): void
	{
		$this->assertFalse(ConfigType::Bool->accepts(1));
		$this->assertFalse(ConfigType::Int->accepts('1'));
		$this->assertFalse(ConfigType::Int->accepts(1.0));
		$this->assertFalse(ConfigType::String->accepts(1));
		$this->assertFalse(ConfigType::Json->accepts('[]'));
	}

	public function test_encode_wrong_type_throws(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		ConfigType::Int->encode('15');
	}

	public function invalid_provider(): array
	{
		return [
			'bool' => [ConfigType::Bool, 'true'],
			'int' => [ConfigType::Int, '1.5'],
			'int text' => [ConfigType::Int, 'abc'],
			'float' => [ConfigType::Float, 'abc'],
			'json syntax' => [ConfigType::Json, '{'],
			'json scalar' => [ConfigType::Json, '"text"'],
		];
	}

	/**
	 * @dataProvider invalid_provider
	 */
	public function test_decode_invalid_throws(ConfigType $type, string $raw): void
	{
		$this->expectException(\UnexpectedValueException::class);
		$type->decode($raw);
	}
}
