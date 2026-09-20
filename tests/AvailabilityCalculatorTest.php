<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Domain/AvailabilityCalculator.php';
require_once dirname(__DIR__) . '/src/Domain/AvailabilityEligibility.php';

use ABP\Domain\AvailabilityCalculator;
use ABP\Domain\AvailabilityEligibility;

$calculator = new AvailabilityCalculator();
$tests = 0;

$assertSame = static function ($expected, $actual, string $message) use (&$tests): void {
    ++$tests;
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: $message\nExpected: " . json_encode($expected) . "\nActual: " . json_encode($actual) . "\n");
        exit(1);
    }
};

$slots = $calculator->calculate('2026-09-21', 30, 30, array(array('09:00', '11:00')), array(), array());
$assertSame(array('2026-09-21 09:00:00', '2026-09-21 09:30:00', '2026-09-21 10:00:00', '2026-09-21 10:30:00'), $slots, 'normal day');

$slots = $calculator->calculate('2026-09-21', 30, 30, array(array('09:00', '12:00')), array(array('10:00', '10:30')), array());
$assertSame(array('2026-09-21 09:00:00', '2026-09-21 09:30:00', '2026-09-21 10:30:00', '2026-09-21 11:00:00', '2026-09-21 11:30:00'), $slots, 'break exclusion');

$assertSame(array(), $calculator->calculate('2026-09-21', 30, 15, array(), array(), array()), 'day off');

$slots = $calculator->calculate('2026-09-21', 60, 30, array(array('10:00', '12:00')), array(), array());
$assertSame(array('2026-09-21 10:00:00', '2026-09-21 10:30:00', '2026-09-21 11:00:00'), $slots, 'special working period and boundary');

$slots = $calculator->calculate('2026-09-21', 30, 30, array(array('09:00', '11:00')), array(), array(array('09:30', '10:00')));
$assertSame(array('2026-09-21 09:00:00', '2026-09-21 10:00:00', '2026-09-21 10:30:00'), $slots, 'existing appointment');

$slots = $calculator->calculate('2026-09-21', 60, 30, array(array('09:00', '12:00')), array(), array(array('10:30', '11:00')));
$assertSame(array('2026-09-21 09:00:00', '2026-09-21 09:30:00', '2026-09-21 11:00:00'), $slots, 'overlapping duration');

$slots = $calculator->calculate('2026-09-21', 45, 15, array(array('09:00', '10:00')), array(), array());
$assertSame(array('2026-09-21 09:00:00', '2026-09-21 09:15:00'), $slots, 'closing boundary');

$eligibility = new AvailabilityEligibility();
$assertSame('inactive_doctor', $eligibility->reason(false, true, true), 'inactive doctor');
$assertSame('not_assigned', $eligibility->reason(true, true, false), 'doctor not assigned to service');
$assertSame(null, $eligibility->reason(true, true, true), 'active assigned doctor and service');

fwrite(STDOUT, "OK: $tests availability tests passed.\n");
