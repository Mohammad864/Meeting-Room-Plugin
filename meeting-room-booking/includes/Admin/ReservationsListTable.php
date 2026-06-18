<?php

namespace MRB\Admin;

use MRB\Database\ReservationRepository;
use MRB\Database\RoomRepository;

if (!defined('ABSPATH')) {
    exit;
}

class ReservationsListTable extends \WP_List_Table
{
    private ReservationRepository $reservations;
    private RoomRepository $rooms;

    public function __construct()
    {
        parent::__construct([
            'singular' => 'reservation',
            'plural'   => 'reservations',
            'ajax'     => false,
        ]);

        $this->reservations = new ReservationRepository();
        $this->rooms        = new RoomRepository();
    }

    public function get_columns(): array
    {
        return [
            'id'            => 'ID',
            'name'          => 'Name',
            'mobile'        => 'Mobile',
            'email'         => 'Email',
            'meeting_title' => 'Title',
            'meeting_date'  => 'Date',
            'time'          => 'Time',
            'room'          => 'Room',
            'status'        => 'Status',
            'actions'       => 'Actions',
        ];
    }

    public function prepare_items(): void
    {
        $perPage     = 20;
        $currentPage = $this->get_pagenum();

        $search = isset($_GET['s'])
            ? sanitize_text_field(wp_unslash($_GET['s']))
            : '';

        $date = isset($_GET['filter_date'])
            ? sanitize_text_field(wp_unslash($_GET['filter_date']))
            : '';

        $args = [
            'search' => $search,
            'date'   => $date,
            'limit'  => $perPage,
            'offset' => ($currentPage - 1) * $perPage,
        ];

        $this->items = $this->reservations->query($args);
        $totalItems  = $this->reservations->count($args);

        $this->_column_headers = [
            $this->get_columns(),
            [],
            [],
        ];

        $this->set_pagination_args([
            'total_items' => $totalItems,
            'per_page'    => $perPage,
            'total_pages' => ceil($totalItems / $perPage),
        ]);
    }

    public function column_default($item, $column_name)
    {
        switch ($column_name) {

            case 'id':
                return esc_html((string) $item['id']);

            case 'name':
                return esc_html(
                    trim(($item['first_name'] ?? '') . ' ' . ($item['last_name'] ?? ''))
                );

            case 'mobile':
                return esc_html($item['mobile'] ?? '');

            case 'email':
                if (!empty($item['email'])) {
                    return '<a href="mailto:' . esc_attr($item['email']) . '">' .
                        esc_html($item['email']) .
                        '</a>';
                }
                return '-';

            case 'meeting_title':
                return esc_html($item['meeting_title'] ?? '');

            case 'meeting_date':
                return esc_html($item['meeting_date'] ?? '');

            case 'time':
                return esc_html(
                    ($item['start_time'] ?? '') . ' - ' . ($item['end_time'] ?? '')
                );

            case 'room':

                if (empty($item['room_id'])) {
                    return '-';
                }

                $roomName = $this->rooms->findNameById((int) $item['room_id']);

                return esc_html($roomName ?: ('Room #' . (int)$item['room_id']));

            case 'status':
                return $this->renderStatusBadge($item['status'] ?? 'pending');

            case 'actions':
                return $this->renderActions($item);

            default:
                return '';
        }
    }

    private function renderStatusBadge(string $status): string
    {
        $status = sanitize_key($status);

        $colors = [
            'pending'   => '#856404;background:#fff3cd;',
            'approved'  => '#0f5132;background:#d1e7dd;',
            'rejected'  => '#842029;background:#f8d7da;',
            'cancelled' => '#555;background:#e2e3e5;',
        ];

        $style = $colors[$status] ?? '';

        return sprintf(
            '<span style="padding:4px 8px;border-radius:4px;%s">%s</span>',
            esc_attr($style),
            esc_html(ucfirst($status))
        );
    }

    private function renderActions(array $item): string
    {
        $id = (int) $item['id'];

        /*
        |--------------------------------------------------------------------------
        | FIX: AdminPage expects ?id= not reservation_id
        |--------------------------------------------------------------------------
        */

        $approveUrl = wp_nonce_url(
            admin_url('admin-post.php?action=mrb_change_status&status=approved&id=' . $id),
            'mrb_change_status_' . $id
        );

        $rejectUrl = wp_nonce_url(
            admin_url('admin-post.php?action=mrb_change_status&status=rejected&id=' . $id),
            'mrb_change_status_' . $id
        );

        $pendingUrl = wp_nonce_url(
            admin_url('admin-post.php?action=mrb_change_status&status=pending&id=' . $id),
            'mrb_change_status_' . $id
        );

        $actions = [];

        if ($item['status'] !== 'approved') {
            $actions[] = '<a href="' . esc_url($approveUrl) . '">Approve</a>';
        }

        if ($item['status'] !== 'rejected') {
            $actions[] = '<a href="' . esc_url($rejectUrl) . '" style="color:#b32d2e;">Reject</a>';
        }

        if ($item['status'] !== 'pending') {
            $actions[] = '<a href="' . esc_url($pendingUrl) . '">Pending</a>';
        }

        if (!empty($item['description'])) {
            $actions[] = '<span title="' . esc_attr($item['description']) . '">Description</span>';
        }

        return implode(' | ', $actions);
    }
}
