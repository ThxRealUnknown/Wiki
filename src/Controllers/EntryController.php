<?php

namespace App\Controllers;

use App\CategoryRepo;
use App\ConnectionRepo;
use App\EntryRepo;
use App\LayoutRepo;
use App\ListState;
use RuntimeException;
use Throwable;

final class EntryController
{
    private CategoryRepo $categories;
    private LayoutRepo $layouts;
    private EntryRepo $entries;

    public function __construct()
    {
        $this->categories = new CategoryRepo();
        $this->layouts = new LayoutRepo();
        $this->entries = new EntryRepo();
    }

    /** The archive's entry list, with nothing selected yet. */
    public function index(string $categorySlug): never
    {
        $category = $this->category($categorySlug);
        $search = trim((string) ($_GET['q'] ?? ''));
        $sortFields = $this->layouts->sortableChoiceFields((int) $category['id']);
        $sort = $this->sortFor($category, $sortFields);
        $page = $this->listing($category, $search, $sort);

        render('entries/index', [
            'pageTitle'   => $category['name'],
            'category'    => $category,
            'entries'     => $page['items'],
            'paging'      => $page,
            'layouts'     => $this->layouts->forCategory((int) $category['id']),
            'sortFields'  => $sortFields,
            'search'      => $search,
            'sort'        => $sort,
            'activeEntry' => null,
        ]);
    }

    /** One page of the archive's entries; the page size is remembered per session. */
    private function listing(array $category, string $search, string $sort): array
    {
        $perPage = ListState::perPage();

        return $this->entries->pageForCategory(
            (int) $category['id'],
            $search,
            null,
            $sort,
            $perPage,
            max(1, (int) ($_GET['page'] ?? 1))
        );
    }

    /** POST /c/{cat}/e/{slug}/duplicate — copies the entry, or moves it, to the picked archive. */
    public function duplicate(string $categorySlug, string $entrySlug): never
    {
        $category = $this->category($categorySlug);
        $entry = $this->entry($category, $entrySlug);

        $mode = ($_POST['mode'] ?? 'copy') === 'move' ? 'move' : 'copy';
        $target = $this->categories->find((int) ($_POST['category_id'] ?? 0)) ?? $category;
        $crossCategory = (int) $target['id'] !== (int) $category['id'];

        if ($mode === 'move' && !$crossCategory) {
            flash(t('Pick a different archive to move "%s" to.', $entry['title']), 'error');
            redirect('/c/' . $category['slug'] . '/e/' . $entry['slug']);
        }

        try {
            // A layout the destination already has if its fields match exactly;
            // otherwise one built to match, so nothing about the entry is lost.
            $layoutId = $crossCategory
                ? $this->layoutFor((int) $target['id'], (int) $entry['layout_id'])
                : (int) $entry['layout_id'];

            if ($mode === 'move' && $crossCategory) {
                $this->entries->moveToCategory((int) $entry['id'], (int) $target['id'], $layoutId);
                $moved = $this->entries->find((int) $entry['id']);
                flash(t('"%s" moved to %s.', $moved['title'], $target['name']));
                redirect('/c/' . $target['slug'] . '/e/' . $moved['slug']);
            }

            $copyId = $crossCategory
                ? $this->entries->duplicate((int) $entry['id'], null, (int) $target['id'], $layoutId)
                : $this->entries->duplicate((int) $entry['id']);
        } catch (Throwable $e) {
            flash($mode === 'move'
                ? t('The entry could not be moved: %s', $e->getMessage())
                : t('The entry could not be duplicated: %s', $e->getMessage()), 'error');
            redirect('/c/' . $category['slug'] . '/e/' . $entry['slug']);
        }

        $copy = $this->entries->find($copyId);
        flash($crossCategory
            ? t('Copied to "%s" in %s. Rename it and it is yours.', $copy['title'], $target['name'])
            : t('Copied to "%s". Rename it and it is yours.', $copy['title']));
        redirect('/c/' . $target['slug'] . '/e/' . $copy['slug'] . '/edit');
    }

    /** The layout to use for an entry landing in $categoryId with $sourceLayoutId's shape. */
    private function layoutFor(int $categoryId, int $sourceLayoutId): int
    {
        $existing = $this->layouts->findMatching($categoryId, $sourceLayoutId);
        if ($existing !== null) {
            return (int) $existing['id'];
        }

        $source = $this->layouts->find($sourceLayoutId);
        $newId = $source === null ? null : $this->layouts->duplicate($sourceLayoutId, $source['name'], $categoryId);

        if ($newId === null) {
            throw new RuntimeException(t('Could not prepare a layout for the destination archive.'));
        }

        return $newId;
    }

