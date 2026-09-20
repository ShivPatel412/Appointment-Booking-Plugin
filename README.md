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

## Phase 2 scope

Version 0.2 adds:

- WordPress-backed patient registration with email activation, login, logout, and password-management links
- Automatic Patient Portal and Doctor Portal pages using private shortcodes
- Patient dashboard, profile, appointments, self-service cancellation/rescheduling, repeat booking, and read-only prescriptions
- Doctor-only assigned appointment list, authorized patient context, separated public/internal notes, completion workflow, and prescription authoring
- Prescription headers and medicine rows with transactional saves
- Object-level policies that derive patient and doctor identity from the signed-in WordPress account
- Secure portal-link notification events for prescription creation and updates

An administrator links a doctor to a WordPress account by entering the account's user ID in the doctor editor. Patient records are linked during verified registration without creating duplicate records for the same email.

## Installation

1. Copy this directory to `wp-content/plugins/appointment-booking-plugin`.
2. Activate **Appointment Booking Plugin** in WordPress.
3. Open **Appointments** in the WordPress admin menu.

Activation or upgrade also creates **Patient Portal** and **Doctor Portal** pages. If pages are managed manually, use `[abp_patient_portal]` and `[abp_doctor_portal]`.

Activation creates or upgrades tables without deleting existing records. Deactivation preserves all data.

## Development checks

```bash
npm test
php tests/AvailabilityCalculatorTest.php
```

The PHP test command requires PHP 8.0 or newer. A complete WordPress integration test requires a WordPress test installation and database.

## API namespace

All Phase 1 routes use `/wp-json/appointment-booking/v1`. Administrative calls require the plugin capabilities and WordPress REST nonce/cookie authentication.
