/*
* © Copyright 2022 · Willem Vervuurt, Studio Delfuego
*
* You can modify, use and distribute this package under one of two licenses:
* 1. GNU AGPLv3
* 2. A perpetual, non-revocable and 100% free (as in beer) do-what-you-want
* license that allows both non-commercial and commercial use, under conditions.
* See LICENSE.md for details.
*
* (it boils down to: do what you want as long as you're building and/or
* using calendar views, but don't embed this package or a modified version
* of it in free or paid-for software libraries and packages aimed at developers).
*/

<template>
    <div>

        <Head :title="pageTitle" />
        <div class="show-filter-button" @click="showFilter = !showFilter">Filters</div>
        <transition @enter="onEnter" @after-enter="onAfterEnter" @leave="onLeave" @before-leave="onBeforeLeave">
            <div v-show="showFilter" class="flex flex-row filter-boxes-container">
                <div v-show="showInstallerFilter" class="filter-box-container thirds">
                    <div class="filter-container">
                        <!-- Internal installers -->
                        <div class="filter-section" v-if="installersInternal.length">
                            <div class="filter-section-title">Internal</div>
                            <div class="filter-item" v-for="(installer, i) in installersInternal" :key="`int-${i}-${installer.id}`">
                                <input type="checkbox" :id="`installer-${installer.id}`" :value="installer.id" v-model="selectedInstallers" @change="this.reset" />
                                <label>{{ installer.first_name }} {{ installer.last_name }}</label>
                            </div>
                        </div>

                        <!-- External installers -->
                        <div class="filter-section" v-if="installersExternal.length">
                            <div class="filter-section-title">External</div>
                            <div class="filter-item" v-for="(installer, i) in installersExternal" :key="`ext-${i}-${installer.id}`">
                                <input type="checkbox" :id="`installer-${installer.id}`" :value="installer.id" v-model="selectedInstallers" @change="this.reset" />
                                <label>{{ installer.first_name }} {{ installer.last_name }}</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="filter-box-container" :class="showInstallerFilter ? 'thirds' : 'half-and-half'">
                    <div class="filter-container">
                        <div class="filter-item" v-for="status in bookingStatuses" :key="status">
                            <input type="checkbox" :id="`status-${status}`" :value="status" v-model="bookingStatus"
                                @change="this.reset" />
                            <label :for="`status-${status}`">{{ capitaliseFirstLetter(status) }}</label>
                        </div>
                    </div>
                </div>
                <div class="filter-box-container" :class="showInstallerFilter ? 'thirds' : 'half-and-half'">
                    <div class="filter-container">
                        <div class="filter-item" v-for="type in bookingTypes" :key="type">
                            <input type="checkbox" :id="`type-${type}`" :value="type" v-model="bookingType"
                                @change="this.reset" />
                            <label :for="`type-${type}`">{{ capitaliseFirstLetter(type) }}</label>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </div>

    <div id="nc-control">

        <h1 @click="reset" class="text-90 font-normal text-xl md:text-2xl noselect">
            <span>{{ $data.title }}</span>
        </h1>

        <a @click="prevMonth" class="left" href="#">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
            </svg>
        </a>

        <a @click="nextMonth" class="right" href="#">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13 5l7 7-7 7M5 5l7 7-7 7" />
            </svg>
        </a>

    </div>

    <div style="width:100%;overflow:scroll">
        <Card class="flex flex-col items-center justify-center dark:bg-gray-800"
            style="min-height: 300px;min-width:800px;background-color:var(--bg-gray-800)">

            <div class="nova-calendar noselect">

                <div class="nc-header">
                    <div v-for="column in $data.columns"
                        class="border-gray-200 dark:border-gray-900 dark:text-gray-300"><span>{{ column }}</span>
                    </div>
                </div>

                <div class="nc-content">

                    <!-- week wrapper -->
                    <div v-for="(week, weekIndex) in $data.weeks" class="week">

                        <!-- a cell per day, background -->
                        <template v-for="day in week">
                            <div class="day dark:bg-gray-900 dark:border-gray-800"
                                :class="['nc-col-' + day.weekdayColumn]"
                                v-bind:class="{ 'withinRange': day.isWithinRange, 'today': day.isToday }">
                                <div class="dayheader text-gray-400 noselect">
                                    <span class="daylabel">{{ day.label }}</span>
                                    <div class="badges">
                                        <span class="badge-bg text-gray-200" v-for="badge in day.badges">
                                            <Tooltip><template #content><span v-html="badge.tooltip"></span></template>
                                                <span class="badge" v-html="badge.badge"></span>
                                            </Tooltip>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <!-- events, overlaid -->
                        <div class="week-events">

                            <!-- multi day events for all days first -->
                            <template v-for="day in week">
                                <template v-for="event in day.eventsMultiDay">
                                    <div :class="['nc-event', 'multi', 'nc-col-' + day.weekdayColumn, 'span-' + event.spansDaysN]"
                                        @click="open($event, event.url)" :style="this.stylesForEvent(event)"
                                        v-bind:class="{ 'clickable': event.url, 'starts': event.startsEvent, 'ends': event.endsEvent, 'withinRange': event.isWithinRange }">
                                        <div class="name noscrollbar">{{ event.name }}</div>
                                        <div class="badges noscrollbar">
                                            <span v-if="event.startsEvent" class="badge-bg text-gray-200"
                                                v-for="badge in event.badges"><span class="badge"
                                                    v-html="badge"></span></span>
                                        </div>
                                        <div class="content noscrollbar">
                                            <template v-if="event.startsEvent && event.options.displayTime">
                                                <span class="time">{{ event.startTime }}</span>
                                            </template>
                                            <span v-if="event.startsEvent" class="notes" v-html="event.notes"></span>
                                        </div>
                                    </div>
                                </template>
                            </template>

                            <!-- then all single day events -->
                            <template v-for="day in week">
                                <div :class="['single-day-events', 'nc-col-' + day.weekdayColumn]">
                                    <template v-for="event in day.eventsSingleDay">
                                        <div :class="['nc-event']" @click="open($event, event.url)"
                                            :style="this.stylesForEvent(event)"
                                            v-bind:class="{ 'clickable': event.url, 'starts': event.startsEvent, 'ends': event.endsEvent, 'withinRange': event.isWithinRange }">
                                            <div class="name noscrollbar">{{ event.name }}</div>
                                            <div class="badges">
                                                <span class="badge-bg text-gray-200" v-for="badge in event.badges"><span
                                                        class="badge" v-html="badge"></span></span>
                                            </div>
                                            <div class="content noscrollbar">
                                                <template v-if="event.options.displayTime">
                                                    <span class="time" v-if="event.endTime">{{ event.startTime }} - {{
                                                        event.endTime
                                                    }}</span>
                                                    <span class="time" v-else>{{ event.startTime }}</span>
                                                </template>
                                                <span class="notes" v-html="event.notes"></span>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </template>

                        </div>

                    </div>
                </div>
            </div>
        </Card>
    </div>
