<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Security/AccessPolicy.php';

use ABP\Security\AccessPolicy;

$policy = new AccessPolicy();
$tests = array(
    array(true, $policy->patientOwns(10, 10), 'Patient can access own record'),
    array(false, $policy->patientOwns(10, 11), 'Patient A cannot access Patient B'),
    array(false, $policy->patientOwns(0, 10), 'Unauthenticated patient mapping is denied'),
    array(true, $policy->doctorOwnsAppointment(7, 7), 'Doctor can access assigned appointment'),
    array(false, $policy->doctorOwnsAppointment(7, 8), 'Doctor cannot access another doctor appointment'),
    array(true, $policy->doctorOwnsPrescription(7, 7), 'Doctor can access own prescription'),
    array(false, $policy->doctorOwnsPrescription(7, 8), 'Doctor cannot access another doctor prescription'),
);

foreach ($tests as [$expected, $actual, $label]) {
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: $label\n");
        exit(1);
    }
}
fwrite(STDOUT, 'OK: ' . count($tests) . " access policy tests passed.\n");
