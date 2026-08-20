<?php

declare(strict_types=1);

namespace Sstf\Api\Domain;

use DateTimeImmutable;
use DateTimeZone;
use Exception;

final class HistoryGrouper
{
    /**
     * @param list<ExerciseLog> $logs
     * @return list<HistoryDay>
     */
    public static function groupByDay(array $logs, string $timezone): array
    {
        $tz = new DateTimeZone(IanaTimezone::resolve($timezone));
        $buckets = [];
        $order = [];

        foreach ($logs as $log) {
            $day = self::localDay($log->loggedAt, $tz);
            if (!array_key_exists($day, $buckets)) {
                $buckets[$day] = [];
                $order[] = $day;
            }
            $buckets[$day][] = $log;
        }

        $days = [];
        foreach ($order as $day) {
            $days[] = new HistoryDay($day, $buckets[$day]);
        }

        return $days;
    }

    /**
     * @param list<HistoryDay> $days
     * @return list<HistoryDay>
     */
    public static function inDateRange(array $days, HistoryFilters $filters): array
    {
        $matched = [];
        foreach ($days as $day) {
            if ($filters->matchesDay($day->date)) {
                $matched[] = $day;
            }
        }

        return $matched;
    }

    private static function localDay(string $loggedAt, DateTimeZone $timezone): string
    {
        try {
            $at = new DateTimeImmutable($loggedAt);
        } catch (Exception) {
            $at = new DateTimeImmutable('@0');
        }

        return $at->setTimezone($timezone)->format('Y-m-d');
    }
}
