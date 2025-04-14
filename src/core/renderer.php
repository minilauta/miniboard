<?php

namespace minichan\core;

interface Renderer
{
    public function render(string $filename, array $vars = []): bool|string;
}

class HtmlRenderer implements Renderer
{
    private array $vars;

    public function __construct(array $vars = [])
    {
        $this->vars = $vars;
    }

    public function render(string $filename, array $vars = []): bool|string
    {
        ob_start();
        if (!empty($this->vars)) {
            extract($this->vars);
        }
        if (!empty($vars)) {
            extract($vars);
        }
        include $filename;
        return ob_get_clean();
    }
}
