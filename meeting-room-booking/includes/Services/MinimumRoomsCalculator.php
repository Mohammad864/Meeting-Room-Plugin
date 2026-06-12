<?php

namespace MRB\Services;

if (!defined('ABSPATH')) {
    exit;
}

class MinimumRoomsCalculator
{
    public function calculate(array $intervals): int
    {
        if (empty($intervals)) {
            return 0;
        }

        usort($intervals, function ($a, $b) {
            return strcmp($a['start_time'], $b['start_time']);
        });

        $endTimes = [];
        $maxRooms = 0;

        foreach ($intervals as $interval) {
            $start = strtotime($interval['start_time']);
            $end = strtotime($interval['end_time']);

            sort($endTimes);

            if (!empty($endTimes) && $start >= $endTimes[0]) {
                array_shift($endTimes);
            }

            $endTimes[] = $end;

            $maxRooms = max($maxRooms, count($endTimes));
        }

        return $maxRooms;
    }
}
