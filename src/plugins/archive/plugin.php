<?php

namespace minichan\plugins;

use Closure;
use minichan\core;

require_once __ROOT__ . '/core/plugin.php';
require_once __ROOT__ . '/core/renderer.php';
require_once __ROOT__ . '/core/router.php';
require_once __ROOT__ . '/common/config.php';
require_once __ROOT__ . '/common/version.php';
require_once __ROOT__ . '/common/exception.php';
require_once __ROOT__ . '/common/database.php';
require_once __ROOT__ . '/common/funcs_common.php';
require_once __ROOT__ . '/modules/board/funcs_board.php';
require_once __DIR__ . '/funcs_archive.php';

class ArchivePlugin implements core\Plugin
{
	private core\HtmlRenderer $renderer;

	public function __construct()
	{
		$this->renderer = new core\HtmlRenderer();
	}

	public function init(core\App &$app): void
	{
		$this->renderer->set_var('app', $app);

		global $translator;
		if (isset($translator)) {
			$translator->add_translation_dir(__DIR__ . '/lang');
		}

		$app->get_router()->prepend_route(HTTP_GET, '/:board_id/:thread_id/archive', function ($vars) {
			$this->handle_archive($vars);
		});
	}

	public function register_hooks(core\App &$app): void
	{
		$mb_version = MB_VERSION;
		$app->add_hook('common.scripts', function () use ($mb_version) {
			echo "<script src='/plugins/archive/index.js?mb_version=$mb_version'></script>";
		});
	}

	public function get_name(): string
	{
		return 'archive';
	}

	public function get_dependencies(): array
	{
		return [];
	}

	private function handle_archive(array $vars): void
	{
		if (!class_exists('\ZipArchive')) {
			throw new \AppException('archive', 'route', 'the zip php extension is not installed', SC_INTERNAL_ERROR);
		}

		// get board config
		$board_cfg = funcs_common_get_board_cfg($vars['board_id']);

		// check board access
		$user_role = funcs_common_get_role();
		if (!funcs_board_check_access($board_cfg, $user_role)) {
			throw new \AppException('archive', 'route', 'access denied', SC_UNAUTHORIZED);
		}

		// get thread
		$thread = select_post($board_cfg['id'], $vars['thread_id']);
		if ($thread == null) {
			throw new \AppException('archive', 'route', "thread with ID /{$board_cfg['id']}/{$vars['thread_id']} not found", SC_NOT_FOUND);
		} else if ($thread['parent_id'] != null) {
			throw new \AppException('archive', 'route', 'not a valid thread', SC_NOT_FOUND);
		}

		// get replies
		$replies = select_posts('', $user_role, $thread['board_id'], $thread['post_id'], false, 0, 9001);
		$thread['replies'] = $replies !== false ? $replies : [];

		// refuse to archive threads whose files exceed the size limit
		$total_size = (int) ($thread['file_size'] ?? 0);
		foreach ($thread['replies'] as $reply) {
			$total_size += (int) ($reply['file_size'] ?? 0);
		}
		if ($total_size > MB_PLUGIN_ARCHIVE_MAX_BYTES) {
			throw new \AppException('archive', 'route', 'thread archive too large', SC_BAD_REQUEST);
		}

		// render the thread
		$html = $this->renderer->render(__DIR__ . '/templates/archive.phtml', [
			'board' => $board_cfg,
			'thread' => $thread
		]);
		$json = funcs_archive_json($board_cfg, $thread);

		// create the ZIP-archive
		$zip_path = tempnam(sys_get_temp_dir(), 'mbzip');
		if ($zip_path === false) {
			throw new \AppException('archive', 'route', 'failed to create archive', SC_INTERNAL_ERROR);
		}

		try {
			$zip = new \ZipArchive();
			if ($zip->open($zip_path, \ZipArchive::OVERWRITE) !== true) {
				throw new \AppException('archive', 'route', 'failed to create archive', SC_INTERNAL_ERROR);
			}

			funcs_archive_add_public($zip);
			funcs_archive_add_src($zip, [$thread]);
			funcs_archive_add_src($zip, $thread['replies']);
			$zip->addFromString('thread.html', $html);
			$zip->addFromString('thread.json', $json);

			if ($zip->close() !== true) {
				throw new \AppException('archive', 'route', 'failed to create archive', SC_INTERNAL_ERROR);
			}

			header('Content-Type: application/zip');
			header('Content-Disposition: attachment; filename="' . MB_SITE_NAME . '_ARCHIVE_' . $board_cfg['id'] . '_' . $thread['post_id'] . '.zip"');
			header('Content-Length: ' . filesize($zip_path));
			readfile($zip_path);
		} finally {
			@unlink($zip_path);
		}
	}
}

return new ArchivePlugin();
