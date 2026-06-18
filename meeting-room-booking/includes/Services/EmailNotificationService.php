<?php

namespace MRB\Services;

if (!defined('ABSPATH')) {
    exit;
}

class EmailNotificationService
{
    /**
     * Send notification after a new reservation is created.
     */
    public function sendReservationCreatedEmails(array $reservation): void
    {
        $this->sendUserReservationCreatedEmail($reservation);
        $this->sendAdminReservationCreatedEmail($reservation);
    }

    /**
     * Send notification after a reservation is updated.
     */
    public function sendReservationUpdatedEmails(array $reservation): void
    {
        $this->sendUserReservationUpdatedEmail($reservation);
        $this->sendAdminReservationUpdatedEmail($reservation);
    }

    /**
     * Send notification after a reservation is cancelled.
     */
    public function sendReservationCancelledEmails(array $reservation): void
    {
        $this->sendUserReservationCancelledEmail($reservation);
        $this->sendAdminReservationCancelledEmail($reservation);
    }

    /**
     * Send notification after admin changes reservation status.
     */
    public function sendReservationStatusChangedEmails(array $reservation): void
    {
        $this->sendUserReservationStatusChangedEmail($reservation);
        $this->sendAdminReservationStatusChangedEmail($reservation);
    }

    /**
     * User email: reservation created.
     */
    private function sendUserReservationCreatedEmail(array $reservation): void
    {
        $email = $this->getReservationEmail($reservation);

        if (!$email) {
            return;
        }

        $subject = sprintf(
            __('Your reservation request has been received - %s', 'meeting-room-booking'),
            get_bloginfo('name')
        );

        $message = $this->buildEmailTemplate(
            __('Reservation Request Received', 'meeting-room-booking'),
            sprintf(
                __('Hello %s,', 'meeting-room-booking'),
                esc_html($this->getReservationName($reservation))
            ),
            [
                __('Thank you. Your meeting room reservation request has been received.', 'meeting-room-booking'),
                __('We will notify you once the reservation status changes.', 'meeting-room-booking'),
            ],
            $reservation
        );

        $this->sendEmail($email, $subject, $message);
    }

    /**
     * Admin email: reservation created.
     */
    private function sendAdminReservationCreatedEmail(array $reservation): void
    {
        $adminEmail = $this->getAdminNotificationEmail();

        if (!$adminEmail) {
            return;
        }

        $subject = sprintf(
            __('New meeting room reservation - %s', 'meeting-room-booking'),
            get_bloginfo('name')
        );

        $message = $this->buildEmailTemplate(
            __('New Reservation Created', 'meeting-room-booking'),
            __('A new meeting room reservation has been submitted.', 'meeting-room-booking'),
            [
                __('Please review the reservation details below.', 'meeting-room-booking'),
            ],
            $reservation,
            true
        );

        $this->sendEmail($adminEmail, $subject, $message);
    }

    /**
     * User email: reservation updated.
     */
    private function sendUserReservationUpdatedEmail(array $reservation): void
    {
        $email = $this->getReservationEmail($reservation);

        if (!$email) {
            return;
        }

        $subject = sprintf(
            __('Your reservation has been updated - %s', 'meeting-room-booking'),
            get_bloginfo('name')
        );

        $message = $this->buildEmailTemplate(
            __('Reservation Updated', 'meeting-room-booking'),
            sprintf(
                __('Hello %s,', 'meeting-room-booking'),
                esc_html($this->getReservationName($reservation))
            ),
            [
                __('Your meeting room reservation has been updated successfully.', 'meeting-room-booking'),
                __('The updated reservation details are shown below.', 'meeting-room-booking'),
            ],
            $reservation
        );

        $this->sendEmail($email, $subject, $message);
    }

    /**
     * Admin email: reservation updated.
     */
    private function sendAdminReservationUpdatedEmail(array $reservation): void
    {
        $adminEmail = $this->getAdminNotificationEmail();

        if (!$adminEmail) {
            return;
        }

        $subject = sprintf(
            __('Reservation updated - %s', 'meeting-room-booking'),
            get_bloginfo('name')
        );

        $message = $this->buildEmailTemplate(
            __('Reservation Updated', 'meeting-room-booking'),
            __('A meeting room reservation has been updated.', 'meeting-room-booking'),
            [
                __('The updated reservation details are shown below.', 'meeting-room-booking'),
            ],
            $reservation,
            true
        );

        $this->sendEmail($adminEmail, $subject, $message);
    }

    /**
     * User email: reservation cancelled.
     */
    private function sendUserReservationCancelledEmail(array $reservation): void
    {
        $email = $this->getReservationEmail($reservation);

        if (!$email) {
            return;
        }

        $subject = sprintf(
            __('Your reservation has been cancelled - %s', 'meeting-room-booking'),
            get_bloginfo('name')
        );

        $message = $this->buildEmailTemplate(
            __('Reservation Cancelled', 'meeting-room-booking'),
            sprintf(
                __('Hello %s,', 'meeting-room-booking'),
                esc_html($this->getReservationName($reservation))
            ),
            [
                __('Your meeting room reservation has been cancelled.', 'meeting-room-booking'),
            ],
            $reservation
        );

        $this->sendEmail($email, $subject, $message);
    }

