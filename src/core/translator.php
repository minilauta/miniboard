<?php

namespace minichan\core {
	class Translator
	{
		private string $lang;
		private string $default_lang;
		private string $lang_dir;
		private array $translations;

		public function __construct(string $lang_dir, string $default_lang = 'en')
		{
			$this->lang_dir = $lang_dir;
			$this->default_lang = $default_lang;
			$this->lang = $this->resolve_lang('miniboard/lang');
			$this->translations = $this->load_translations($this->lang);
		}

		public function t(string $key, array $params = []): string
		{
			$text = $this->translations[$key] ?? $key;

			foreach ($params as $name => $value) {
				$text = str_replace(':' . $name, $value, $text);
			}

			return $text;
		}

		private function resolve_lang(string $cookie_name): string
		{
			if (isset($_COOKIE[$cookie_name])) {
				$lang = preg_replace('/[^a-z_]/', '', strtolower($_COOKIE[$cookie_name]));
				if ($lang !== '' && file_exists($this->lang_dir . '/' . $lang . '.php')) {
					return $lang;
				}
			}

			return $this->default_lang;
		}

		private function load_translations(string $lang): array
		{
			$file = $this->lang_dir . '/' . $lang . '.php';
			if (!file_exists($file)) {
				return [];
			}

			$translations = require $file;
			return is_array($translations) ? $translations : [];
		}
	}
}

namespace {
	function __($key, $params = [])
	{
		global $translator;
		return $translator->t($key, $params);
	}
}
