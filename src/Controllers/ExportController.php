<?php

namespace App\Controllers;

use App\CategoryRepo;
use App\ChapterRepo;
use App\Config;
use App\Database;
use App\Export\Builder;
use App\Export\DocxRenderer;
use App\Export\HtmlRenderer;
use App\Export\JsonBackup;
use App\Export\MarkdownRenderer;
use App\Export\Renderer;
use Throwable;

/**
 * Two exports, kept strictly apart: the wiki is every archive and no chapters,
 * the book is every chapter and no archives.
 */
final class ExportController
{
    /** @return array<string, array{label:string, hint:string}> */
    public static function formats(): array
    {
        return [
            'html' => [
                'label' => t('Web page (.html)'),
                'hint'  => t('One self-contained file with formatting and a linked table of contents. Opens in any browser, and prints to PDF from there.'),
            ],
            'docx' => [
                'label' => t('Word document (.docx)'),
                'hint'  => t('Opens in Word, LibreOffice or Google Docs, with real heading styles so the navigation pane and a generated table of contents work.'),
            ],
            'md' => [
                'label' => t('Markdown (.md)'),
                'hint'  => t('Plain text. Opens anywhere, pastes into most writing tools, and keeps well in version control.'),
            ],
        ];
    }

    public function index(): never
    {
        $db = Database::instance();
        $chapters = new ChapterRepo();

        render('export/index', [
            'pageTitle'    => t('Export'),
            'formats'      => self::formats(),
            'archiveCount' => (int) $db->value('SELECT COUNT(*) FROM categories'),
            'entryCount'   => (int) $db->value('SELECT COUNT(*) FROM entries'),
            'chapterCount' => $chapters->countAll(),
            'visibleCount' => $chapters->countVisible(),
        ]);
    }

    /** The full backup: everything, in a form that can be read back in. */
    public function backup(): never
    {
        $json = (new JsonBackup())->export();

        $name = slugify((string) Config::get('site_name', 'worldbuilder'), 'worldbuilder')
            . '-backup-' . date('Y-m-d-Hi') . '.json';

        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Content-Length: ' . strlen($json));
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');

        echo $json;
        exit;
    }

    /**
     * Step one of a restore: read the upload, work out exactly what it would do,
     * and show that before anything is written.
     */
    public function importPreview(): never
    {
        $upload = $_FILES['backup'] ?? null;

        if (!is_array($upload) || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            flash(t('Choose a backup file first.'), 'error');
            redirect('/export');
        }

        if ($upload['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'])) {
            flash(t('That upload did not arrive intact.'), 'error');
            redirect('/export');
        }

        $json = (string) file_get_contents($upload['tmp_name']);

        try {
            $tally = (new JsonBackup())->import($json, true);
        } catch (Throwable $e) {
            flash($e->getMessage(), 'error');
            redirect('/export');
        }

        // Held aside so the confirm step reads exactly what was previewed.
        $staged = tempnam(sys_get_temp_dir(), 'wbimp');
        if ($staged === false || file_put_contents($staged, $json) === false) {
            flash(t('Could not hold the file while you review it.'), 'error');
            redirect('/export');
        }

        $_SESSION['import'] = [
            'path'  => $staged,
            'name'  => (string) ($upload['name'] ?? 'backup.json'),
            'bytes' => strlen($json),
            'token' => bin2hex(random_bytes(16)),
        ];

        render('export/preview', [
            'pageTitle' => t('Restore'),
            'tally'     => $tally,
            'staged'    => $_SESSION['import'],
        ]);
    }

    /** Step two: actually write it. */
    public function importApply(): never
    {
        $staged = $_SESSION['import'] ?? null;

        if (!is_array($staged) || !hash_equals((string) $staged['token'], (string) ($_POST['token'] ?? ''))) {
            flash(t('That restore has expired. Upload the file again.'), 'error');
            redirect('/export');
        }

        // Only ever a file this session put there, inside the temp directory.
        $path = (string) $staged['path'];
        $real = realpath($path);
        $tempDir = realpath(sys_get_temp_dir());

        if ($real === false || $tempDir === false || !str_starts_with($real, $tempDir)) {
            unset($_SESSION['import']);
            flash(t('The held file has gone. Upload it again.'), 'error');
            redirect('/export');
        }

        try {
            $tally = (new JsonBackup())->import((string) file_get_contents($real), false);
        } catch (Throwable $e) {
            flash(t('The restore failed and nothing was changed: %s', $e->getMessage()), 'error');
            redirect('/export');
        } finally {
            @unlink($real);
            unset($_SESSION['import']);
        }

        $created = $tally['categories_created'] + $tally['layouts_created'] + $tally['fields_created']
            + $tally['entries_created'] + $tally['chapters_created'];
        $updated = $tally['categories_updated'] + $tally['layouts_updated'] + $tally['fields_updated']
            + $tally['entries_updated'] + $tally['chapters_updated'];

        flash(t('Restore complete: %d created, %d updated, %d connections added.',
            $created, $updated, $tally['connections_added']));
        redirect('/export');
    }

    public function wiki(): never
    {
        $document = (new Builder())->wiki([
            'connections'  => !isset($_GET['connections']) || $_GET['connections'] === '1',
            'empty_fields' => ($_GET['empty_fields'] ?? '0') === '1',
        ]);

        $this->download($document, 'wiki');
    }

    public function book(): never
    {
        $document = (new Builder())->book([
            'hidden' => ($_GET['hidden'] ?? '0') === '1',
            'notes'  => ($_GET['notes'] ?? '0') === '1',
        ]);

        $this->download($document, 'book');
    }

    private function download(\App\Export\Document $document, string $what): never
    {
        $renderer = self::renderer((string) ($_GET['format'] ?? 'html'));
        $body = $renderer->render($document);

        $name = slugify((string) Config::get('site_name', 'worldbuilder'), 'worldbuilder')
            . '-' . $what . '-' . date('Y-m-d') . '.' . $renderer->extension();

        // Forces a download rather than inline display.
        header('Content-Type: ' . $renderer->contentType());
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Content-Length: ' . strlen($body));
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');

        echo $body;
        exit;
    }

    private static function renderer(string $format): Renderer
    {
        return match ($format) {
            'docx'  => new DocxRenderer(),
            'md'    => new MarkdownRenderer(),
            default => new HtmlRenderer(),
        };
    }
}
