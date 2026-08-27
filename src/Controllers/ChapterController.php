<?php

namespace App\Controllers;

use App\ChapterRepo;
use App\ConnectionRepo;
use App\Settings;
use RuntimeException;
use Throwable;

/**
 * Two views onto the same chapters: Draft is the workshop, Story is the finished
 * book. Story only ever shows chapters flagged visible, and never lets you edit.
 */
final class ChapterController
{
    private ChapterRepo $chapters;

    public function __construct()
    {
        $this->chapters = new ChapterRepo();
    }

    // ------------------------------------------------------------ draft side

    public function draftIndex(): never
    {
        render('chapters/draft_index', [
            'pageTitle'      => t('Draft'),
            'chapters'       => $this->chapters->all(),
            'goal'           => Settings::get(Settings::DRAFT_GOAL_WORDS, ''),
            'activeChapter'  => null,
            'section'        => 'draft',
        ]);
    }

    public function draftCreate(): never
    {
        render('chapters/draft_form', [
            'pageTitle'     => t('New chapter'),
            'chapter'       => null,
            'chapters'      => $this->chapters->all(),
            'parts'         => $this->chapters->parts(),
            'connections'   => [],
            'activeChapter' => null,
            'section'       => 'draft',
        ]);
    }

    public function draftStore(): never
    {
        try {
            $chapterId = $this->chapters->save(null, $_POST);
        } catch (RuntimeException $e) {
            flash($e->getMessage(), 'error');
            redirect('/draft/new');
        }

        $chapter = $this->chapters->find($chapterId);
        flash(t('Chapter "%s" created.', $chapter['title']));
        redirect('/draft/' . $chapter['slug']);
    }

    public function draftShow(string $slug): never
    {
        $chapter = $this->chapter($slug);

        render('chapters/draft_form', [
            'pageTitle'     => $chapter['title'],
            'chapter'       => $chapter,
            'chapters'      => $this->chapters->all(),
            'parts'         => $this->chapters->parts(),
            'connections'   => (new ConnectionRepo())->forTarget(ConnectionRepo::CHAPTER, (int) $chapter['id']),
            'activeChapter' => $chapter,
            'section'       => 'draft',
        ]);
    }

    public function draftUpdate(string $slug): never
    {
        $chapter = $this->chapter($slug);

        try {
            $this->chapters->save((int) $chapter['id'], $_POST);
        } catch (Throwable $e) {
            flash(t('The chapter could not be saved: %s', $e->getMessage()), 'error');
            redirect('/draft/' . $chapter['slug']);
        }

        $fresh = $this->chapters->find((int) $chapter['id']);
        flash(t('Saved.'));
        redirect('/draft/' . $fresh['slug']);
    }

    public function draftToggle(string $slug): never
    {
        $chapter = $this->chapter($slug);
        $visible = (int) $chapter['is_visible'] !== 1;

        $this->chapters->setVisibility((int) $chapter['id'], $visible);
        flash($visible
            ? t('"%s" is now shown in the Story.', $chapter['title'])
            : t('"%s" is hidden from the Story.', $chapter['title']));

        redirect('/draft/' . $chapter['slug']);
    }

    public function draftDestroy(string $slug): never
    {
        $chapter = $this->chapter($slug);

        $this->chapters->delete((int) $chapter['id']);
        flash(t('"%s" was deleted.', $chapter['title']));
        redirect('/draft');
    }

    // ------------------------------------------------------------ story side

    public function storyIndex(): never
    {
        render('chapters/story_index', [
            'pageTitle'     => t('Story'),
            'chapters'      => $this->chapters->published(),
            'draftCount'    => $this->chapters->countAll(),
            'activeChapter' => null,
            'section'       => 'story',
        ]);
    }

    public function storyShow(string $slug): never
    {
        $chapter = $this->chapter($slug);

        // Story is the reader's view: a hidden chapter simply is not there.
        if ((int) $chapter['is_visible'] !== 1) {
            abort(404, t('That chapter is not part of the story yet.'));
        }

        render('chapters/story_show', [
            'pageTitle'     => $chapter['title'],
            'chapter'       => $chapter,
            'chapters'      => $this->chapters->published(),
            'connections'   => (new ConnectionRepo())->forTarget(ConnectionRepo::CHAPTER, (int) $chapter['id']),
            'neighbours'    => $this->chapters->neighbours((int) $chapter['id']),
            'activeChapter' => $chapter,
            'section'       => 'story',
        ]);
    }

    private function chapter(string $slug): array
    {
        $chapter = $this->chapters->findBySlug($slug);
        if ($chapter === null) {
            abort(404, t('There is no chapter called "%s".', $slug));
        }

        return $chapter;
    }
}