    /**
     * Admin email: reservation cancelled.
     */
    private function sendAdminReservationCancelledEmail(array $reservation): void
    {
        $adminEmail = $this->getAdminNotificationEmail();

        if (!$adminEmail) {
            return;
        }

        $subject = sprintf(
            __('Reservation cancelled - %s', 'meeting-room-booking'),
            get_bloginfo('name')
        );

        $message = $this->buildEmailTemplate(
            __('Reservation Cancelled', 'meeting-room-booking'),
            __('A meeting room reservation has been cancelled.', 'meeting-room-booking'),
            [
                __('The cancelled reservation details are shown below.', 'meeting-room-booking'),
            ],
            $reservation,
            true
        );

        $this->sendEmail($adminEmail, $subject, $message);
    }

    /**
     * User email: reservation status changed.
     */
    private function sendUserReservationStatusChangedEmail(array $reservation): void
    {
        $email = $this->getReservationEmail($reservation);

        if (!$email) {
            return;
        }

        $status = $this->getReservationStatus($reservation);

        $subject = sprintf(
            __('Your reservation status is now %s - %s', 'meeting-room-booking'),
            ucfirst($status),
            get_bloginfo('name')
        );

        $message = $this->buildEmailTemplate(
            __('Reservation Status Updated', 'meeting-room-booking'),
            sprintf(
                __('Hello %s,', 'meeting-room-booking'),
                esc_html($this->getReservationName($reservation))
            ),
            [
                sprintf(
                    __('Your reservation status has been changed to: %s', 'meeting-room-booking'),
                    '<strong>' . esc_html(ucfirst($status)) . '</strong>'
                ),
            ],
            $reservation
        );

        $this->sendEmail($email, $subject, $message);
    }

    /**
     * Admin email: reservation status changed.
     */
    private function sendAdminReservationStatusChangedEmail(array $reservation): void
    {
        $adminEmail = $this->getAdminNotificationEmail();

        if (!$adminEmail) {
            return;
        }

        $status = $this->getReservationStatus($reservation);

        $subject = sprintf(
            __('Reservation status changed to %s - %s', 'meeting-room-booking'),
            ucfirst($status),
            get_bloginfo('name')
        );

        $message = $this->buildEmailTemplate(
            __('Reservation Status Changed', 'meeting-room-booking'),
            sprintf(
                __('A reservation status has been changed to: %s', 'meeting-room-booking'),
                '<strong>' . esc_html(ucfirst($status)) . '</strong>'
            ),
            [
                __('The reservation details are shown below.', 'meeting-room-booking'),
            ],
            $reservation,
            true
        );

        $this->sendEmail($adminEmail, $subject, $message);
    }

    /**
     * Build HTML email template.
     */
    private function buildEmailTemplate(
        string $title,
        string $intro,
        array $paragraphs,
        array $reservation,
        bool $includeAdminLink = false
    ): string {
        $siteName = get_bloginfo('name');

        $detailsRows = $this->buildReservationDetailsRows($reservation);

        $adminLinkHtml = '';

        if ($includeAdminLink && !empty($reservation['id'])) {
            $adminUrl = admin_url('admin.php?page=mrb-reservations&action=edit&id=' . absint($reservation['id']));

            $adminLinkHtml = '
                <p style="margin-top:24px;">
                    <a href="' . esc_url($adminUrl) . '" style="
                        display:inline-block;
                        padding:11px 18px;
                        background:#2563eb;
                        color:#ffffff;
                        text-decoration:none;
                        border-radius:8px;
                        font-weight:600;
                    ">
                        ' . esc_html__('View Reservation in Admin', 'meeting-room-booking') . '
                    </a>
                </p>
            ';
        }

        $paragraphHtml = '';

        foreach ($paragraphs as $paragraph) {
            $paragraphHtml .= '<p style="margin:0 0 12px;color:#374151;font-size:15px;line-height:1.6;">' . wp_kses_post($paragraph) . '</p>';
        }

        return '
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <title>' . esc_html($title) . '</title>
            </head>
            <body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;">
                <table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:30px 12px;">
                    <tr>
                        <td align="center">
                            <table width="100%" cellpadding="0" cellspacing="0" style="max-width:640px;background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e5e7eb;">
                                <tr>
                                    <td style="padding:24px 28px;background:#2563eb;color:#ffffff;">
                                        <h1 style="margin:0;font-size:22px;line-height:1.3;">
                                            ' . esc_html($title) . '
                                        </h1>
                                        <p style="margin:6px 0 0;font-size:14px;opacity:.9;">
                                            ' . esc_html($siteName) . '
                                        </p>
                                    </td>
                                </tr>

                                <tr>
                                    <td style="padding:28px;">
                                        <p style="margin:0 0 14px;color:#111827;font-size:16px;line-height:1.6;">
                                            ' . wp_kses_post($intro) . '
                                        </p>

                                        ' . $paragraphHtml . '

                                        <div style="margin-top:24px;">
                                            <h2 style="margin:0 0 14px;color:#111827;font-size:18px;">
                                                ' . esc_html__('Reservation Details', 'meeting-room-booking') . '
                                            </h2>

                                            <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;">
                                                ' . $detailsRows . '
                                            </table>
                                        </div>

                                        ' . $adminLinkHtml . '
                                    </td>
                                </tr>

                                <tr>
                                    <td style="padding:18px 28px;background:#f9fafb;border-top:1px solid #e5e7eb;color:#6b7280;font-size:13px;line-height:1.5;">
                                        ' . esc_html__('This is an automated email. Please do not reply directly to this message.', 'meeting-room-booking') . '
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </body>
            </html>
        ';
    }

