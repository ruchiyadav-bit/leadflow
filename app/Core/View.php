<?php
declare(strict_types=1);

namespace LeadFlow\Core;

final class View
{
    public static function render(string $template, array $data = []): Response
    {
        $path = Application::instance()->basePath('resources/views/' . str_replace('.', '/', $template) . '.php');
        if (!file_exists($path)) {
            throw new \RuntimeException("View not found: $template");
        }
        extract($data, EXTR_SKIP);
        ob_start();
        $__contentTemplate = $path;
        require $path;
        $content = ob_get_clean();

        $layoutPath = Application::instance()->basePath('resources/views/layouts/app.php');
        if (file_exists($layoutPath) && !str_starts_with($template, 'layouts.') && !str_starts_with($template, 'auth.')) {
            ob_start();
            require $layoutPath;
            $content = ob_get_clean();
        }
        return Response::html($content);
    }

    public static function e(mixed $v): string
    {
        return htmlspecialchars((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
