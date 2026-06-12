# Meeting Room Booking Plugin

A minimal production-ready WordPress plugin for meeting room reservations.

## Features

- Custom database tables
- Frontend booking form via shortcode
- Admin reservations list
- Search by name/mobile
- Filter by date
- Approve/reject workflow
- Room allocation
- Conflict detection
- Minimum required rooms calculation

## Usage

Place this shortcode in any page:

[mrb_booking_form]

## Default Rooms

The plugin creates 3 default rooms on activation.

## Reservation Statuses

- Pending
- Approved
- Rejected

Only approved reservations are considered as occupied slots.

## Security

- Nonce verification
- Capability checks
- Sanitization
- Escaping
- Prepared SQL queries
