<?php

declare(strict_types=1);

namespace ABP\Notifications;

final class TemplateRenderer
{
    public function render(string $template, array $values): string
    {
        $safe = array();
        foreach ($values as $key => $value) {
            $safe['{{' . sanitize_key((string) $key) . '}}'] = esc_html((string) $value);
        }
        return wp_kses_post(strtr($template, $safe));
    }
}
