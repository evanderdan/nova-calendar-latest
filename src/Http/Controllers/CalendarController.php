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

use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Carbon;
use Laravel\Nova\Http\Requests\NovaRequest;
use Illuminate\Support\Facades\Log;
use Wdelfuego\NovaCalendar\DataProvider\MonthCalendar;

use Wdelfuego\NovaCalendar\View\AbstractView as View;

class CalendarController extends BaseController
{
    // Must match the hard-coded value in Tool.vue's reload() method
    const API_PATH_PREFIX = '/nova-vendor/wdelfuego/nova-calendar/';

    private $dataProviders = [];

    public function __construct()
    {
        // Load data providers, keyed by uri
        foreach(config('nova-calendar', []) as $calendarKey => $calendarConfig)
        {
            // We are assuming these keys to exist since the Nova Tool
            // Wdelfuego\NovaCalendar\NovaCalendar does all sorts of checks on initiation
            // Not sure if that assumption is completely valid but assuming valid config for now
            $dataProvider = new ($calendarConfig['dataProvider']);
            $dataProvider->setConfig($calendarConfig);
            $this->dataProviders[$calendarConfig['uri']] = $dataProvider;
        }
    }    
    
    public function index()
    {
        /** @var User $user */
        $user = \Illuminate\Support\Facades\Auth::user();

        return inertia('NovaCalendar', [
            'pageTitle' => config('nova-calendar.title', 'Nova Calendar'),
            'user' => $user,
        ]);
    }


    protected function getCalendarDataProviderForUri(string $calendarUri)
    {
        if(!isset($this->dataProviders[$calendarUri]))
        {
            throw new \Exception("Unknown calendar uri: $calendarUri");
        }

        return $this->dataProviders[$calendarUri];
    }

    public function getMonthCalendarData(Request $request)
    {
        $year = $request->get('year', date('Y'));
        $month = $request->get('month', date('m'));
        Log::debug('getMonthCalendarData', [$year, $month]);
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

        Log::debug('getMonthCalendarData', [$installerId, $bookingStatus, $bookingType]);
        $this->dataProvider->setRequest($this->request)
            ->setYearAndMonth($year, $month)
            ->setInstallerIds($installerId)
            ->setBookingStatus($bookingStatus)
            ->setBookingType($bookingType);

        Log::debug('getMonthCalendarData', [
            'year' => $year,
            'month' => $month,
            'title' => $this->dataProvider->title(),
            'columns' => $this->dataProvider->daysOfTheWeek(),
            'weeks' => $this->dataProvider->calendarWeeks(),
            'styles' => array_replace_recursive($this->defaultStyles(), $this->dataProvider->eventStyles()),
        ]);
        return [
            'year' => $year,
            'month' => $month,
            'title' => $this->dataProvider->title(),
            'columns' => $this->dataProvider->daysOfTheWeek(),
            'weeks' => $this->dataProvider->calendarWeeks(),
            'styles' => array_replace_recursive($this->defaultStyles(), $this->dataProvider->eventStyles()),
        ];
    }

    public function getCalendarData(NovaRequest $request, string $view = 'month')
    {
        $requestUri = substr($request->url(), strlen($request->schemeAndHttpHost()));

        // Get calendar URI from full request URI by ditching the prefix and the last path element (view)
        $calendarUri = substr($requestUri, strlen(self::API_PATH_PREFIX));
        $calendarUri = substr($calendarUri, 0, strrpos($calendarUri, '/'));

        $dataProvider = $this->getCalendarDataProviderForUri($calendarUri)->withRequest($request);

        Log::debug('in here - getCalendarData', [$requestUri, $calendarUri, $dataProvider]);

        if($request->query('isInitRequest'))
        {
            $dataProvider->setActiveFilterKey($dataProvider->defaultFilterKey());
        }
        else
        {
            $dataProvider->setActiveFilterKey($request->query('filter'));
        }

        $view = View::get($view);
        $view->initFromRequest($request);
        $view = $dataProvider->customizeView($view);        
        
        Log::debug('in here - getCalendarData', [$view]);

        return $view->calendarData($request, $dataProvider);
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