</template>

<script>
export default {
    data() {
        return {
            year: null,
            month: null,
            title: '',
            columns: Array(7).fill('-'),
            weeks: Array(6).fill(Array(7).fill({})),
            styles: {
                default: { color: '#fff', 'background-color': 'rgba(var(--colors-primary-500), 0.9)' }
            },
            installerId: null,
            bookingType: [],
            bookingStatus: [],
            selectedInstallers: [],
            bookingTypes: [],
            bookingStatuses: [],
            showFilter: false,
            showInstallerFilter: true,
            installers: [],
            installersInternal: [],
            installersExternal: [],
        }
    },
    methods: {
        reset() {
            if (this.installerId === '') {
                this.installerId = null;
            }
            this.month = (new Date()).getMonth() + 1;
            this.year = (new Date()).getFullYear();
            this.reload();
        },
        prevMonth() {
            this.month -= 1;
            this.reload();
        },
        nextMonth() {
            this.month += 1;
            this.reload();
        },
        reload() {
            let vue = this;

            Nova.request().post('/nova-vendor/wdelfuego/nova-calendar/calendar-data', {
                year: this.year,
                month: this.month,
                installers: this.selectedInstallers,
                bookingType: this.bookingType,
                bookingStatus: this.bookingStatus,
            }).then(response => {
                this.year = response.data.year;
                this.month = response.data.month;
                this.title = response.data.title;
                this.columns = response.data.columns;
                this.weeks = response.data.weeks;
                this.styles = response.data.styles;
            });
        },
        open(e, url) {
            if (e.metaKey || e.ctrlKey) {
                window.open(Nova.url(url))
            } else {
                window.location.href = '/nova' + url;
            }
        },
        stylesForEvent(event) {
            if (event.options.styles) {
                let out = [this.styles.default];
                event.options.styles.forEach(style => {
                    if (this.styles[style] === undefined) {
                        console.log("[nova-calendar] Unknown custom style name '" + style + "'; does the eventStyles method of your CalendarDataProvider define it properly?");
                    } else {
                        out.push(this.styles[style]);
                    }
                })
                return out;
            } else {
                return this.styles.default;
            }
        },
        capitaliseFirstLetter(str) {
            return str[0].toUpperCase() + str.slice(1);
        },
        onEnter(el) {
            el.style.transition = 'height 300ms ease, opacity 300ms ease';
        },
        onAfterEnter(el) {
            // cleanup inline styles so layout is natural after animation
            el.style.transition = '';
            el.style.height = '';
            el.style.opacity = '';
        },
        onBeforeLeave(el) {
            // set current height so we can animate back to 0
            el.style.height = el.scrollHeight + 'px';
            el.style.opacity = '1';
            // force reflow
            // eslint-disable-next-line no-unused-expressions
            el.offsetHeight;
        },
        onLeave(el) {
            el.style.transition = 'height 300ms ease, opacity 300ms ease';
            el.style.height = '0px';
            el.style.opacity = '0';
        },
        isExternalInstaller(installer) {
            const roles = installer && installer.user && Array.isArray(installer.user.roles) ? installer.user.roles : [];
            return roles.some(r => r && r.name === 'external-installer');
        },
    },
    mounted() {
        console.log(this.user);

        this.reset();

        Nova.addShortcut('alt+right', event => {
            this.nextMonth();
        });
        Nova.addShortcut('alt+left', event => {
            this.prevMonth();
        });
        Nova.addShortcut('alt+h', event => {
            this.reset();
        });

        Nova.request().get('/api/installers').then(response => {
            const all = (response.data && response.data.data) ? response.data.data.slice() : [];

            // sort by first_name (case-insensitive), fallback to last_name if needed
            all.sort((a, b) => {
                const aName = (a.first_name || '').toUpperCase();
                const bName = (b.first_name || '').toUpperCase();
                if (aName < bName) return -1;
                if (aName > bName) return 1;
                const aLast = (a.last_name || '').toUpperCase();
                const bLast = (b.last_name || '').toUpperCase();
                if (aLast < bLast) return -1;
                if (aLast > bLast) return 1;
                return 0;
            });
            this.installers = all;
            this.installersInternal = all.filter(i => !this.isExternalInstaller(i));
            this.installersExternal = all.filter(i => this.isExternalInstaller(i));

            this.installers.forEach(installer => {
                if (this.user.id === installer.user_id) {
                    const roles = (installer.user && Array.isArray(installer.user.roles)) ? installer.user.roles : [];
                    const roleNames = roles.map(r => r && r.name).filter(Boolean);

                    const isInstallerType = roleNames.includes('installer') || roleNames.includes('external-installer');
                    const hasManagerRole = roleNames.some(n => ['super', 'admin', 'installation-supervisor'].includes(n));

                    // If user is installer-type ONLY (no manager roles) => hide filter
                    // If user is installer-type AND has a manager role => show filter
                    if (isInstallerType && !hasManagerRole) {
                        this.showInstallerFilter = false;
                    } else if (isInstallerType && hasManagerRole) {
                        this.showInstallerFilter = true;
                    }
                }
            });
        });

        Nova.request().get('/api/booking-statuses').then(response => {
            const n = response.data.data;
            this.bookingStatuses = Object.keys(n)
            this.bookingStatus = [...this.bookingStatuses];
            this.reload()
        });

        Nova.request().get('/api/booking-types').then(response => {
            const x = response.data.data;
            this.bookingTypes = Object.keys(x)
            this.bookingType = [...this.bookingTypes]
            this.reload()
        });
    },
    props: {
        pageTitle: {
            type: String,
            required: false,
            default: 'Nova Calendar',
        },
        user: {
            type: Object,
            required: false,
            default: {},
        },
    },
}
</script>

<style>
/* Scoped Styles */
.filter-section { margin-bottom: 0.75rem; }
.filter-section-title { font-weight: 600; margin-bottom: 0.25rem; opacity: 0.8; }
</style>