<?php

namespace MRB\Contracts;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Contract for reservation email notifications.
 *
 * Depend on this interface so the concrete mailer can be swapped or mocked.
 */
interface NotificationServiceInterface
{
    public function sendReservationCreatedEmails(array $reservation): void;

    public function sendReservationUpdatedEmails(array $reservation): void;

    public function sendReservationCancelledEmails(array $reservation): void;

    public function sendReservationStatusChangedEmails(array $reservation): void;
}
