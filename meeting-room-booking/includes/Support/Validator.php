<?php

namespace MRB\Support;

if (!defined('ABSPATH')) {
    exit;
}

class Validator
{
    public static function validateReservation(array $data): array
    {
        $errors = [];

        $required = [
            'first_name',
            'last_name',
            'mobile',
            'email',
            'meeting_title',
            'meeting_date',
            'start_time',
            'end_time',
        ];

        foreach ($required as $field) {
            if (empty($data[$field])) {
                $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
            }
        }

        if (!empty($data['email']) && !is_email($data['email'])) {
            $errors[] = 'Invalid email address.';
        }

        if (!empty($data['meeting_date'])) {
            $today = current_time('Y-m-d');

            if ($data['meeting_date'] < $today) {
                $errors[] = 'Meeting date cannot be in the past.';
            }
        }

        if (!empty($data['start_time']) && !empty($data['end_time'])) {
            if ($data['end_time'] <= $data['start_time']) {
                $errors[] = 'End time must be greater than start time.';
            }
        }

        if (!empty($data['mobile']) && !preg_match('/^[0-9+\-\s]{8,20}$/', $data['mobile'])) {
            $errors[] = 'Invalid mobile number.';
        }

        return $errors;
    }
}
