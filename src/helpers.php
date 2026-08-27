<?php

use App\Config;
use App\Sanitizer;

/** Escape for HTML text and attribute context. */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** The app's own UI text, in the current locale — see App\Language. */
function t(string $key, mixed ...$args): string
{
    return \App\Language::t($key, ...$args);
}

/** Binary-plural version of t() — picks $singular or $plural by $count. */
function tn(int $count, string $singular, string $plural, mixed ...$args): string
{
    return \App\Language::tn($count, $singular, $plural, ...$args);
}

/**
 * The path the site is served from, without a trailing slash. 'auto' (default)
 * derives it from the request; set base_path explicitly in config to override.
 */
function base_path(): string
{
    static $base = null;

    if ($base !== null) {
        return $base;
    }

    $configured = Config::get('base_path', 'auto');

    if ($configured !== 'auto' && $configured !== null) {
        return $base = rtrim((string) $configured, '/');
    }

    // dirname of the front controller: "/worldbuilder/index.php" -> "/worldbuilder".
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $directory = rtrim(str_replace('\\', '/', dirname($script)), '/');

    return $base = ($directory === '' || $directory === '.') ? '' : $directory;
}

/** Build an application URL from a path: url('/c/characters'). */
function url(string $path = '/'): string
{
    $base = base_path();

    if ($path === '' || $path === '/') {
        return $base === '' ? '/' : $base . '/';
    }

    return $base . '/' . ltrim($path, '/');
}

/** URL for a file under public/, e.g. asset('assets/css/app.css'). */
function asset(string $path): string
{
    return url($path);
}

function redirect(string $path): never
{
    header('Location: ' . (str_starts_with($path, 'http') ? $path : url($path)));
    exit;
}

function slugify(string $value, string $fallback = 'item'): string
{
    $value = trim($value);

    // Transliterate accented Latin before stripping non-ASCII characters.
    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    if ($ascii !== false) {
        $value = $ascii;
    }

    $value = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $value) ?? '');
    $value = trim($value, '-');

    return $value === '' ? $fallback : substr($value, 0, 180);
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
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): void
{
    $posted = (string) ($_POST['_token'] ?? '');
    if ($posted === '' || !hash_equals(csrf_token(), $posted)) {
        // 403, not a custom code — Apache maps unknown statuses to a bare 500.
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        exit('Session expired — go back, reload the page and try again.');
    }
}

/** Queue a one-off message for the next page load. */
function flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'][] = ['message' => $message, 'type' => $type];
}

function take_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);

    return $flashes;
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

/**
 * Breadcrumb trail of parent archives, outermost first, with a trailing
 * separator so it drops in front of the archive's own crumb; '' if top-level.
 */
function parent_crumb(?array $category): string
{
    if ($category === null || ($category['parent_id'] ?? null) === null) {
        return '';
    }

    $crumbs = '';
    foreach ((new App\CategoryRepo())->ancestors($category) as $ancestor) {
        $crumbs .= '<a href="' . e(url('/c/' . $ancestor['slug'])) . '">'
            . e($ancestor['name']) . '</a> › ';
    }

    return $crumbs;
}

/** "3 minutes ago" style stamps for the entry list. */
function human_time(?string $timestamp): string
{
    if (!$timestamp) {
        return '';
    }

    $then = strtotime($timestamp);
    if ($then === false) {
        return '';
    }

    $diff = time() - $then;
    if ($diff < 60) {
        return t('just now');
    }
    if ($diff < 3600) {
        $m = (int) ($diff / 60);
        return tn($m, '%d minute ago', '%d minutes ago');
    }
    if ($diff < 86400) {
        $h = (int) ($diff / 3600);
        return tn($h, '%d hour ago', '%d hours ago');
    }
    if ($diff < 604800) {
        $d = (int) ($diff / 86400);
        return tn($d, '%d day ago', '%d days ago');
    }

    return (int) date('j', $then) . ' ' . month_abbr((int) date('n', $then)) . ' ' . date('Y', $then);
}

/**
 * PHP's date() always gives English month names regardless of locale — this
 * is the one place a short abbreviation is needed, so it gets its own small,
 * locale-aware table rather than pulling in the server's locale system.
 */
