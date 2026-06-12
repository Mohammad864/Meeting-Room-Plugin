# Meeting Room Booking Plugin

A production-ready WordPress plugin for managing meeting room reservations with smart room allocation, conflict detection, and admin moderation.

---

## Features

- Custom database tables
- Frontend booking form via shortcode
- Admin reservations management panel
- Search reservations by name/mobile
- Filter reservations by date
- Approve / reject workflow
- Automatic room allocation system
- Conflict detection (prevents double booking)
- Minimum required rooms calculation (capacity planning)
- Scalable service-based architecture

---

## Architecture

### Services Layer
- ReservationService (business logic)
- RoomAllocator (room assignment)
- ConflictDetector (overlap prevention)
- MinimumRoomsCalculator (capacity planning)

### Database Layer
- ReservationRepository
- RoomRepository

### Presentation Layer
- Shortcode-based frontend form
- WordPress admin dashboard

---

## Booking Flow

1. User submits booking form
2. Data is sanitized and validated
3. System checks available rooms
4. Conflict detection is applied
5. Room is automatically assigned
6. Reservation is stored as pending
7. Admin approves or rejects booking

---

## Installation

1. Upload plugin to:
wp-content/plugins/meeting-room-booking

2. Activate plugin from WordPress admin panel

3. Create a page and add shortcode:

[mrb_booking_form]

---

## Default Rooms

On activation, the plugin creates 3 default meeting rooms automatically.

---

## Reservation Statuses

- pending → waiting for approval
- approved → confirmed and occupies time slot
- rejected → cancelled reservation

Only approved reservations block room availability.

---

## Core Algorithm

### Conflict Detection

start_time < existing_end_time  
AND end_time > existing_start_time

---

### Room Allocation
- Loop through available rooms
- Return first available room
- If none available → reject

---

### Minimum Rooms Calculation
Calculates peak simultaneous bookings for capacity planning.

---

## Database Tables

### wp_mrb_reservations
- user info
- meeting details
- room_id
- status
- timestamps

### wp_mrb_rooms
- id
- name

---

## Usage

[mrb_booking_form]

---

## Security

- Nonce verification
- Capability checks
- Input sanitization
- Output escaping
- Prepared SQL queries

---

## Tech Stack

- WordPress Plugin API
- PHP 8+
- MySQL ($wpdb)
- OOP architecture
- Service-based design

---

## Future Improvements

- AJAX booking (no reload)
- Calendar UI
- Email notifications
- Google Calendar sync
- REST API support
- Admin analytics dashboard

---

## Author

Mohammad Taghipoor  
WordPress Developer

---

## License

GPL v2 or later
