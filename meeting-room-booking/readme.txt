=== Meeting Room Booking ===
Contributors:      mtaghipoor
Tags:              booking, meeting room, reservation, calendar, scheduling
Requires at least: 6.0
Tested up to:      6.7
Stable tag:        1.0.0
Requires PHP:      7.4
License:           GPLv2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Let visitors reserve meeting rooms from the front end, with admin approval, automatic room allocation, conflict detection, and email notifications.

== Description ==

Meeting Room Booking is a lightweight, MVC-structured plugin that gives your site a fully functional meeting-room reservation workflow:

* **Front-end booking form** via the `[mrb_booking_form]` shortcode with a drag-to-select availability calendar
* **Guest management page** at `/reservation/{token}/` where guests can update or cancel their own bookings
* **Admin reservations list** with search, date, and status filters
* **Admin calendar view** powered by FullCalendar
* **Automatic room allocation** when a reservation is approved
* **Conflict detection** prevents double-booking
* **Email notifications** for every reservation action (created, updated, cancelled, status changed)
* **Translatable** — all strings wrapped in WordPress i18n functions, `.pot` file included

== Installation ==

1. Upload the `meeting-room-booking` folder to `/wp-content/plugins/`.
2. Activate the plugin through **Plugins → Installed Plugins**.
3. The required database tables are created automatically on activation.
4. Add the `[mrb_booking_form]` shortcode to any page.
5. Go to **Meeting Bookings → Settings** to configure the number of rooms.

== Frequently Asked Questions ==

= How do guests manage their reservations? =

After submitting a booking, guests receive a unique management link (shown on screen and in the confirmation email). That link takes them to `/reservation/{token}/` where they can update details or cancel.

= What happens when a reservation is approved? =

The plugin automatically assigns the first available room to the reservation. If no rooms are free for that time slot, the admin sees an error and the status is not changed.

= Can I change the number of rooms after installation? =

Yes. Go to **Meeting Bookings → Settings** and change the number. New rooms are added immediately. Rooms are only removed when they have no existing reservations.

= Is FullCalendar loaded from a CDN? =

No. The FullCalendar library is bundled locally in `assets/vendor/fullcalendar/` so the plugin works completely offline and complies with WordPress.org guidelines.

== Screenshots ==

1. Front-end booking form with the drag-to-select availability calendar.
2. Guest manage-reservation page.
3. Admin reservations list with filters.
4. Admin calendar view.
5. Settings page.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release — no upgrade needed.