function month_abbr(int $month): string
{
    $en = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    $de = ['Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'];
    $table = \App\Language::locale() === 'de' ? $de : $en;

    return $table[max(1, min(12, $month)) - 1];
}

/** How many words a piece of rich text holds, tags and entities stripped. */
function word_count(string $html): int
{
    $text = trim(preg_replace('/\s+/u', ' ', Sanitizer::excerpt($html, PHP_INT_MAX)) ?? '');

    return $text === '' ? 0 : count(explode(' ', $text));
}

function json_response(mixed $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Render a view file from views/ with $data extracted into scope. */
function view(string $name, array $data = []): string
{
    $path = dirname(__DIR__) . '/views/' . $name . '.php';
    if (!is_file($path)) {
        throw new RuntimeException("View not found: {$name}");
    }

    extract($data, EXTR_SKIP);
    ob_start();
    require $path;

    return (string) ob_get_clean();
}

/**
 * Year + Month/Special-day + Day picker for a Date/Era field value. $name is
 * the POST field prefix ("fields[12]", or "fields[12][from]" for an Era);
 * posts as $name[year]/[slot]/[day]. Slot values round-trip through
 * App\Calendar::slotValue() ("m:3" / "i:0").
 *
 * `data-name-template` mirrors `name`, used by app.js to reindex this picker
 * when it sits inside a repeatable row (e.g. a Cycle holiday's reference date).
 */
function date_picker_html(string $name, string $domId, ?array $date, array $config): string
{
    $year = $date['year'] ?? '';
    $slotValue = $date === null ? '' : App\Calendar::slotValue($date);
    $day = $date['day'] ?? null;

    $dayCount = 0;
    if ($date !== null) {
        $dayCount = $date['kind'] === 'intercalary'
            ? (int) ($config['intercalary'][$date['ref']]['days'] ?? 0)
            : App\Calendar::monthLength((int) $date['year'], (int) $date['ref']);
    }

    ob_start();
    ?>
    <div class="date-picker" data-date-picker>
        <input class="input date-picker-year" type="text" inputmode="numeric"
               id="<?= e($domId) ?>" name="<?= e($name) ?>[year]"
               data-name-template="<?= e($name) ?>[year]"
               value="<?= e((string) $year) ?>" placeholder="Year" data-date-year>
        <select class="select date-picker-slot" name="<?= e($name) ?>[slot]"
                data-name-template="<?= e($name) ?>[slot]" data-date-slot>
            <option value="">— choose —</option>
            <optgroup label="Months">
                <?php foreach ($config['months'] as $i => $month): ?>
                    <option value="m:<?= $i + 1 ?>" <?= $slotValue === 'm:' . ($i + 1) ? 'selected' : '' ?>>
                        <?= e((string) $month['name']) ?>
                    </option>
                <?php endforeach; ?>
            </optgroup>
            <?php if ($config['intercalary'] !== []): ?>
                <optgroup label="Special days">
                    <?php foreach ($config['intercalary'] as $i => $block): ?>
                        <option value="i:<?= $i ?>" <?= $slotValue === 'i:' . $i ? 'selected' : '' ?>>
                            <?= e((string) $block['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </optgroup>
            <?php endif; ?>
        </select>
        <select class="select date-picker-day" name="<?= e($name) ?>[day]"
                data-name-template="<?= e($name) ?>[day]" data-date-day>
            <?php if ($dayCount === 0): ?>
                <option value="">—</option>
            <?php else: ?>
                <?php for ($d = 1; $d <= $dayCount; $d++): ?>
                    <option value="<?= $d ?>" <?= $day === $d ? 'selected' : '' ?>><?= $d ?></option>
                <?php endfor; ?>
            <?php endif; ?>
        </select>
    </div>
    <?php
    return (string) ob_get_clean();
}

/** Render a view inside the chrome in views/shell.php. */
function render(string $name, array $data = []): never
{
    $content = view($name, $data);
    echo view('shell', $data + [
        'content' => $content,
    ]);
    exit;
}

function abort(int $status, string $message = ''): never
{
    http_response_code($status);
    $titles = [404 => 'Not found', 403 => 'Not allowed', 400 => 'Bad request'];
    render('error', [
        'pageTitle' => $titles[$status] ?? 'Error',
        'status'    => $status,
        'message'   => $message ?: ($titles[$status] ?? 'Something went wrong.'),
    ]);
}
