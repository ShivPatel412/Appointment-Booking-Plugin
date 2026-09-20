# Appointment Booking Plugin

A WordPress appointment-management plugin with a React-powered admin workspace and a server-authoritative scheduling engine.

## Phase 1 scope

The current implementation provides:

- Versioned custom database tables and non-destructive activation migrations
- Administrator capabilities, REST permissions, input validation, and prepared queries
- Categories, services, doctors, doctor-service pricing, working hours, breaks, schedule exceptions, and patients
- Deterministic availability calculation with appointment-conflict exclusion
- Appointment creation, editing, rescheduling, cancellation, filtering, and calendar drag/drop
- Dashboard metrics and date-range activity
- Transactional email provider, template renderer, and delivery log
- Business and scheduling settings

Excluded by design: patient/doctor portals, prescriptions, payments, SMS, external calendar synchronization, events, locations, finance, waiting lists, buffers, capacity, deposits, and service extras.

## Installation

1. Copy this directory to `wp-content/plugins/appointment-booking-plugin`.
2. Activate **Appointment Booking Plugin** in WordPress.
3. Open **Appointments** in the WordPress admin menu.

Activation creates or upgrades tables without deleting existing records. Deactivation preserves all data.

## Development checks

```bash
npm test
php tests/AvailabilityCalculatorTest.php
```

The PHP test command requires PHP 8.0 or newer. A complete WordPress integration test requires a WordPress test installation and database.

## API namespace

All Phase 1 routes use `/wp-json/appointment-booking/v1`. Administrative calls require the plugin capabilities and WordPress REST nonce/cookie authentication.