    public function show(string $categorySlug, string $entrySlug): never
    {
        $category = $this->category($categorySlug);
        $entry = $this->entry($category, $entrySlug);
        $fields = $this->layouts->fields((int) $entry['layout_id']);
        $search = trim((string) ($_GET['q'] ?? ''));
        $sortFields = $this->layouts->sortableChoiceFields((int) $category['id']);
        $sort = $this->sortFor($category, $sortFields);
        $listing = $this->listing($category, $search, $sort);

        render('entries/show', [
            'pageTitle'   => $entry['title'],
            'category'    => $category,
            'entry'       => $entry,
            'layout'      => $this->layouts->find((int) $entry['layout_id']),
            'fields'      => $fields,
            'values'      => $this->entries->values((int) $entry['id']),
            'links'       => $this->entries->links((int) $entry['id']),
            'backlinks'   => $this->entries->backlinks((int) $entry['id']),
            'connections' => (new ConnectionRepo())->forTarget(ConnectionRepo::ENTRY, (int) $entry['id']),
            'entries'     => $listing['items'],
            'paging'      => $listing,
            'search'      => $search,
            'sort'        => $sort,
            'layouts'     => $this->layouts->forCategory((int) $category['id']),
            'sortFields'  => $sortFields,
            'activeEntry' => $entry,
        ]);
    }

    public function create(string $categorySlug): never
    {
        $category = $this->category($categorySlug);

        $layoutId = (int) ($_GET['layout'] ?? 0);
        $layout = $layoutId > 0 ? $this->layouts->find($layoutId) : null;

        if ($layout === null || (int) $layout['category_id'] !== (int) $category['id']) {
            $layout = $this->layouts->defaultForCategory((int) $category['id']);
        }

        if ($layout === null) {
            flash(t('This archive has no layout yet — make one first.'), 'error');
            redirect('/c/' . $category['slug'] . '/layouts');
        }

        $sortFields = $this->layouts->sortableChoiceFields((int) $category['id']);
        $sort = $this->sortFor($category, $sortFields);

        render('entries/form', [
            'pageTitle'   => t('New in %s', $category['name']),
            'category'    => $category,
            'entry'       => null,
            'layout'      => $layout,
            'layouts'     => $this->layouts->forCategory((int) $category['id']),
            'fields'      => $this->layouts->fields((int) $layout['id']),
            'values'      => [],
            'links'       => [],
            'entries'     => $this->entries->forCategory((int) $category['id'], '', null, $sort),
            'search'      => '',
            'sort'        => $sort,
            'sortFields'  => $sortFields,
            'activeEntry' => null,
        ]);
    }

    public function store(string $categorySlug): never
    {
        $category = $this->category($categorySlug);

        $layoutId = (int) ($_POST['layout_id'] ?? 0);
        $layout = $this->layouts->find($layoutId);
        if ($layout === null || (int) $layout['category_id'] !== (int) $category['id']) {
            abort(400, t('That layout does not belong to this archive.'));
        }

        $fields = $this->layouts->fields($layoutId);

        try {
            $entryId = $this->entries->save(
                null,
                (int) $category['id'],
                $layoutId,
                $fields,
                $_POST,
                $_FILES
            );
        } catch (RuntimeException $e) {
            flash($e->getMessage(), 'error');
            redirect('/c/' . $category['slug'] . '/new?layout=' . $layoutId);
        } catch (Throwable $e) {
            flash(t('The entry could not be saved: %s', $e->getMessage()), 'error');
            redirect('/c/' . $category['slug'] . '/new?layout=' . $layoutId);
        }

        $entry = $this->entries->find($entryId);
        flash(t('"%s" created.', $entry['title']));
        redirect('/c/' . $category['slug'] . '/e/' . $entry['slug']);
    }

    public function edit(string $categorySlug, string $entrySlug): never
    {
        $category = $this->category($categorySlug);
        $entry = $this->entry($category, $entrySlug);

        // ?layout= previews a different layout without committing until saved.
        $requested = (int) ($_GET['layout'] ?? 0);
        $layout = $requested > 0 ? $this->layouts->find($requested) : null;
        if ($layout === null || (int) $layout['category_id'] !== (int) $category['id']) {
            $layout = $this->layouts->find((int) $entry['layout_id']);
        }

        $sortFields = $this->layouts->sortableChoiceFields((int) $category['id']);
        $sort = $this->sortFor($category, $sortFields);

        render('entries/form', [
            'pageTitle'   => t('Editing %s', $entry['title']),
            'category'    => $category,
            'entry'       => $entry,
            'layout'      => $layout,
            'layouts'     => $this->layouts->forCategory((int) $category['id']),
            'fields'      => $this->layouts->fields((int) $layout['id']),
            'values'      => $this->entries->values((int) $entry['id']),
            'links'       => $this->entries->links((int) $entry['id']),
            'entries'     => $this->entries->forCategory((int) $category['id'], '', null, $sort),
            'search'      => '',
            'sort'        => $sort,
            'sortFields'  => $sortFields,
            'activeEntry' => $entry,
        ]);
    }

