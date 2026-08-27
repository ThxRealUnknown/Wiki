<?php

namespace App\Controllers;

use App\CategoryRepo;
use App\EntryRepo;
use App\LayoutRepo;
use App\ListState;

final class LayoutController
{
    private CategoryRepo $categories;
    private LayoutRepo $layouts;

    public function __construct()
    {
        $this->categories = new CategoryRepo();
        $this->layouts = new LayoutRepo();
    }

    public function index(string $categorySlug): never
    {
        $category = $this->category($categorySlug);
        $sortFields = $this->layouts->sortableChoiceFields((int) $category['id']);
        $sort = ListState::sort($category, $sortFields);

        render('layouts/index', [
            'pageTitle'   => t('%s — layouts', $category['name']),
            'category'    => $category,
            'layouts'     => $this->layouts->forCategory((int) $category['id']),
            'entries'     => (new EntryRepo())->forCategory((int) $category['id'], '', null, $sort),
            'search'      => '',
            'sort'        => $sort,
            'sortFields'  => $sortFields,
            'activeEntry' => null,
        ]);
    }

    public function store(string $categorySlug): never
    {
        $category = $this->category($categorySlug);
        $name = trim((string) ($_POST['name'] ?? ''));

        if ($name === '') {
            flash(t('A layout needs a name.'), 'error');
            redirect('/c/' . $category['slug'] . '/layouts');
        }

        $layoutId = $this->layouts->create((int) $category['id'], $name);

        // Starts with one field so it isn't blank.
        $this->layouts->saveFields($layoutId, [
            ['label' => t('Description'), 'field_type' => 'richtext', 'width' => 'full'],
        ]);

        flash(t('Layout "%s" created.', $name));
        redirect('/c/' . $category['slug'] . '/layouts/' . $layoutId);
    }

    public function edit(string $categorySlug, int $layoutId): never
    {
        $category = $this->category($categorySlug);
        $layout = $this->layout($category, $layoutId);
        $sortFields = $this->layouts->sortableChoiceFields((int) $category['id']);
        $sort = ListState::sort($category, $sortFields);

        render('layouts/edit', [
            'pageTitle'    => t('Layout: %s', $layout['name']),
            'category'     => $category,
            'layout'       => $layout,
            'layouts'      => $this->layouts->forCategory((int) $category['id']),
            'fields'       => $this->layouts->fields($layoutId),
            'archived'     => $this->layouts->archivedFields($layoutId),
            // Tree order with depth, matching the sidebar.
            'allCategories'=> $this->categories->flatTree(),
            'entryCount'   => $this->layouts->entryCount($layoutId),
            'entries'      => (new EntryRepo())->forCategory((int) $category['id'], '', null, $sort),
            'search'       => '',
            'sort'         => $sort,
            'sortFields'   => $sortFields,
            'activeEntry'  => null,
        ]);
    }

    /**
     * The fields admin: everything a layout has ever had, live and archived,
     * and the only place where content can actually be destroyed.
     */
    public function fields(string $categorySlug, int $layoutId): never
    {
        $category = $this->category($categorySlug);
        $layout = $this->layout($category, $layoutId);

        $archived = $this->layouts->archivedFields($layoutId);
        foreach ($archived as $index => $field) {
            $archived[$index]['content_count'] = $this->layouts->fieldContentCount((int) $field['id']);
        }

        $sortFields = $this->layouts->sortableChoiceFields((int) $category['id']);
        $sort = ListState::sort($category, $sortFields);

        render('layouts/fields', [
            'pageTitle'   => t('Fields: %s', $layout['name']),
            'category'    => $category,
            'layout'      => $layout,
            'layouts'     => $this->layouts->forCategory((int) $category['id']),
            'fields'      => $this->layouts->fields($layoutId),
            'archived'    => $archived,
            'entryCount'  => $this->layouts->entryCount($layoutId),
            'entries'     => (new EntryRepo())->forCategory((int) $category['id'], '', null, $sort),
            'search'      => '',
            'sort'        => $sort,
            'sortFields'  => $sortFields,
            'activeEntry' => null,
        ]);
    }

