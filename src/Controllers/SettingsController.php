<?php

namespace App\Controllers;

use App\Calendar;
use App\Locales;
use App\Settings;
use App\TagRepo;
use App\Uploads;
use Throwable;

/** Site-wide settings, stored as name/value pairs so new settings need no migration. */
final class SettingsController
{
    public function index(): never
    {
        render('settings/index', [
            'pageTitle' => t('Settings'),
            'banner'    => Settings::get(Settings::SITE_BANNER),
            'tags'      => (new TagRepo())->all(),
            'features'  => [
                'book'        => Settings::flag(Settings::FEATURE_BOOK),
                'map'         => Settings::flag(Settings::FEATURE_MAP),
                'connections' => Settings::flag(Settings::FEATURE_CONNECTIONS),
                'timeline'    => Settings::flag(Settings::FEATURE_TIMELINE),
            ],
            'epochName' => Settings::get(Settings::TIMELINE_EPOCH_NAME, ''),
            'epochAbbr' => Settings::get(Settings::TIMELINE_EPOCH_ABBR, ''),
            'locale'    => Settings::get(Settings::LOCALE, Locales::DEFAULT),
            'locales'   => Locales::all(),
            'draftGoal' => Settings::get(Settings::DRAFT_GOAL_WORDS, ''),
        ]);
    }

    /** A target word count for the whole draft, shown as progress on the Draft page. */
    public function updateGoal(): never
    {
        $raw = trim((string) ($_POST['draft_goal_words'] ?? ''));
        $goal = $raw === '' ? null : max(0, (int) preg_replace('/\D/', '', $raw));

        Settings::set(Settings::DRAFT_GOAL_WORDS, $goal === null ? null : (string) $goal);

        flash($goal === null ? t('Writing goal cleared.') : t('Writing goal set to %s words.', number_format($goal)));
        redirect('/settings');
    }

    public function updateFeatures(): never
    {
        Settings::setFlag(Settings::FEATURE_BOOK, !empty($_POST['feature_book']));
        Settings::setFlag(Settings::FEATURE_MAP, !empty($_POST['feature_map']));
        Settings::setFlag(Settings::FEATURE_CONNECTIONS, !empty($_POST['feature_connections']));
        Settings::setFlag(Settings::FEATURE_TIMELINE, !empty($_POST['feature_timeline']));

        flash(t('Features updated.'));
        redirect('/settings');
    }

    /** The wiki's own calendar — its name, and the abbreviation every year is shown with. */
    public function updateTimeline(): never
    {
        Settings::set(Settings::TIMELINE_EPOCH_NAME, trim((string) ($_POST['epoch_name'] ?? '')));
        Settings::set(Settings::TIMELINE_EPOCH_ABBR, trim((string) ($_POST['epoch_abbr'] ?? '')));

        flash(t('Timeline updated.'));
        redirect('/settings');
    }

    public function updateLanguage(): never
    {
        $code = Locales::resolve($_POST['locale'] ?? null);
        Settings::set(Settings::LOCALE, $code);

        flash(t('Language updated.'));
        redirect('/settings');
    }

    /** GET /settings/calendar — designing the calendar itself. */
    public function calendar(): never
    {
        render('settings/calendar', [
            'pageTitle' => t('Calendar'),
            'config'    => Calendar::config(),
        ]);
    }

