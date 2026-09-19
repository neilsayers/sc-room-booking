(function () {
    'use strict';

    // No jQuery dependency, deliberately — this runs on the front end
    // of whatever theme this plugin's been dropped into, and jQuery
    // isn't guaranteed to be enqueued there the way it always is in
    // wp-admin (see assets/js/room-types-delete.js for that side's
    // own no-jQuery convention, which this matches).

    document.addEventListener('DOMContentLoaded', init);

    function init() {
        var widgets = document.querySelectorAll('.scrb-booking-widget');

        widgets.forEach(function (widget) {
            setupWidget(widget);
        });
    }

    function setupWidget(widget) {
        var roomId = widget.getAttribute('data-room-id');
        var trigger = widget.querySelector('.scrb-booking-widget-trigger');
        var dialog = widget.querySelector('.scrb-booking-widget-dialog');
        var closeButton = widget.querySelector('.scrb-booking-widget-close');
        var calendarEl = widget.querySelector('.scrb-booking-widget-calendar');
        var hintStart = widget.querySelector('[data-step="pick-start"]');
        var hintEnd = widget.querySelector('[data-step="pick-end"]');
        var form = widget.querySelector('.scrb-booking-widget-form');
        var selectionEl = widget.querySelector('.scrb-booking-widget-selection');
        var errorEl = widget.querySelector('.scrb-booking-widget-error');
        var changeTimesButton = widget.querySelector('.scrb-booking-widget-change-times');
        var successEl = widget.querySelector('.scrb-booking-widget-success');
        var doneButton = widget.querySelector('.scrb-booking-widget-done');
        var submitButton = form ? form.querySelector('.scrb-booking-widget-submit') : null;

        if (!roomId || !trigger || !dialog || !calendarEl) {
            return;
        }

        var calendar = null;
        var minBookingMinutes = 30; // RoomMeta's own default, until the schedule fetch says otherwise.
        var selection = { start: null, end: null };

        trigger.addEventListener('click', function () {
            if (!calendar) {
                calendar = buildCalendar();
                calendar.render();
            }

            dialog.showModal();
        });

        if (closeButton) {
            closeButton.addEventListener('click', function () {
                dialog.close();
            });
        }

        // Click-on-backdrop-to-close — a plain <dialog> doesn't do this
        // itself. event.target is the dialog element only when the
        // click landed outside its content box (its own padding area
        // counts as the backdrop's hit target for a <dialog>).
        dialog.addEventListener('click', function (event) {
            if (event.target === dialog) {
                dialog.close();
            }
        });

        if (doneButton) {
            doneButton.addEventListener('click', function () {
                dialog.close();
            });
        }

        if (changeTimesButton) {
            changeTimesButton.addEventListener('click', function () {
                resetSelection();
            });
        }

        if (form) {
            form.addEventListener('submit', function (event) {
                event.preventDefault();
                submitBooking();
            });
        }

        function buildCalendar() {
            return new window.FullCalendar.Calendar(calendarEl, {
                initialView: 'timeGridWeek',
                headerToolbar: { left: 'prev,next today', center: 'title', right: '' },
                allDaySlot: false,
                nowIndicator: true,
                height: 'auto',
                slotDuration: '00:15:00',
                timeZone: 'local',
                firstDay: 1,
                dateClick: handleDateClick,
                datesSet: handleDatesSet,
            });
        }

        function handleDatesSet(info) {
            fetchSchedule(info.start, info.end);
        }

        function fetchSchedule(rangeStart, rangeEnd) {
            var url = window.scrbBookingWidget.restUrl
                + '/rooms/' + encodeURIComponent(roomId) + '/schedule'
                + '?start=' + encodeURIComponent(formatLocal(rangeStart))
                + '&end=' + encodeURIComponent(formatLocal(rangeEnd));

            fetch(url)
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('schedule request failed');
                    }

                    return response.json();
                })
                .then(function (schedule) {
                    applySchedule(schedule);
                })
                .catch(function () {
                    // Blocked ranges/business hours just don't shade in
                    // this case — the server is still the final word on
                    // whether a chosen slot is actually free (see
                    // submitBooking()), so a failed fetch here degrades
                    // the calendar's usefulness, not its correctness.
                });
        }

        function applySchedule(schedule) {
            if (!calendar) {
                return;
            }

            minBookingMinutes = schedule.min_booking_minutes || minBookingMinutes;

            var startTime = schedule.available_start_time || '09:00';
            var endTime = schedule.available_end_time || '17:00';

            calendar.setOption('businessHours', {
                daysOfWeek: (schedule.available_days || []).map(dayNameToNumber),
                startTime: startTime,
                endTime: endTime,
            });

            // Left at FullCalendar's own default (a full 00:00-24:00
            // day, requiring a lot of scrolling to reach a typical 9-5
            // room's actual hours) until schedule data says otherwise —
            // padded an hour either side of opening hours for context,
            // clamped to a real day.
            calendar.setOption('slotMinTime', clampTime(addMinutesToTimeString(startTime, -60)));
            calendar.setOption('slotMaxTime', clampTime(addMinutesToTimeString(endTime, 60)));

            var existingSource = calendar.getEventSourceById('scrb-blocked');

            if (existingSource) {
                existingSource.remove();
            }

            calendar.addEventSource({
                id: 'scrb-blocked',
                events: (schedule.blocked || []).map(function (range) {
                    return {
                        start: range.start,
                        end: range.end,
                        display: 'background',
                        classNames: ['scrb-slot-blocked'],
                    };
                }),
            });
        }

        function dayNameToNumber(name) {
            var map = { sun: 0, mon: 1, tue: 2, wed: 3, thu: 4, fri: 5, sat: 6 };

            return map.hasOwnProperty(name) ? map[name] : -1;
        }

        function handleDateClick(info) {
            var clicked = info.date;

            // A complete selection already sitting there — clicking
            // again starts over, same forgiving-reselection rule as
            // clicking an earlier time than the current start below.
            if (selection.start && selection.end) {
                resetSelection();
            }

            if (!selection.start) {
                selection.start = clicked;
                showSelectionMarker(clicked, addMinutes(clicked, minBookingMinutes));
                setStep('pick-end');

                return;
            }

            var sameDay = clicked.toDateString() === selection.start.toDateString();

            if (!sameDay || clicked <= selection.start) {
                // Not a valid end for the current start — treat it as a
                // new start instead of leaving the widget stuck.
                selection.start = clicked;
                selection.end = null;
                showSelectionMarker(clicked, addMinutes(clicked, minBookingMinutes));
                setStep('pick-end');

                return;
            }

            var end = clicked;

            if ((end - selection.start) / 60000 < minBookingMinutes) {
                // Rather than reject a too-short tap outright (a dead
                // end the visitor can't obviously recover from), extend
                // it to this room's own minimum automatically.
                end = addMinutes(selection.start, minBookingMinutes);
            }

            selection.end = end;
            showSelectionMarker(selection.start, selection.end);
            showForm();
        }

        function showSelectionMarker(start, end) {
            var existing = calendar.getEventById('scrb-selection');

            if (existing) {
                existing.remove();
            }

            calendar.addEvent({
                id: 'scrb-selection',
                start: start,
                end: end,
                classNames: ['scrb-slot-selected'],
            });
        }

        function setStep(step) {
            if (hintStart) {
                hintStart.hidden = step !== 'pick-start';
            }

            if (hintEnd) {
                hintEnd.hidden = step !== 'pick-end';
            }
        }

        function showForm() {
            if (selectionEl) {
                selectionEl.textContent = formatRangeForDisplay(selection.start, selection.end);
            }

            if (calendarEl) {
                calendarEl.hidden = true;
            }

            if (hintStart) {
                hintStart.hidden = true;
            }

            if (hintEnd) {
                hintEnd.hidden = true;
            }

            if (form) {
                form.hidden = false;
            }

            hideError();
        }

        function resetSelection() {
            selection = { start: null, end: null };

            var existing = calendar ? calendar.getEventById('scrb-selection') : null;

            if (existing) {
                existing.remove();
            }

            if (calendarEl) {
                calendarEl.hidden = false;
            }

            if (form) {
                form.hidden = true;
            }

            setStep('pick-start');
            hideError();
        }

        function submitBooking() {
            if (!selection.start || !selection.end) {
                return;
            }

            var formData = new FormData(form);

            hideError();

            if (submitButton) {
                submitButton.disabled = true;
            }

            fetch(window.scrbBookingWidget.restUrl + '/bookings', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    room_id: parseInt(roomId, 10),
                    start: formatLocal(selection.start),
                    end: formatLocal(selection.end),
                    name: formData.get('name') || '',
                    email: formData.get('email') || '',
                    phone: formData.get('phone') || '',
                    message: formData.get('message') || '',
                }),
            })
                .then(function (response) {
                    return response.json().then(function (body) {
                        return { ok: response.ok, body: body };
                    });
                })
                .then(function (result) {
                    if (submitButton) {
                        submitButton.disabled = false;
                    }

                    if (!result.ok) {
                        showError((result.body && result.body.message) || 'Something went wrong — please try again.');

                        return;
                    }

                    if (form) {
                        form.hidden = true;
                    }

                    if (successEl) {
                        successEl.hidden = false;
                    }
                })
                .catch(function () {
                    if (submitButton) {
                        submitButton.disabled = false;
                    }

                    showError('Something went wrong — please check your connection and try again.');
                });
        }

        function showError(message) {
            if (!errorEl) {
                return;
            }

            errorEl.textContent = message;
            errorEl.hidden = false;
        }

        function hideError() {
            if (!errorEl) {
                return;
            }

            errorEl.hidden = true;
            errorEl.textContent = '';
        }
    }

    function addMinutes(date, minutes) {
        return new Date(date.getTime() + minutes * 60000);
    }

    // Plain "HH:mm" arithmetic for slotMinTime/slotMaxTime padding —
    // deliberately not routed through a Date object, since a Date
    // requires a specific day/DST context that time-of-day maths like
    // this has no business depending on.
    function addMinutesToTimeString(time, minutes) {
        var parts = time.split(':');
        var totalMinutes = parseInt(parts[0], 10) * 60 + parseInt(parts[1], 10) + minutes;

        totalMinutes = Math.max(0, Math.min(24 * 60, totalMinutes));

        return pad(Math.floor(totalMinutes / 60)) + ':' + pad(totalMinutes % 60) + ':00';
    }

    // FullCalendar's slotMinTime/slotMaxTime accept "HH:mm:ss" already
    // clamped to a single day by addMinutesToTimeString() above — this
    // just guards a malformed/empty available_start_time|end_time
    // falling through as something FullCalendar can't parse at all.
    function clampTime(time) {
        return /^\d{2}:\d{2}:\d{2}$/.test(time) ? time : '00:00:00';
    }

    // Built from the Date object's own *local* getters, deliberately —
    // Date#toISOString() always converts to UTC, which would silently
    // shift every time sent to the server unless the visitor's browser
    // and the site's PHP default timezone happen to be the same. The
    // rest of this plugin treats start/end as plain naive wall-clock
    // strings (see scrb_request_booking()), so this matches that
    // exactly instead of introducing a UTC conversion nothing else here
    // expects.
    function formatLocal(date) {
        return date.getFullYear()
            + '-' + pad(date.getMonth() + 1)
            + '-' + pad(date.getDate())
            + 'T' + pad(date.getHours())
            + ':' + pad(date.getMinutes())
            + ':' + pad(date.getSeconds());
    }

    function pad(value) {
        return value < 10 ? '0' + value : String(value);
    }

    function formatRangeForDisplay(start, end) {
        var dateOptions = { weekday: 'long', day: 'numeric', month: 'long' };
        var timeOptions = { hour: '2-digit', minute: '2-digit' };

        return start.toLocaleDateString(undefined, dateOptions)
            + ', ' + start.toLocaleTimeString(undefined, timeOptions)
            + '–' + end.toLocaleTimeString(undefined, timeOptions);
    }
})();
