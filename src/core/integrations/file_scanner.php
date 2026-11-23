<?php

namespace minichan\core\integrations;

/**
 * Category of banned content, mirrors the `file_scanner_hashes.content_type` ENUM column.
 */
enum BannedContentType: string
{
	case Csam = 'csam';
	case Illegal = 'illegal';
	case Gore = 'gore';
	case Spam = 'spam';
	case Malware = 'malware';
	case Other = 'other';
}

/**
 * Media type of a banned file, mirrors the `file_scanner_hashes.type` column values set by the file-scanner microservice.
 */
enum BannedMediaType: string
{
	case Image = 'image';
	case Video = 'video';
	case Audio = 'audio';
	case File = 'file';
}

class FileScannerException extends \Exception {}

/**
 * Banned hash that matched a checked file.
 */
final class FileScannerMatch
{
	public function __construct(
		public readonly int $id,
		public readonly BannedContentType $content_type,
		public readonly string $algorithm,
		public readonly string $sha256,
		public readonly float $similarity,
	) {}
}

/**
 * Client for the file-scanner microservice, which bans files by their SHA256 and perceptual (PDQ) hashes.
 */
class FileScannerClient
{
	private string $base_url;
	private int $timeout;
	private int $connect_timeout;

	/**
	 * @param string $base_url service URL, e.g. http://file-scanner:8000
	 * @param int $timeout max seconds per request, including the file upload
	 * @param int $connect_timeout max seconds to wait for a connection
	 */
	public function __construct(string $base_url, int $timeout = 10, int $connect_timeout = 2)
	{
		$this->base_url = rtrim($base_url, '/');
		$this->timeout = $timeout;
		$this->connect_timeout = $connect_timeout;
	}

	/**
	 * Checks a file against the banned hashes.
	 *
	 * @return FileScannerMatch|null the matching banned hash, null if the file is not banned
	 * @throws FileScannerException if the file is not readable or the request fails
	 */
	public function check(string $file_path): ?FileScannerMatch
	{
		$response = $this->post('/check', [
			'input' => $this->create_file($file_path),
		]);

		if (($response['match'] ?? false) !== true) {
			return null;
		}

		$content_type = BannedContentType::tryFrom($response['content_type'] ?? '');
		if (!isset($response['id'], $response['algorithm'], $response['sha256'], $response['similarity']) || $content_type === null) {
			throw new FileScannerException('invalid /check response: ' . json_encode($response));
		}

		return new FileScannerMatch(
			(int) $response['id'],
			$content_type,
			(string) $response['algorithm'],
			(string) $response['sha256'],
			(float) $response['similarity'],
		);
	}

	/**
	 * Bans a file by its hashes, hashes that are already banned are skipped.
	 *
	 * @param ?string $comment optional free text stored with the hashes
	 * @return int number of newly banned hashes
	 * @throws FileScannerException if the file is not readable or the request fails
	 */
	public function ban(string $file_path, BannedContentType $content_type, ?string $comment = null): int
	{
		$fields = [
			'input' => $this->create_file($file_path),
			'content_type' => $content_type->value,
		];
		if ($comment !== null && $comment !== '') {
			$fields['comment'] = $comment;
		}

		$response = $this->post('/ban', $fields);

		if (($response['success'] ?? false) !== true || !isset($response['inserted'])) {
			throw new FileScannerException('invalid /ban response: ' . json_encode($response));
		}

		return (int) $response['inserted'];
	}

	/**
	 * Sends a multipart POST request and returns the decoded JSON response.
	 *
	 * @throws FileScannerException on transport errors, non-2xx responses and invalid JSON
	 */
	protected function post(string $endpoint, array $fields): array
	{
		$curl = curl_init();
		curl_setopt_array($curl, [
			CURLOPT_URL => $this->base_url . $endpoint,
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => $fields,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_CONNECTTIMEOUT => $this->connect_timeout,
			CURLOPT_TIMEOUT => $this->timeout,
		]);
		$body = curl_exec($curl);
		$error = curl_error($curl);
		$status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
		curl_close($curl);

		if ($body === false) {
			throw new FileScannerException("POST {$endpoint} failed: {$error}");
		}

		if ($status < 200 || $status >= 300) {
			throw new FileScannerException("POST {$endpoint} failed with HTTP {$status}: {$body}");
		}

		$response = json_decode($body, true);
		if (!is_array($response)) {
			throw new FileScannerException("POST {$endpoint} returned invalid JSON: {$body}");
		}

		return $response;
	}

	/**
	 * @throws FileScannerException if the file is not readable
	 */
	private function create_file(string $file_path): \CURLFile
	{
		if (!is_file($file_path) || !is_readable($file_path)) {
			throw new FileScannerException("file '{$file_path}' is not readable");
		}

		$mime = mime_content_type($file_path);

		return new \CURLFile($file_path, $mime !== false ? $mime : 'application/octet-stream', basename($file_path));
	}
}
