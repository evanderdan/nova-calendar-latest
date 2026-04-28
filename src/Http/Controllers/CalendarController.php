<?php

/*
 * © Copyright 2022 · Willem Vervuurt, Studio Delfuego
 * 
 * You can modify, use and distribute this package under one of two licenses:
 * 1. GNU AGPLv3
 * 2. A perpetual, non-revocable and 100% free (as in beer) do-what-you-want 
 *    license that allows both non-commercial and commercial use, under conditions.
 *    See LICENSE.md for details.
 * 
 *    (it boils down to: do what you want as long as you're building and/or
 *     using calendar views, but don't embed this package or a modified version
 *     of it in free or paid-for software libraries and packages aimed at developers).
 */

namespace Wdelfuego\NovaCalendar\Http\Controllers;

use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Laravel\Nova\Http\Requests\NovaRequest;
use Wdelfuego\NovaCalendar\Contracts\CalendarDataProviderInterface;
use Wdelfuego\NovaCalendar\DataProvider\MonthCalendar;

class CalendarController extends BaseController
{
    private $request;
    private $dataProvider;

    public function __construct(NovaRequest $request, CalendarDataProviderInterface $dataProvider)
    {
        $this->request = $request;
        /** @var MonthCalendar dataProvider */
        $this->dataProvider = $dataProvider;
    }

    /**
     * Handles the basic presentation of the calendar view
     */
    public function index()
    {
        /** @var User $user */
        $user = \Illuminate\Support\Facades\Auth::user();

        return inertia('NovaCalendar', [
            'pageTitle' => config('nova-calendar.title', 'Nova Calendar'),
            'user' => $user,
        ]);
    }

    public function getMonthCalendarData(Request $request)
    {
        $year = $request->get('year', date('Y'));
        $month = $request->get('month', date('m'));
        while ($month > 12) {
            $year += 1;
            $month -= 12;
        }
        while ($month < 1) {
            $year -= 1;
            $month += 12;
        }
        $installerId = $request->get('installers') === 'null' ? null : $request->get('installers');
        $bookingStatus = $request->get('bookingStatus') === 'null' ? null : $request->get('bookingStatus');
        $bookingType = $request->get('bookingType') === 'null' ? null : $request->get('bookingType');

        $this->dataProvider->setRequest($this->request)
            ->setYearAndMonth($year, $month)
            ->setInstallerIds($installerId)
            ->setBookingStatus($bookingStatus)
            ->setBookingType($bookingType);

        return [
            'year' => $year,
            'month' => $month,
            'title' => $this->dataProvider->title(),
            'columns' => $this->dataProvider->daysOfTheWeek(),
            'weeks' => $this->dataProvider->calendarWeeks(),
            'styles' => array_replace_recursive($this->defaultStyles(), $this->dataProvider->eventStyles()),
        ];
    }

    public function defaultStyles(): array
    {
        return [
            'default' => [
                'color' => '#fff',
                'background-color' => 'rgba(var(--colors-primary-500), 0.9)',
            ]
        ];
    }
}