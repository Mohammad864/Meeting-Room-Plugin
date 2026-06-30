/**
 * Booking Form — interactive availability calendar and form validation.
 *
 * Depends on jQuery (WordPress bundled).
 * Configuration is injected via window.MRBBookingForm (set by Assets.php):
 *   window.MRBBookingForm.ajaxUrl  — WordPress admin-ajax.php URL
 *   window.MRBBookingForm.nonce    — Nonce for mrb_get_booked_times_range
 */
(function ($) {
  "use strict";

  var bookedSlotsByDate = {};
  var daysByDate = {};
  var currentSelectedDate = "";
  var totalRooms = 1;

  var slotMinutes = 30;
  var slotHeight = 24;
  var totalMinutes = 24 * 60;
  var timelineHeight = (totalMinutes / slotMinutes) * slotHeight;

  var isDragging = false;
  var dragStartMinutes = null;
  var dragCurrentMinutes = null;

  // ── Utility helpers ───────────────────────────────────────────────────────

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function timeToMinutes(time) {
    if (!time || time.indexOf(":") === -1) {
      return null;
    }
    var parts = time.split(":");
    var hours = parseInt(parts[0], 10);
    var minutes = parseInt(parts[1], 10);
    if (isNaN(hours) || isNaN(minutes)) {
      return null;
    }
    return hours * 60 + minutes;
  }

  function minutesToInputTime(minutes) {
    minutes = Math.max(0, Math.min(totalMinutes, minutes));
    if (minutes >= totalMinutes) {
      return "23:59";
    }
    var h = Math.floor(minutes / 60);
    var m = minutes % 60;
    return String(h).padStart(2, "0") + ":" + String(m).padStart(2, "0");
  }

  function minutesToLabel(minutes) {
    minutes = Math.max(0, Math.min(totalMinutes, minutes));
    if (minutes === totalMinutes) {
      return "24:00";
    }
    var h = Math.floor(minutes / 60);
    var m = minutes % 60;
    return String(h).padStart(2, "0") + ":" + String(m).padStart(2, "0");
  }

  function roundToSlot(m) {
    return Math.round(m / slotMinutes) * slotMinutes;
  }
  function floorToSlot(m) {
    return Math.floor(m / slotMinutes) * slotMinutes;
  }
  function ceilToSlot(m) {
    return Math.ceil(m / slotMinutes) * slotMinutes;
  }
  function clampMinutes(m) {
    return Math.max(0, Math.min(totalMinutes, m));
  }

  // ── Conflict detection ────────────────────────────────────────────────────

  function getCurrentBookedSlots() {
    var date = $("#mrb_meeting_date").val();
    return !date || !bookedSlotsByDate[date] ? [] : bookedSlotsByDate[date];
  }

  function hasConflict(startTime, endTime) {
    var slots = getCurrentBookedSlots();
    var selectedStart = timeToMinutes(startTime);
    var selectedEnd = timeToMinutes(endTime);
    if (selectedStart === null || selectedEnd === null) {
      return false;
    }

    var overlapCount = 0;
    for (var i = 0; i < slots.length; i++) {
      var s = timeToMinutes(slots[i].start_time);
      var e = timeToMinutes(slots[i].end_time);
      if (s === null || e === null) {
        continue;
      }
      if (selectedStart < e && selectedEnd > s) {
        overlapCount++;
      }
    }
    return overlapCount >= totalRooms;
  }

  function hasConflictByMinutes(startMinutes, endMinutes) {
    var slots = bookedSlotsByDate[currentSelectedDate] || [];
    if (endMinutes <= startMinutes) {
      return true;
    }

    var overlapCount = 0;
    for (var i = 0; i < slots.length; i++) {
      var s = timeToMinutes(slots[i].start_time);
      var e = timeToMinutes(slots[i].end_time);
      if (s === null || e === null) {
        continue;
      }
      if (startMinutes < e && endMinutes > s) {
        overlapCount++;
      }
    }
    return overlapCount >= totalRooms;
  }

  // ── UI feedback ───────────────────────────────────────────────────────────

  function showFormFeedback(message, type) {
    var $box = $("#mrb-form-feedback");
    type = type || "error";
    $box
      .removeClass("is-error is-success is-info")
      .addClass("is-" + type)
      .html(escapeHtml(message))
      .show();
    if ($box.length) {
      $("html, body").animate(
        { scrollTop: Math.max(0, $box.offset().top - 120) },
        250,
      );
    }
  }

  function hideFormFeedback() {
    $("#mrb-form-feedback").hide().empty();
  }

  function updateSelectedTimeDisplay() {
    var startTime = $("#mrb_start_time").val();
    var endTime = $("#mrb_end_time").val();
    if (!startTime || !endTime) {
      $("#mrb-selected-time-text").text("No time selected yet");
      $("#mrb-selected-time-help").text(
        "Use the availability calendar to choose a free slot.",
      );
      $("#mrb-selected-time-box").removeClass("has-time has-error");
      $("#mrb-clear-time").hide();
      return;
    }
    $("#mrb-selected-time-text").text(startTime + " - " + endTime);
    $("#mrb-selected-time-help").text(
      "Selected from the availability calendar.",
    );
    $("#mrb-selected-time-box").addClass("has-time").removeClass("has-error");
    $("#mrb-clear-time").show();
  }

  function updateConflictWarning() {
    var startTime = $("#mrb_start_time").val();
    var endTime = $("#mrb_end_time").val();
    if (!startTime || !endTime) {
      $("#mrb-time-conflict-warning").hide();
      $("#mrb-selected-time-box").removeClass("has-error");
      return false;
    }
    if (hasConflict(startTime, endTime)) {
      $("#mrb-time-conflict-warning").show();
      $("#mrb-selected-time-box").addClass("has-error");
      return true;
    }
    $("#mrb-time-conflict-warning").hide();
    $("#mrb-selected-time-box").removeClass("has-error");
    return false;
  }

  function clearSelectedTime() {
    $("#mrb_start_time").val("");
    $("#mrb_end_time").val("");
    updateSelectedTimeDisplay();
    updateConflictWarning();
  }

  // ── Calendar modal ────────────────────────────────────────────────────────

  function openCalendarModal() {
    $("#mrb-calendar-modal").addClass("is-open").attr("aria-hidden", "false");
    $("body").addClass("mrb-calendar-open");
    hideFormFeedback();
    setTimeout(function () {
      $("#mrb-calendar-close").trigger("focus");
    }, 50);
  }

  function closeCalendarModal() {
    $("#mrb-calendar-modal").removeClass("is-open").attr("aria-hidden", "true");
    $("body").removeClass("mrb-calendar-open");
    clearSelectionLayer();
    setTimeout(function () {
      $("#mrb-open-calendar").trigger("focus");
    }, 50);
  }

  function showCalendarError(message) {
    $("#mrb-calendar-error-box").html(escapeHtml(message)).show();
  }

  function hideCalendarError() {
    $("#mrb-calendar-error-box").hide().empty();
  }

  function getAjaxErrorMessage(response) {
    return response && response.data && response.data.message
      ? response.data.message
      : "Could not load availability calendar.";
  }

  // ── Day buttons ───────────────────────────────────────────────────────────

  function renderDayButtons(days) {
    var html = "";
    days.forEach(function (day) {
      var selectedClass =
        day.date === currentSelectedDate ? " is-selected" : "";
      var count = day.slots ? day.slots.length : 0;
      html +=
        '<button type="button" class="mrb-calendar-day-btn' +
        selectedClass +
        '" data-date="' +
        escapeHtml(day.date) +
        '">';
      html +=
        '<span class="mrb-calendar-day-name">' +
        escapeHtml(day.day_label) +
        "</span>";
      html +=
        '<span class="mrb-calendar-day-date">' +
        escapeHtml(day.month_label) +
        "</span>";
      html +=
        count > 0
          ? '<span class="mrb-calendar-day-count">' +
            count +
            " booking" +
            (count > 1 ? "s" : "") +
            "</span>"
          : '<span class="mrb-calendar-day-count is-free">Fully available</span>';
      html += "</button>";
    });
    $("#mrb-calendar-days").html(html);
  }

  // ── Timeline ──────────────────────────────────────────────────────────────

  function buildTimelineBase() {
    var labelsHtml = "";
    var gridHtml = "";
    for (var hour = 0; hour <= 24; hour++) {
      var top = ((hour * 60) / slotMinutes) * slotHeight;
      labelsHtml +=
        '<div class="mrb-hour-label" style="top:' +
        top +
        'px;">' +
        (hour === 24 ? "24:00" : String(hour).padStart(2, "0") + ":00") +
        "</div>";
      if (hour < 24) {
        gridHtml +=
          '<div class="mrb-hour-line" style="top:' + top + 'px;"></div>';
      }
    }
    for (var half = 30; half < totalMinutes; half += 60) {
      gridHtml +=
        '<div class="mrb-half-hour-line" style="top:' +
        (half / slotMinutes) * slotHeight +
        'px;"></div>';
    }
    $("#mrb-google-timeline").css("height", timelineHeight + "px");
    $("#mrb-timeline-labels")
      .html(labelsHtml)
      .css("height", timelineHeight + "px");
    $("#mrb-timeline-grid").css("height", timelineHeight + "px");
    $(
      "#mrb-timeline-grid .mrb-hour-line, #mrb-timeline-grid .mrb-half-hour-line",
    ).remove();
    $("#mrb-timeline-grid").prepend(gridHtml);
  }

  function renderBookedBlocks(date) {
    var slots = bookedSlotsByDate[date] || [];
    var html = "";
    slots.forEach(function (slot) {
      var start = clampMinutes(timeToMinutes(slot.start_time));
      var end = clampMinutes(timeToMinutes(slot.end_time));
      if (start === null || end === null || end <= start) {
        return;
      }
      var top = (start / slotMinutes) * slotHeight;
      var height = Math.max(
        slotHeight,
        ((end - start) / slotMinutes) * slotHeight,
      );
      html +=
        '<div class="mrb-booked-block" style="top:' +
        top +
        "px;height:" +
        height +
        'px;">';
      html +=
        "<strong>Booked</strong><span>" +
        escapeHtml(slot.start_time) +
        " - " +
        escapeHtml(slot.end_time) +
        "</span>";
      html += "</div>";
    });
    $("#mrb-booked-layer").html(html);
  }

  function renderCurrentDay(date) {
    currentSelectedDate = date;
    var day = daysByDate[date];
    if (!day) {
      return;
    }
    $("#mrb_meeting_date").val(date);
    $(".mrb-calendar-day-btn").removeClass("is-selected");
    $('.mrb-calendar-day-btn[data-date="' + date + '"]').addClass(
      "is-selected",
    );
    $("#mrb-current-day-title").text(day.full_label || date);
    $("#mrb-current-day-subtitle").text(
      " — drag on an empty area to choose your time",
    );
    $("#mrb-current-selection-label").text("Drag to select");
    buildTimelineBase();
    renderBookedBlocks(date);
    clearSelectionLayer();
    setTimeout(function () {
      $("#mrb-google-timeline-scroll").scrollTop(
        ((7 * 60) / slotMinutes) * slotHeight,
      );
    }, 50);
    updateConflictWarning();
  }

  function renderCalendar(responseData) {
    var days = responseData.days || [];
    bookedSlotsByDate = {};
    daysByDate = {};
    totalRooms = parseInt(responseData.total_rooms || 1, 10);
    if (!days.length) {
      showCalendarError("No calendar data found.");
      return;
    }
    days.forEach(function (day) {
      bookedSlotsByDate[day.date] = day.slots || [];
      daysByDate[day.date] = day;
    });
    currentSelectedDate = responseData.selected_date || days[0].date;
    renderDayButtons(days);
    renderCurrentDay(currentSelectedDate);
  }

  // ── AJAX fetch ────────────────────────────────────────────────────────────

  function fetchCalendar(date) {
    if (!date) {
      showFormFeedback("Please select a date first.", "error");
      $("#mrb_meeting_date").trigger("focus");
      return;
    }
    hideCalendarError();
    openCalendarModal();
    $("#mrb-calendar-loading").show();
    $("#mrb-calendar-days").html("Loading days...");
    $("#mrb-booked-layer").empty();
    $("#mrb-selection-layer").empty();

    if (
      !window.MRBBookingForm ||
      !window.MRBBookingForm.ajaxUrl ||
      !window.MRBBookingForm.nonce
    ) {
      $("#mrb-calendar-loading").hide();
      showCalendarError("AJAX configuration is missing.");
      return;
    }

    $.ajax({
      url: window.MRBBookingForm.ajaxUrl,
      type: "POST",
      dataType: "json",
      data: {
        action: "mrb_get_booked_times_range",
        nonce: window.MRBBookingForm.nonce,
        date: date,
      },
    })
      .done(function (response) {
        $("#mrb-calendar-loading").hide();
        if (!response || !response.success) {
          bookedSlotsByDate = {};
          showCalendarError(getAjaxErrorMessage(response));
          updateConflictWarning();
          return;
        }
        renderCalendar(response.data);
      })
      .fail(function (xhr) {
        $("#mrb-calendar-loading").hide();
        var message = "Could not load availability calendar.";
        if (xhr && xhr.responseText) {
          message += " Server response: " + xhr.responseText.substring(0, 300);
        }
        bookedSlotsByDate = {};
        showCalendarError(message);
        updateConflictWarning();
      });
  }

  // ── Drag-to-select ────────────────────────────────────────────────────────

  function eventPageY(event) {
    var original = event.originalEvent || event;
    if (original.touches && original.touches.length) {
      return original.touches[0].pageY;
    }
    if (original.changedTouches && original.changedTouches.length) {
      return original.changedTouches[0].pageY;
    }
    return event.pageY;
  }

  function minutesFromEvent(event) {
    var $grid = $("#mrb-timeline-grid");
    var y = Math.max(
      0,
      Math.min(timelineHeight, eventPageY(event) - $grid.offset().top),
    );
    return clampMinutes(roundToSlot((y / slotHeight) * slotMinutes));
  }

  function clearSelectionLayer() {
    $("#mrb-selection-layer").empty();
    dragStartMinutes = null;
    dragCurrentMinutes = null;
    isDragging = false;
  }

  function drawSelection(startMinutes, endMinutes, isInvalid) {
    var top = floorToSlot(Math.min(startMinutes, endMinutes));
    var bottom = ceilToSlot(Math.max(startMinutes, endMinutes));
    if (bottom <= top) {
      bottom = top + slotMinutes;
    }
    bottom = clampMinutes(bottom);
    var topPx = (top / slotMinutes) * slotHeight;
    var heightPx = Math.max(
      slotHeight,
      ((bottom - top) / slotMinutes) * slotHeight,
    );
    var html =
      '<div class="mrb-selection-block' +
      (isInvalid ? " is-invalid" : "") +
      '" style="top:' +
      topPx +
      "px;height:" +
      heightPx +
      'px;">';
    html +=
      "<strong>" +
      minutesToLabel(top) +
      " - " +
      minutesToLabel(bottom) +
      "</strong>";
    html += isInvalid
      ? "<span>Conflicts with booked time</span>"
      : "<span>Release to choose</span>";
    html += "</div>";
    $("#mrb-selection-layer").html(html);
    $("#mrb-current-selection-label").text(
      minutesToLabel(top) + " - " + minutesToLabel(bottom),
    );
  }

  function commitSelection() {
    if (dragStartMinutes === null || dragCurrentMinutes === null) {
      clearSelectionLayer();
      return;
    }
    var start = clampMinutes(
      floorToSlot(Math.min(dragStartMinutes, dragCurrentMinutes)),
    );
    var end = clampMinutes(
      ceilToSlot(Math.max(dragStartMinutes, dragCurrentMinutes)),
    );
    if (end <= start) {
      end = start + slotMinutes;
    }
    if (end <= start) {
      clearSelectionLayer();
      return;
    }

    if (hasConflictByMinutes(start, end)) {
      drawSelection(start, end, true);
      showCalendarError(
        "This selected time overlaps with an existing reservation. Please select an empty time.",
      );
      return;
    }
    $("#mrb_start_time").val(minutesToInputTime(start));
    $("#mrb_end_time").val(minutesToInputTime(end));
    updateSelectedTimeDisplay();
    updateConflictWarning();
    closeCalendarModal();
  }

  // ── Form validation ───────────────────────────────────────────────────────

  function validateFormBeforeSubmit() {
    hideFormFeedback();
    var required = [
      ["#mrb_meeting_title", "Please enter a meeting title."],
      ["#mrb_meeting_date", "Please select a meeting date."],
      ["#mrb_first_name", "Please enter your first name."],
      ["#mrb_last_name", "Please enter your last name."],
      ["#mrb_email", "Please enter your email address."],
      ["#mrb_mobile", "Please enter your mobile number."],
    ];
    for (var i = 0; i < required.length; i++) {
      if (!$(required[i][0]).val()) {
        showFormFeedback(required[i][1], "error");
        $(required[i][0]).trigger("focus");
        return false;
      }
    }
    if (!$("#mrb_start_time").val() || !$("#mrb_end_time").val()) {
      showFormFeedback(
        "Please choose an available time from the calendar.",
        "error",
      );
      $("#mrb-open-calendar").trigger("focus");
      $("#mrb-selected-time-box").addClass("has-error");
      return false;
    }
    if (updateConflictWarning()) {
      showFormFeedback(
        "The selected time overlaps with an existing reservation. Please choose another time.",
        "error",
      );
      $("#mrb-open-calendar").trigger("focus");
      return false;
    }
    return true;
  }

  // ── Step navigation ───────────────────────────────────────────────────────

  function setActiveStep(step) {
    $(".mrb-step").removeClass("is-active");
    $('.mrb-step[data-target="' + step + '"]').addClass("is-active");
  }

  function scrollToBookingSection(step) {
    var map = {
      meeting: "#mrb-section-meeting",
      time: "#mrb-section-time",
      contact: "#mrb-section-contact",
    };
    var sel = map[step];
    if (!sel || !$(sel).length) {
      return;
    }
    setActiveStep(step);
    $("html, body").animate(
      { scrollTop: Math.max(0, $(sel).offset().top - 120) },
      300,
    );
  }

  // ── Event listeners ───────────────────────────────────────────────────────

  $(document).on("click", "#mrb-open-calendar", function () {
    var date = $("#mrb_meeting_date").val();
    if (!date) {
      showFormFeedback("Please select a date first.", "error");
      $("#mrb_meeting_date").trigger("focus");
      return;
    }
    fetchCalendar(date);
  });

  $(document).on("change", "#mrb_meeting_date", function () {
    clearSelectedTime();
    bookedSlotsByDate = {};
    daysByDate = {};
    currentSelectedDate = "";
  });

  $(document).on("click", "#mrb-clear-time", function () {
    clearSelectedTime();
  });

  $(document).on("click", ".mrb-calendar-day-btn", function () {
    var date = $(this).data("date");
    if (!date) {
      return;
    }
    clearSelectedTime();
    renderCurrentDay(date);
  });

  $(document).on(
    "mousedown touchstart",
    "#mrb-timeline-grid",
    function (event) {
      if (!currentSelectedDate) {
        return;
      }
      if ($(event.target).closest(".mrb-booked-block").length) {
        return;
      }
      event.preventDefault();
      hideCalendarError();
      isDragging = true;
      dragStartMinutes = minutesFromEvent(event);
      dragCurrentMinutes = dragStartMinutes + slotMinutes;
      drawSelection(dragStartMinutes, dragCurrentMinutes, false);
    },
  );

  $(document).on("mousemove touchmove", function (event) {
    if (!isDragging) {
      return;
    }
    event.preventDefault();
    dragCurrentMinutes = minutesFromEvent(event);
    if (dragCurrentMinutes === dragStartMinutes) {
      dragCurrentMinutes = dragStartMinutes + slotMinutes;
    }
    var start = floorToSlot(Math.min(dragStartMinutes, dragCurrentMinutes));
    var end = ceilToSlot(Math.max(dragStartMinutes, dragCurrentMinutes));
    if (end <= start) {
      end = start + slotMinutes;
    }
    drawSelection(start, end, hasConflictByMinutes(start, end));
  });

  $(document).on("mouseup touchend", function () {
    if (!isDragging) {
      return;
    }
    isDragging = false;
    commitSelection();
  });

  $(document).on(
    "click",
    "#mrb-calendar-close, .mrb-calendar-modal-backdrop",
    function () {
      closeCalendarModal();
    },
  );

  $(document).on("keydown", function (event) {
    if (event.key === "Escape") {
      closeCalendarModal();
    }
  });

  $(document).on("submit", "#mrb-booking-form", function (event) {
    if (!validateFormBeforeSubmit()) {
      event.preventDefault();
      return false;
    }
    $("#mrb-submit-btn").prop("disabled", true).addClass("is-loading");
    $("#mrb-submit-btn .mrb-submit-label").hide();
    $("#mrb-submit-btn .mrb-submit-loading").show();
    return true;
  });

  $(document).on("click", ".mrb-copy-link-btn", function () {
    var text = $($(this).data("copy-target")).text();
    if (!text) {
      return;
    }
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(function () {
        $("#mrb-copy-feedback").fadeIn(150).delay(1500).fadeOut(200);
      });
    } else {
      var $tmp = $("<input>").val(text).appendTo("body").select();
      document.execCommand("copy");
      $tmp.remove();
      $("#mrb-copy-feedback").fadeIn(150).delay(1500).fadeOut(200);
    }
  });

  $(document).on("click", ".mrb-step", function () {
    var target = $(this).data("target");
    if (target) {
      scrollToBookingSection(target);
    }
  });

  // ── Init ──────────────────────────────────────────────────────────────────

  updateSelectedTimeDisplay();
})(jQuery);