    /**
     * Build reservation details rows for email.
     */
    private function buildReservationDetailsRows(array $reservation): string
    {
        $rows = [
            __('Name', 'meeting-room-booking')          => $this->getReservationName($reservation),
            __('Email', 'meeting-room-booking')         => $this->getReservationEmail($reservation),
            __('Mobile', 'meeting-room-booking')        => $reservation['mobile'] ?? '',
            __('Meeting Title', 'meeting-room-booking') => $reservation['meeting_title'] ?? '',
            __('Room', 'meeting-room-booking')          => $this->getRoomLabel($reservation),
            __('Date', 'meeting-room-booking')          => $reservation['meeting_date'] ?? '',
            __('Start Time', 'meeting-room-booking')    => $reservation['start_time'] ?? '',
            __('End Time', 'meeting-room-booking')      => $reservation['end_time'] ?? '',
            __('Status', 'meeting-room-booking')        => ucfirst($this->getReservationStatus($reservation)),
            __('Description', 'meeting-room-booking')   => $reservation['description'] ?? '',
        ];

        $html = '';
        $index = 0;

        foreach ($rows as $label => $value) {
            if ($value === '' || $value === null) {
                continue;
            }

            $background = $index % 2 === 0 ? '#ffffff' : '#f9fafb';

            $html .= '
                <tr style="background:' . esc_attr($background) . ';">
                    <td style="padding:12px 14px;border-bottom:1px solid #e5e7eb;color:#6b7280;font-size:14px;width:34%;font-weight:600;">
                        ' . esc_html($label) . '
                    </td>
                    <td style="padding:12px 14px;border-bottom:1px solid #e5e7eb;color:#111827;font-size:14px;">
                        ' . nl2br(esc_html((string) $value)) . '
                    </td>
                </tr>
            ';

            $index++;
        }

        return $html;
    }

    /**
     * Actually send email.
     */
    private function sendEmail(string $to, string $subject, string $message): bool
    {
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . $this->getFromEmail() . '>',
        ];

        return wp_mail($to, $subject, $message, $headers);
    }

    /**
     * Get admin notification email.
     */
    private function getAdminNotificationEmail(): string
    {
        $email = get_option('mrb_admin_notification_email');

        if (!$email) {
            $email = get_option('admin_email');
        }

        return is_email($email) ? $email : '';
    }

    /**
     * Get sender email.
     */
    private function getFromEmail(): string
    {
        $email = get_option('mrb_from_email');

        if (!$email || !is_email($email)) {
            $domain = wp_parse_url(home_url(), PHP_URL_HOST);

            if (!$domain) {
                return get_option('admin_email');
            }

            $domain = preg_replace('/^www\./', '', $domain);

            $email = 'wordpress@' . $domain;
        }

        return sanitize_email($email);
    }

    /**
     * Get reservation name.
     */
    private function getReservationName(array $reservation): string
    {
        $firstName = $reservation['first_name'] ?? '';
        $lastName  = $reservation['last_name'] ?? '';

        $name = trim($firstName . ' ' . $lastName);

        return $name ?: __('Guest', 'meeting-room-booking');
    }

    /**
     * Get reservation email.
     */
    private function getReservationEmail(array $reservation): string
    {
        $email = $reservation['email'] ?? '';

        return is_email($email) ? sanitize_email($email) : '';
    }

    /**
     * Get reservation status.
     */
    private function getReservationStatus(array $reservation): string
    {
        return !empty($reservation['status'])
            ? sanitize_key($reservation['status'])
            : 'pending';
    }

    /**
     * Get room label.
     */
    private function getRoomLabel(array $reservation): string
    {
        if (!empty($reservation['room_name'])) {
            return sanitize_text_field($reservation['room_name']);
        }

        if (!empty($reservation['room'])) {
            return sanitize_text_field($reservation['room']);
        }

        if (!empty($reservation['room_id'])) {
            return sprintf(
                __('Room #%d', 'meeting-room-booking'),
                absint($reservation['room_id'])
            );
        }

        return __('No room assigned', 'meeting-room-booking');
    }
}
