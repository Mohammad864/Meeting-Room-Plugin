# Meeting Room Booking Plugin

A professional WordPress plugin for managing meeting room reservations with frontend booking, admin moderation, automatic room allocation, availability checking, calendar-based scheduling, and capacity-aware conflict handling.

The plugin is designed with a clean service-based architecture, custom database tables, secure WordPress development practices, and scalable booking logic suitable for organizations with one or multiple meeting rooms.

---

## Features

### Frontend Booking

- Shortcode-based booking form
- Public reservation form for website visitors
- Date and time selection
- Calendar-style availability display
- Dynamic booked-slot checking
- Capacity-aware availability handling for multiple rooms
- User-friendly booking confirmation messages

### Reservation Management

- Admin reservations management panel
- View all submitted reservations
- Search reservations by name or mobile number
- Filter reservations by reservation date
- Approve reservations
- Reject reservations
- Pending reservation workflow
- Status-based reservation handling

### Room Management Logic

- Automatic room allocation
- Smart available-room detection
- Multi-room capacity support
- Prevents overbooking when all rooms are occupied
- Allows overlapping reservations when different rooms are available
- Minimum required rooms calculation for capacity planning

### Conflict Detection

- Detects overlapping reservations
- Prevents double booking for the same room
- Only approved reservations block availability
- Supports time-range overlap logic
- Designed for scalable room availability checks

### Admin Calendar

- Calendar-based admin view
- Reservation visualization
- Date-based booking overview
- Easier schedule management for administrators

### Email Notifications

- Reservation-related notification service structure
- Designed for confirmation, approval, and rejection emails

### Security

- Nonce verification for form and AJAX requests
- WordPress capability checks in admin areas
- Input sanitization
- Output escaping
- Prepared SQL queries using $wpdb
- Direct file access protection

---

## Architecture Overview

The plugin follows a layered, object-oriented architecture to keep business logic, database access, and presentation separated.

```text
meeting-room-booking/
│
├── assets/
│   ├── admin/js/
│   └── css/
│
├── includes/
│   ├── Admin/
│   ├── Core/
│   ├── Database/
│   ├── Front/
│   ├── Services/
│   └── Support/
│
├── meeting-room-booking.php
└── uninstall.php
```

---

## Booking Flow

1. User opens a page containing the booking shortcode.
2. Booking form loads available dates and times.
3. User submits reservation details.
4. Data is sanitized and validated.
5. System checks available rooms.
6. Conflict detection runs.
7. Room allocator selects an available room.
8. Reservation is saved as **pending**.
9. Admin reviews the reservation.
10. Admin approves or rejects the booking.

---

## Shortcode

```
[mrb_booking_form]
```

Add the shortcode to any page to display the booking form.

---

## Installation

1. Upload plugin to:

```
wp-content/plugins/meeting-room-booking
```

2. Activate the plugin in the WordPress admin panel.

3. Create a page and add:

```
[mrb_booking_form]
```

---

## Default Rooms

When the plugin is activated it automatically creates:

```
3 default meeting rooms
```

These rooms are used by the automatic allocation system.

---

## Reservation Status

| Status | Description | Blocks Availability |
|------|-------------|---------------------|
| pending | Waiting for admin approval | No |
| approved | Confirmed reservation | Yes |
| rejected | Cancelled reservation | No |

Only **approved** reservations block room availability.

---

## Availability Logic

A time slot becomes unavailable only when:

```
overlapping_reservations >= total_rooms
```

Example with 3 rooms:

| Reservations | Slot Status |
|--------------|-------------|
| 0 | Available |
| 1 | Available |
| 2 | Available |
| 3 | Fully booked |

---

## Core Algorithm

### Time Conflict Detection

```
start_time < existing_end_time
AND
end_time > existing_start_time
```

Equivalent PHP example:

```php
$overlap = $start < $existingEnd && $end > $existingStart;
```

---

### Room Allocation

1. Get all rooms
2. Check each room for conflicts
3. Return the first available room
4. If none available → booking cannot be allocated

---

## Database Tables

### Reservations Table

```
wp_mrb_reservations
```

Fields include:

- id
- room_id
- full_name
- mobile
- meeting_title
- meeting_date
- start_time
- end_time
- status
- created_at

---

### Rooms Table

```
wp_mrb_rooms
```

Fields:

- id
- name

---

## Security

The plugin follows WordPress security best practices:

- Nonce verification
- Capability checks
- Input sanitization
- Output escaping
- Prepared SQL queries
- Direct file access protection

---

## Requirements

| Requirement | Version |
|-------------|--------|
| WordPress | 6.0+ |
| PHP | 8.0+ |
| MySQL | 5.7+ |

---

## Tech Stack

- WordPress Plugin API
- PHP 8+
- MySQL
- Object‑oriented PHP
- Service‑based architecture

---

## Author

Mohammad Taghipoor  
WordPress Developer

---

## License

GPL v2 or later
