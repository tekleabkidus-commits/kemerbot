<?php

declare(strict_types=1);

namespace App\Services\Bot;

use App\Models\User;

/**
 * Reusable {token} renderer (spec §5.3): `{first_name}` today, extensible
 * later. Unknown tokens render as empty strings — never raw braces to users.
 */
final class TokenRenderer
{
    public function render(string $template, array $tokens): string
    {
        return preg_replace_callback(
            '/\{([a-z0-9_]+)\}/i',
            fn (array $m) => (string) ($tokens[strtolower($m[1])] ?? ''),
            $template,
        );
    }

    public function renderForUser(string $template, User $user): string
    {
        return $this->render($template, [
            'first_name' => $user->first_name,
        ]);
    }
}