    public function restoreField(string $categorySlug, int $layoutId, int $fieldId): never
    {
        $category = $this->category($categorySlug);
        $this->layout($category, $layoutId);
        $field = $this->field($layoutId, $fieldId);

        $this->layouts->restoreField($fieldId);
        flash(t('"%s" is back in the layout, with everything it held.', $field['label']));
        redirect('/c/' . $category['slug'] . '/layouts/' . $layoutId . '/fields');
    }

    public function destroyField(string $categorySlug, int $layoutId, int $fieldId): never
    {
        $category = $this->category($categorySlug);
        $this->layout($category, $layoutId);
        $field = $this->field($layoutId, $fieldId);

        // Only archived fields can be destroyed, to prevent accidental data loss.
        if ($field['archived_at'] === null) {
            flash(t('Remove the field from the layout first, then delete it here.'), 'error');
            redirect('/c/' . $category['slug'] . '/layouts/' . $layoutId . '/fields');
        }

        if (trim((string) ($_POST['confirm_label'] ?? '')) !== $field['label']) {
            flash(t('Type the field name exactly to confirm.'), 'error');
            redirect('/c/' . $category['slug'] . '/layouts/' . $layoutId . '/fields');
        }

        $lost = $this->layouts->fieldContentCount($fieldId);
        $this->layouts->destroyField($fieldId);

        flash($lost > 0
            ? tn($lost, '"%2$s" and the %1$d value stored in it were deleted.', '"%2$s" and the %1$d values stored in it were deleted.', $field['label'])
            : t('"%s" was deleted. It held nothing.', $field['label']));
        redirect('/c/' . $category['slug'] . '/layouts/' . $layoutId . '/fields');
    }

    private function field(int $layoutId, int $fieldId): array
    {
        $field = $this->layouts->findField($fieldId);
        if ($field === null || (int) $field['layout_id'] !== $layoutId) {
            abort(404, t('That field is not part of this layout.'));
        }

        return $field;
    }

    public function update(string $categorySlug, int $layoutId): never
    {
        $category = $this->category($categorySlug);
        $layout = $this->layout($category, $layoutId);

        $this->layouts->rename($layoutId, (string) ($_POST['name'] ?? $layout['name']));

        $posted = $_POST['fields'] ?? [];
        if (!is_array($posted)) {
            $posted = [];
        }

        // array_values() preserves the drag-and-drop order PHP re-keys on submit.
        $this->layouts->saveFields($layoutId, array_values($posted));

        flash(t('Layout saved.'));
        redirect('/c/' . $category['slug'] . '/layouts/' . $layoutId);
    }

    public function destroy(string $categorySlug, int $layoutId): never
    {
        $category = $this->category($categorySlug);
        $layout = $this->layout($category, $layoutId);

        if (!$this->layouts->delete($layoutId)) {
            flash(
                t('That layout still has entries using it. Move them to another layout first.'),
                'error'
            );
            redirect('/c/' . $category['slug'] . '/layouts/' . $layoutId);
        }

        flash(t('Layout "%s" deleted.', $layout['name']));
        redirect('/c/' . $category['slug'] . '/layouts');
    }

    public function duplicate(string $categorySlug, int $layoutId): never
    {
        $category = $this->category($categorySlug);
        $this->layout($category, $layoutId);

        $copyId = $this->layouts->duplicate($layoutId);
        flash(t('Layout duplicated.'));
        redirect('/c/' . $category['slug'] . '/layouts/' . $copyId);
    }

    public function makeDefault(string $categorySlug, int $layoutId): never
    {
        $category = $this->category($categorySlug);
        $layout = $this->layout($category, $layoutId);

        $this->layouts->makeDefault($layoutId);
        flash(t('"%s" is now the default for new entries.', $layout['name']));
        redirect('/c/' . $category['slug'] . '/layouts');
    }

    private function category(string $slug): array
    {
        $category = $this->categories->findBySlug($slug);
        if ($category === null) {
            abort(404, t('There is no archive called "%s".', $slug));
        }

        return $category;
    }

    private function layout(array $category, int $layoutId): array
    {
        $layout = $this->layouts->find($layoutId);
        if ($layout === null || (int) $layout['category_id'] !== (int) $category['id']) {
            abort(404, t('That layout is not part of this archive.'));
        }

        return $layout;
    }
}
