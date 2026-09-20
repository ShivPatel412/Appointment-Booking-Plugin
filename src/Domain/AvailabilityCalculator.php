<?php

declare(strict_types=1);

namespace ABP\Domain;

use DateInterval;
use DateTimeImmutable;
use InvalidArgumentException;

final class AvailabilityCalculator
{
    /**
     * @param array<int,array{0:string,1:string}> $workingPeriods
     * @param array<int,array{0:string,1:string}> $breaks
     * @param array<int,array{0:string,1:string}> $busyPeriods
     * @return string[] ISO-like local start times.
     */
    public function calculate(
        string $date,
        int $duration,
        int $interval,
        array $workingPeriods,
        array $breaks,
        array $busyPeriods
    ): array {
        if ($duration < 1 || $interval < 1 || ! $this->validDate($date)) {
            throw new InvalidArgumentException('Invalid date, duration, or slot interval.');
        }

        $blocked = array_merge($this->normalize($date, $breaks), $this->normalize($date, $busyPeriods));
        $slots = array();
        foreach ($this->normalize($date, $workingPeriods) as [$open, $close]) {
            for ($cursor = $open; $cursor < $close; $cursor = $cursor->add(new DateInterval('PT' . $interval . 'M'))) {
                $end = $cursor->add(new DateInterval('PT' . $duration . 'M'));
                if ($end > $close || $this->overlapsAny($cursor, $end, $blocked)) {
                    continue;
                }
                $slots[] = $cursor->format('Y-m-d H:i:s');
            }
        }
        sort($slots, SORT_STRING);
        return array_values(array_unique($slots));
    }

    private function validDate(string $date): bool
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }

    /** @return array<int,array{0:DateTimeImmutable,1:DateTimeImmutable}> */
    private function normalize(string $date, array $periods): array
    {
        $result = array();
        foreach ($periods as $period) {
            if (! is_array($period) || count($period) !== 2) {
                continue;
            }
            $start = $this->dateTime($date, (string) $period[0]);
            $end = $this->dateTime($date, (string) $period[1]);
            if ($start && $end && $start < $end) {
                $result[] = array($start, $end);
            }
        }
        return $result;
    }

    private function dateTime(string $date, string $value): ?DateTimeImmutable
    {
        $time = preg_match('/^\d{2}:\d{2}$/', $value) ? $value . ':00' : $value;
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $date . ' ' . $time);
        return $parsed === false ? null : $parsed;
    }

    private function overlapsAny(DateTimeImmutable $start, DateTimeImmutable $end, array $periods): bool
    {
        foreach ($periods as [$blockedStart, $blockedEnd]) {
            if ($start < $blockedEnd && $end > $blockedStart) {
                return true;
            }
        }
        return false;
    }
}
