<?php

namespace App\Controllers;

use App\Calendar;
use App\CategoryRepo;
use App\Timeline;
use App\TimelineRepo;

/**
 * The timeline: every Date and Era field value across every archive, on one
 * pannable, zoomable axis. The dataset is fetched client-side, like the
 * pinboard's graph.
 */
final class TimelineController
{
    private TimelineRepo $timeline;

    public function __construct()
    {
        $this->timeline = new TimelineRepo();
    }

    /** GET /timeline[?focus=] */
    public function index(): never
    {
        render('timeline/index', [
            'pageTitle' => t('Timeline'),
            'section'   => 'timeline',
            'focus'     => (int) ($_GET['focus'] ?? 0),
            'epochName' => Timeline::epochName(),
            'epochAbbr' => Timeline::epochAbbr(),
            // Archive tree for the filter column, same as the pinboard's.
            'archives'  => (new CategoryRepo())->treeWithCounts(),
        ]);
    }

    /** GET /timeline/events */
    public function events(): never
    {
        json_response(['ok' => true] + $this->timeline->events());
    }

    /**
     * GET /timeline/calendar[?year=&month=] — the month-grid view. Year/month
     * are remembered per session (like ListState's sort), so returning to the
     * page doesn't reset to year 1.
     */
    public function calendar(): never
    {
        $monthCount = count(Calendar::config()['months']);

        if (isset($_GET['year'])) {
            $_SESSION['calendar_year'] = (int) $_GET['year'];
        }
        if (isset($_GET['month'])) {
            $_SESSION['calendar_month'] = (int) $_GET['month'];
        }

        $year = (int) ($_SESSION['calendar_year'] ?? 1);
        $month = max(1, min($monthCount, (int) ($_SESSION['calendar_month'] ?? 1)));

        $prevMonth = $month > 1 ? $month - 1 : $monthCount;
        $prevYear = $month > 1 ? $year : $year - 1;
        $nextMonth = $month < $monthCount ? $month + 1 : 1;
        $nextYear = $month < $monthCount ? $year : $year + 1;

        $monthDays = Calendar::monthLength($year, $month);

        // Resolved server-side since a cycle holiday needs the same day-numbering
        // math as a Date field, which the client doesn't otherwise need.
        $intercalaryHolidays = [];
        foreach (Calendar::config()['intercalary'] as $i => $block) {
            $intercalaryHolidays[] = Calendar::holidaysForSurface($year, 'intercalary', $i, (int) $block['days']);
        }

        render('timeline/calendar', [
            'pageTitle'     => t('Calendar'),
            'section'       => 'timeline',
            'year'          => $year,
            'month'         => $month,
            'prevYear'      => $prevYear,
            'prevMonth'     => $prevMonth,
            'nextYear'      => $nextYear,
            'nextMonth'     => $nextMonth,
            // Month-grid layout: within a month, weekdays just advance a day at a
            // time, so the client only needs where day 1 falls and how many days there are.
            'startWeekday'  => Calendar::weekdayOf(['year' => $year, 'kind' => 'month', 'ref' => $month, 'day' => 1]),
            'monthDays'     => $monthDays,
            'holidays'      => Calendar::holidaysForSurface($year, 'month', $month, $monthDays),
            'intercalaryHolidays' => $intercalaryHolidays,
            'epochName'     => Timeline::epochName(),
            'epochAbbr'     => Timeline::epochAbbr(),
            'archives'      => (new CategoryRepo())->treeWithCounts(),
        ]);
    }
}