    public function updateCalendar(): never
    {
        $months = [];
        foreach ((array) ($_POST['months'] ?? []) as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            $days = (int) ($row['days'] ?? 0);
            if ($name === '' || $days <= 0) {
                continue;
            }
            $months[] = ['name' => mb_substr($name, 0, 80), 'days' => $days];
        }

        $weekdays = [];
        foreach ((array) ($_POST['weekdays'] ?? []) as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $weekdays[] = mb_substr($name, 0, 40);
        }

        if ($months === [] || $weekdays === []) {
            flash(t('A calendar needs at least one month and one weekday.'), 'error');
            redirect('/settings/calendar');
        }

        $monthCount = count($months);

        $intercalary = [];
        foreach ((array) ($_POST['intercalary'] ?? []) as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            $days = (int) ($row['days'] ?? 0);
            if ($name === '' || $days <= 0) {
                continue;
            }
            $intercalary[] = [
                'name'           => mb_substr($name, 0, 80),
                'days'           => $days,
                'after_month'    => max(0, min($monthCount, (int) ($row['after_month'] ?? 0))),
                'counts_weekday' => !empty($row['counts_weekday']),
            ];
        }

        $leapRules = [];
        foreach ((array) ($_POST['leap_rules'] ?? []) as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            $every = (int) ($row['every_years'] ?? 0);
            $extra = (int) ($row['extra_days'] ?? 0);
            $month = (int) ($row['month'] ?? 0);
            if ($every <= 0 || $extra === 0 || $month < 1 || $month > $monthCount) {
                continue;
            }
            $leapRules[] = [
                'name'        => $name === '' ? t('Leap rule') : mb_substr($name, 0, 80),
                'every_years' => $every,
                'offset'      => (int) ($row['offset'] ?? 0),
                'month'       => $month,
                'extra_days'  => $extra,
            ];
        }

        $intercalaryCount = count($intercalary);
        $weekdayCount = count($weekdays);

        // Saved before parsing holidays below: a Cycle holiday's start date is
        // validated via Calendar::parseDate(), which reads the live config.
        Calendar::set([
            'months'      => $months,
            'weekdays'    => $weekdays,
            'intercalary' => $intercalary,
            'leap_rules'  => $leapRules,
            'holidays'    => [],
        ]);

        $holidays = [];
        foreach ((array) ($_POST['holidays'] ?? []) as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $name = mb_substr($name, 0, 80);
            $type = (string) ($row['type'] ?? 'date');

            if ($type === 'weekday') {
                $weekday = (int) ($row['weekday'] ?? -1);
                if ($weekday < 0 || $weekday >= $weekdayCount) {
                    continue;
                }
                $occurrence = (int) ($row['occurrence'] ?? 1);
                $occurrence = $occurrence === -1 ? -1 : max(1, min(5, $occurrence));

                $holidays[] = [
                    'type'       => 'weekday',
                    'name'       => $name,
                    'month'      => max(0, min($monthCount, (int) ($row['month'] ?? 0))),
                    'occurrence' => $occurrence,
                    'weekday'    => $weekday,
                ];
                continue;
            }

            if ($type === 'cycle') {
                $every = (int) ($row['every_days'] ?? 0);
                if ($every <= 0) {
                    continue;
                }
                $start = Calendar::parseDate(
                    $row['start']['year'] ?? '',
                    $row['start']['slot'] ?? '',
                    $row['start']['day'] ?? ''
                );
                if ($start === null) {
                    continue;
                }

                $holidays[] = [
                    'type'       => 'cycle',
                    'name'       => $name,
                    'every_days' => $every,
                    'start'      => $start,
                ];
                continue;
            }

            $token = Calendar::parseSlotToken($row['slot'] ?? '');
            $day = (int) ($row['day'] ?? 0);
            if ($token === null || $day <= 0) {
                continue;
            }
            if (!Calendar::slotRefExists($token['kind'], $token['ref'], $monthCount, $intercalaryCount)) {
                continue;
            }

            $holidays[] = [
                'type' => 'date',
                'name' => $name,
                'kind' => $token['kind'],
                'ref'  => $token['ref'],
                'day'  => $day,
            ];
        }

        Calendar::set([
            'months'      => $months,
            'weekdays'    => $weekdays,
            'intercalary' => $intercalary,
            'leap_rules'  => $leapRules,
            'holidays'    => $holidays,
        ]);

        flash(t('Calendar updated.'));
        redirect('/settings/calendar');
    }

    /** Removes a tag from every entry that carries it. */
    public function deleteTag(): never
    {
        $tag = trim((string) ($_POST['tag'] ?? ''));

        if ($tag === '') {
            flash(t('No tag was named.'), 'error');
            redirect('/settings');
        }

        $result = (new TagRepo())->delete($tag);

        if ($result['entries'] === 0 && $result['layouts'] === 0) {
            flash(t('Nothing was using "%s".', $tag), 'error');
            redirect('/settings');
        }

        $parts = [];
        if ($result['entries'] > 0) {
            $parts[] = tn($result['entries'], '%d entry', '%d entries');
        }
        if ($result['layouts'] > 0) {
            $parts[] = tn($result['layouts'], '%d layout', '%d layouts');
        }

        flash(t('"%s" removed from %s.', $tag, implode(' ' . t('and') . ' ', $parts)));
        redirect('/settings');
    }

    public function updateBanner(): never
    {
        $current = Settings::get(Settings::SITE_BANNER);
        $upload = $_FILES['site_banner'] ?? null;
        $hasUpload = is_array($upload)
            && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

        if (!empty($_POST['remove'])) {
            Settings::forget(Settings::SITE_BANNER);
            Uploads::remove($current);
            flash(t('Banner removed.'));
            redirect('/settings');
        }

        if (!$hasUpload) {
            flash(t('Choose an image first.'), 'error');
            redirect('/settings');
        }

        try {
            $path = Uploads::store($upload);
        } catch (Throwable $e) {
            flash($e->getMessage(), 'error');
            redirect('/settings');
        }

        Settings::set(Settings::SITE_BANNER, $path);

        // Only once the new one is safely stored.
        Uploads::remove($current);

        flash(t('Banner updated.'));
        redirect('/settings');
    }
}
