<?php
/**
 * TerraWatch-1 — shared helpers (module M1): escaping, CSRF, redirects, layout.
 * AI-assisted code: written with Claude Code (Anthropic) and reviewed by the team.
 */
declare(strict_types=1);

/** Escape any value for HTML output. Use for every rendered value. */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/** Reject any state-changing request without a valid session token. */
function require_csrf(): void
{
    $sent = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(403);
        exit('Request rejected: the form expired or was not sent from this game. Please go back and try again.');
    }
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

/** Number formatting for display (1 decimal unless told otherwise). */
function num($value, int $decimals = 1): string
{
    return number_format((float) $value, $decimals, '.', ',');
}

/**
 * Render a page inside the shared layout.
 * $view is a file in src/pages; $data becomes local variables there.
 */
function render(string $view, array $data = [], string $title = 'TerraWatch-1'): void
{
    extract($data, EXTR_SKIP);
    ob_start();
    require APP_ROOT . '/src/pages/' . $view . '.php';
    $content = ob_get_clean();
    require APP_ROOT . '/src/pages/_layout.php';
}
