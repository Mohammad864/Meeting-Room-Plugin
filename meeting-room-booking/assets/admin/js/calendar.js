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

    loading: function (isLoading) {
      calendarEl.classList.toggle("mrb-calendar-loading", isLoading);
    },

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
          if (!response.ok) {
            throw new Error("Network response was not ok");
          }

          return response.json();
        })
        .then(function (events) {
          successCallback(events);
        })
        .catch(function (error) {
          console.error("MRB Calendar events error:", error);
          failureCallback(error);
        });
    },

    eventClick: function (info) {
      const props = info.event.extendedProps;

      if (info.event.url) {
        info.jsEvent.preventDefault();
        window.location.href = info.event.url;
        return;
      }

      let details = "";

      details += "Reservation Details\n\n";
      details += "Title: " + info.event.title + "\n";

      if (props.room) {
        details += "Room: " + props.room + "\n";
      }

      if (props.status) {
        details += "Status: " + props.status + "\n";
      }

      if (props.mobile) {
        details += "Mobile: " + props.mobile + "\n";
      }

      if (props.email) {
        details += "Email: " + props.email + "\n";
      }

      alert(details);
    },

    eventDidMount: function (info) {
      const props = info.event.extendedProps;

      let tooltip = "";

      tooltip += "Title: " + info.event.title + "\n";

      if (props.status) {
        tooltip += "Status: " + props.status + "\n";
      }

      if (props.room) {
        tooltip += "Room: " + props.room + "\n";
      }

      if (props.mobile) {
        tooltip += "Mobile: " + props.mobile + "\n";
      }

      if (props.email) {
        tooltip += "Email: " + props.email + "\n";
      }

      info.el.setAttribute("title", tooltip);

      /* Optional color by status */

      if (props.status) {
        if (props.status === "confirmed") {
          info.el.style.backgroundColor = "#16a34a";
          info.el.style.borderColor = "#16a34a";
        }

        if (props.status === "pending") {
          info.el.style.backgroundColor = "#f59e0b";
          info.el.style.borderColor = "#f59e0b";
        }

        if (props.status === "cancelled") {
          info.el.style.backgroundColor = "#dc2626";
          info.el.style.borderColor = "#dc2626";
        }
      }
    },
  });

  calendar.render();

  if (statusFilter) {
    statusFilter.addEventListener("change", function () {
      calendar.refetchEvents();
    });
  }
});