    public function update(string $categorySlug, string $entrySlug): never
    {
        $category = $this->category($categorySlug);
        $entry = $this->entry($category, $entrySlug);

        // Content carries over to fields of the same name/type in the new layout;
        // anything with nowhere to go is named in the message below.
        $layoutId = (int) ($_POST['layout_id'] ?? $entry['layout_id']);
        $layout = $this->layouts->find($layoutId);
        if ($layout === null || (int) $layout['category_id'] !== (int) $category['id']) {
            $layoutId = (int) $entry['layout_id'];
        }

        $fields = $this->layouts->fields($layoutId);

        try {
            $this->entries->save(
                (int) $entry['id'],
                (int) $category['id'],
                $layoutId,
                $fields,
                $_POST,
                $_FILES
            );
        } catch (Throwable $e) {
            flash(t('The entry could not be saved: %s', $e->getMessage()), 'error');
            redirect('/c/' . $category['slug'] . '/e/' . $entry['slug'] . '/edit');
        }

        $fresh = $this->entries->find((int) $entry['id']);
        $carry = $this->entries->lastCarry();

        if ($carry === null) {
            flash(t('Saved.'));
        } else {
            $message = t('Saved, and moved to the %s layout.', $layout['name']);

            if ($carry['carried'] !== []) {
                $message .= ' ' . t('Kept: %s.', implode(', ', $carry['carried']));
            }
            if ($carry['dropped'] !== []) {
                $message .= ' ' . t('The new layout has no field for %s, so that content was removed.',
                    implode(', ', $carry['dropped']));
            }

            flash($message, $carry['dropped'] === [] ? 'success' : 'error');
        }

        redirect('/c/' . $category['slug'] . '/e/' . $fresh['slug']);
    }

    /** Pins or unpins the entry. The wanted state is posted (not toggled), so a double submit can't flip it twice. */
    public function favorite(string $categorySlug, string $entrySlug): never
    {
        $category = $this->category($categorySlug);
        $entry = $this->entry($category, $entrySlug);
        $on = ($_POST['on'] ?? '1') === '1';

        $this->entries->setFavorite((int) $entry['id'], $on);
        flash($on
            ? t('"%s" is in your favourites.', $entry['title'])
            : t('"%s" is no longer a favourite.', $entry['title']));

        $this->back('/c/' . $category['slug'] . '/e/' . $entry['slug']);
    }

    /** Redirects back to return_to if it's a same-site path, so it can't be used to bounce the browser off-site. */
    private function back(string $fallback): never
    {
        $target = (string) ($_POST['return_to'] ?? '');

        if ($target === '' || !str_starts_with($target, '/') || str_starts_with($target, '//')) {
            redirect($fallback);
        }

        header('Location: ' . $target);
        exit;
    }

    public function destroy(string $categorySlug, string $entrySlug): never
    {
        $category = $this->category($categorySlug);
        $entry = $this->entry($category, $entrySlug);

        $this->entries->delete((int) $entry['id']);
        flash(t('"%s" was deleted.', $entry['title']));
        redirect('/c/' . $category['slug']);
    }

    /** Archives an entry: hides it everywhere until restored, without altering its content. */
    public function archive(string $categorySlug, string $entrySlug): never
    {
        $category = $this->category($categorySlug);
        $entry = $this->entry($category, $entrySlug);

        $this->entries->archive((int) $entry['id']);
        flash(t("\"%s\" archived. It won't appear in lists, search, connections or the pinboard until restored.", $entry['title']));
        redirect('/c/' . $category['slug'] . '/e/' . $entry['slug']);
    }

    /** Puts an archived entry back, exactly as it was. */
    public function restore(string $categorySlug, string $entrySlug): never
    {
        $category = $this->category($categorySlug);
        $entry = $this->entry($category, $entrySlug);

        $this->entries->restoreEntry((int) $entry['id']);
        flash(t('"%s" restored.', $entry['title']));
        $this->back('/c/' . $category['slug'] . '/e/' . $entry['slug']);
    }

    /** GET /c/{slug}/archived — every archived entry in this category. */
    public function archived(string $categorySlug): never
    {
        $category = $this->category($categorySlug);
        $sortFields = $this->layouts->sortableChoiceFields((int) $category['id']);
        $sort = $this->sortFor($category, $sortFields);

        render('entries/archived', [
            'pageTitle'   => t('%s — archive', $category['name']),
            'category'    => $category,
            'archived'    => $this->entries->archivedInCategory((int) $category['id']),
            'entries'     => $this->entries->forCategory((int) $category['id'], '', null, $sort),
            'search'      => '',
            'sort'        => $sort,
            'sortFields'  => $sortFields,
            'activeEntry' => null,
        ]);
    }

    /** Current sort order for this archive; wraps ListState so sortable fields are looked up once. */
    private function sortFor(array $category, array $sortFields): string
    {
        return ListState::sort($category, $sortFields);
    }

    private function category(string $slug): array
    {
        $category = $this->categories->findBySlug($slug);
        if ($category === null) {
            abort(404, t('There is no archive called "%s".', $slug));
        }

        return $category;
    }

    private function entry(array $category, string $slug): array
    {
        $entry = $this->entries->findInCategory((int) $category['id'], $slug);
        if ($entry === null) {
            abort(404, t('There is no entry called "%s" in %s.', $slug, $category['name']));
        }

        return $entry;
    }
}
