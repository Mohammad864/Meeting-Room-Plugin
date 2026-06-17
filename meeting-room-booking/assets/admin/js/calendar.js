document.addEventListener("DOMContentLoaded", function () {
  const calendarEl = document.getElementById("mrb-calendar");

  if (!calendarEl) {
    return;
  }

  const statusFilter = document.getElementById("mrb-calendar-status-filter");

  const calendar = new FullCalendar.Calendar(calendarEl, {
    initialView: "timeGridWeek",

    headerToolbar: {
      left: "prev,next today",
      center: "title",
      right: "dayGridMonth,timeGridWeek,timeGridDay,listWeek",
    },

    height: "auto",

    slotMinTime: "06:00:00",
    slotMaxTime: "22:00:00",

    nowIndicator: true,

    navLinks: true,

    eventTimeFormat: {
      hour: "2-digit",
      minute: "2-digit",
      hour12: false,
    },

    events: function (info, successCallback, failureCallback) {
      const params = new URLSearchParams();

      params.append("action", "mrb_calendar_events");
      params.append("nonce", MRBCalendar.nonce);
      params.append("start", info.startStr);
      params.append("end", info.endStr);

      if (statusFilter && statusFilter.value) {
        params.append("status", statusFilter.value);
      }

      fetch(MRBCalendar.ajaxUrl + "?" + params.toString(), {
        method: "GET",
        credentials: "same-origin",
      })
        .then(function (response) {
          return response.json();
        })
        .then(function (events) {
          successCallback(events);
        })
        .catch(function (error) {
          console.error("Calendar events error:", error);
          failureCallback(error);
        });
    },

    eventClick: function (info) {
      if (info.event.url) {
        info.jsEvent.preventDefault();
        window.location.href = info.event.url;
      }
    },

    eventDidMount: function (info) {
      const props = info.event.extendedProps;

      let tooltip = "";

      tooltip += "Title: " + info.event.title + "\n";
      tooltip += "Status: " + (props.status || "") + "\n";
      tooltip += "Room: " + (props.room || "") + "\n";

      if (props.mobile) {
        tooltip += "Mobile: " + props.mobile + "\n";
      }

      if (props.email) {
        tooltip += "Email: " + props.email + "\n";
      }

      info.el.setAttribute("title", tooltip);
    },
  });

  calendar.render();

  if (statusFilter) {
    statusFilter.addEventListener("change", function () {
      calendar.refetchEvents();
    });
  }
});
