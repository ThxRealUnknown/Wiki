<?php

namespace App\Controllers;

use App\CategoryRepo;
use App\LayoutRepo;

final class CategoryController
{
    private CategoryRepo $repo;

    public function __construct()
    {
        $this->repo = new CategoryRepo();
    }

    public function index(): never
    {
        $categories = $this->repo->allWithCounts();

        // Lets an archive be sorted by its own choice fields, not just the built-in orders.
        $layouts = new LayoutRepo();
        $sortFields = [];
        foreach ($categories as $category) {
            $sortFields[(int) $category['id']] = $layouts->sortableChoiceFields((int) $category['id']);
        }

        render('categories/index', [
            'pageTitle'  => t('Archives'),
            'tree'       => $this->repo->treeWithCounts(),
            'categories' => $categories,
            'parents'    => $this->repo->possibleParents(),
            'sortFields' => $sortFields,
        ]);
    }

    public function store(): never
    {
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') {
            flash(t('An archive needs a name.'), 'error');
            redirect('/archives');
        }

        $categoryId = $this->repo->create($_POST);

        // Starter layout so the archive isn't unusable until one is created.
        $layouts = new LayoutRepo();
        $layoutId = $layouts->create($categoryId, t('Standard layout'), true);
        $layouts->saveFields($layoutId, [
            ['label' => t('Summary'), 'field_type' => 'textarea', 'width' => 'full'],
            ['label' => t('Description'), 'field_type' => 'richtext', 'width' => 'full'],
        ]);

        flash(t('Archive "%s" created, with a starter layout you can edit.', $name));

        $category = $this->repo->find($categoryId);
        redirect('/c/' . $category['slug']);
    }

    public function update(int $id): never
    {
        if ($this->repo->find($id) === null) {
            abort(404, t('That archive does not exist.'));
        }

        $this->repo->update($id, $_POST);
        flash(t('Archive updated.'));
        redirect('/archives');
    }

    public function destroy(int $id): never
    {
        $category = $this->repo->find($id);
        if ($category === null) {
            abort(404, t('That archive does not exist.'));
        }

        // Deleting an archive takes its layouts and every entry with it, so make
        // the user type the name rather than trusting a single click.
        $confirmation = trim((string) ($_POST['confirm_name'] ?? ''));
        if ($confirmation !== $category['name']) {
            flash(t('Type the archive name exactly to confirm deletion.'), 'error');
            redirect('/archives');
        }

        $this->repo->delete($id);
        flash(t('Archive "%s" and everything in it was deleted.', $category['name']));
        redirect('/archives');
    }

    public function reorder(): never
    {
        $order = $_POST['order'] ?? [];
        if (!is_array($order)) {
            json_response(['ok' => false], 400);
        }

        $this->repo->reorder(array_map('intval', $order));
        json_response(['ok' => true]);
    }
}
