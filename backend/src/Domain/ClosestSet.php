<?php

declare(strict_types=1);

namespace Sstf\Api\Domain;

use DateTimeImmutable;

final class ClosestSet
{
    /**
     * Pick the set whose weekday + start time is closest to $now (min absolute delta).
     *
     * $now must already be in the account timezone. This is not "next upcoming."
     *
     * @param list<TrainingSet> $sets
     */
    public static function pick(array $sets, DateTimeImmutable $now): ?TrainingSet
    {
        $winner = null;
        $winnerMeta = null;

        foreach ($sets as $set) {
            $meta = self::bestCandidate($set, $now);
            if ($winner === null || self::beats($set, $meta, $winner, $winnerMeta, $now)) {
                $winner = $set;
                $winnerMeta = $meta;
            }
        }

        return $winner;
    }

    /**
     * @return array{delta: int, candidate: DateTimeImmutable}
     */
    private static function bestCandidate(TrainingSet $set, DateTimeImmutable $now): array
    {
        $bestDelta = null;
        $bestCandidate = null;
        $bestSameDay = false;

        foreach ([-1, 0, 1] as $weekOffset) {
            $candidate = self::occurrence($now, $set->dayOfWeek, $set->startMinutes, $weekOffset);
            $delta = abs($candidate->getTimestamp() - $now->getTimestamp());
            $sameDay = $candidate->format('Y-m-d') === $now->format('Y-m-d');
            $betterDelta = $bestDelta === null || $delta < $bestDelta;
            $sameDeltaPreferToday = $bestDelta !== null && $delta === $bestDelta && $sameDay && !$bestSameDay;
            if ($betterDelta || $sameDeltaPreferToday) {
                $bestDelta = $delta;
                $bestCandidate = $candidate;
                $bestSameDay = $sameDay;
            }
        }

        assert($bestDelta !== null && $bestCandidate !== null);

        return [
            'delta' => $bestDelta,
            'candidate' => $bestCandidate,
        ];
    }

    /**
     * @param array{delta: int, candidate: DateTimeImmutable} $challengerMeta
     * @param array{delta: int, candidate: DateTimeImmutable} $incumbentMeta
     */
    private static function beats(
        TrainingSet $challenger,
        array $challengerMeta,
        TrainingSet $incumbent,
        array $incumbentMeta,
        DateTimeImmutable $now,
    ): bool {
        if ($challengerMeta['delta'] < $incumbentMeta['delta']) {
            return true;
        }
        if ($challengerMeta['delta'] > $incumbentMeta['delta']) {
            return false;
        }

        $challengerSameDay = $challengerMeta['candidate']->format('Y-m-d') === $now->format('Y-m-d');
        $incumbentSameDay = $incumbentMeta['candidate']->format('Y-m-d') === $now->format('Y-m-d');
        if ($challengerSameDay !== $incumbentSameDay) {
            return $challengerSameDay;
        }
        if ($challenger->startMinutes !== $incumbent->startMinutes) {
            return $challenger->startMinutes < $incumbent->startMinutes;
        }

        return $challenger->id < $incumbent->id;
    }

    private static function occurrence(
        DateTimeImmutable $now,
        int $dayOfWeek,
        int $startMinutes,
        int $weekOffset,
    ): DateTimeImmutable {
        $deltaDays = $dayOfWeek - (int) $now->format('w') + ($weekOffset * 7);
        $hours = intdiv($startMinutes, 60);
        $minutes = $startMinutes % 60;

        return $now->modify($deltaDays . ' day')->setTime($hours, $minutes, 0);
    }
}
