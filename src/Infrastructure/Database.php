<?php

declare(strict_types=1);

namespace ABP\Infrastructure;

use ABP\Portal\PortalPages;

final class Database
{
    public const VERSION = '2.0.0';

    public static function table(string $name): string
    {
        global $wpdb;
        return $wpdb->prefix . 'abp_' . $name;
    }

    public static function maybeMigrate(): void
    {
        if (get_option('abp_schema_version') !== self::VERSION) {
            self::migrate();
            Capabilities::install();
            PortalPages::install();
        }
    }

    public static function migrate(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $tables = array(
            "CREATE TABLE " . self::table('categories') . " (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                name varchar(190) NOT NULL,
                description text NULL,
                active tinyint(1) NOT NULL DEFAULT 1,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY (id), KEY active (active), UNIQUE KEY name (name)
            ) $charset;",
            "CREATE TABLE " . self::table('services') . " (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                category_id bigint(20) unsigned NULL,
                name varchar(190) NOT NULL,
                description text NULL,
                image_id bigint(20) unsigned NULL,
                color varchar(7) NOT NULL DEFAULT '#4f46e5',
                price decimal(12,2) NOT NULL DEFAULT 0,
                duration smallint unsigned NOT NULL,
                active tinyint(1) NOT NULL DEFAULT 1,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY (id), KEY category_id (category_id), KEY active (active)
            ) $charset;",
            "CREATE TABLE " . self::table('doctors') . " (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                user_id bigint(20) unsigned NULL,
                image_id bigint(20) unsigned NULL,
                name varchar(190) NOT NULL,
                email varchar(190) NOT NULL,
                phone varchar(40) NULL,
                bio text NULL,
                active tinyint(1) NOT NULL DEFAULT 1,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY (id), UNIQUE KEY email (email), KEY active (active), KEY user_id (user_id)
            ) $charset;",
            "CREATE TABLE " . self::table('doctor_services') . " (
                doctor_id bigint(20) unsigned NOT NULL,
                service_id bigint(20) unsigned NOT NULL,
                price decimal(12,2) NULL,
                PRIMARY KEY (doctor_id, service_id), KEY service_id (service_id)
            ) $charset;",
            "CREATE TABLE " . self::table('working_hours') . " (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                doctor_id bigint(20) unsigned NOT NULL,
                weekday tinyint unsigned NOT NULL,
                start_time time NOT NULL,
                end_time time NOT NULL,
                PRIMARY KEY (id), KEY doctor_day (doctor_id, weekday)
            ) $charset;",
            "CREATE TABLE " . self::table('breaks') . " (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                doctor_id bigint(20) unsigned NOT NULL,
                weekday tinyint unsigned NOT NULL,
                start_time time NOT NULL,
                end_time time NOT NULL,
                PRIMARY KEY (id), KEY doctor_day (doctor_id, weekday)
            ) $charset;",
            "CREATE TABLE " . self::table('schedule_exceptions') . " (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                doctor_id bigint(20) unsigned NOT NULL,
                exception_date date NOT NULL,
                is_working tinyint(1) NOT NULL DEFAULT 0,
                start_time time NULL,
                end_time time NULL,
                label varchar(190) NULL,
                PRIMARY KEY (id), KEY doctor_date (doctor_id, exception_date)
            ) $charset;",
            "CREATE TABLE " . self::table('patients') . " (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                user_id bigint(20) unsigned NULL,
                image_id bigint(20) unsigned NULL,
                first_name varchar(100) NOT NULL,
                last_name varchar(100) NOT NULL,
                email varchar(190) NOT NULL,
                phone varchar(40) NULL,
                date_of_birth date NULL,
                gender varchar(30) NULL,
                address text NULL,
                reference varchar(100) NOT NULL,
                admin_notes text NULL,
                account_status varchar(20) NOT NULL DEFAULT 'active',
                active tinyint(1) NOT NULL DEFAULT 1,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY (id), UNIQUE KEY email (email), UNIQUE KEY reference (reference), UNIQUE KEY user_id (user_id), KEY active (active), KEY patient_name (last_name, first_name)
            ) $charset;",
            "CREATE TABLE " . self::table('appointments') . " (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                reference varchar(100) NULL,
                doctor_id bigint(20) unsigned NOT NULL,
                service_id bigint(20) unsigned NOT NULL,
                patient_id bigint(20) unsigned NOT NULL,
                start_at datetime NOT NULL,
                end_at datetime NOT NULL,
                duration smallint unsigned NOT NULL,
                status varchar(20) NOT NULL DEFAULT 'pending',
                notes text NULL,
                public_notes text NULL,
                internal_notes text NULL,
                created_by bigint(20) unsigned NOT NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY (id), UNIQUE KEY reference (reference), KEY doctor_window (doctor_id, start_at, end_at), KEY patient_id (patient_id), KEY service_id (service_id), KEY status (status), KEY start_at (start_at)
            ) $charset;",
            "CREATE TABLE " . self::table('prescriptions') . " (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                reference varchar(100) NOT NULL,
                patient_id bigint(20) unsigned NOT NULL,
                doctor_id bigint(20) unsigned NOT NULL,
                appointment_id bigint(20) unsigned NOT NULL,
                prescribed_on date NOT NULL,
                diagnosis text NULL,
                instructions text NULL,
                additional_notes text NULL,
                attachment_id bigint(20) unsigned NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY (id), UNIQUE KEY reference (reference), KEY patient_id (patient_id), KEY doctor_id (doctor_id), KEY appointment_id (appointment_id)
            ) $charset;",
            "CREATE TABLE " . self::table('prescription_items') . " (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                prescription_id bigint(20) unsigned NOT NULL,
                medicine_name varchar(190) NOT NULL,
                dosage varchar(190) NULL,
                frequency varchar(190) NULL,
                duration varchar(190) NULL,
                instructions text NULL,
                sort_order smallint unsigned NOT NULL DEFAULT 0,
                PRIMARY KEY (id), KEY prescription_id (prescription_id), KEY sort_order (sort_order)
            ) $charset;",
            "CREATE TABLE " . self::table('notification_log') . " (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                event varchar(80) NOT NULL,
                appointment_id bigint(20) unsigned NULL,
                recipient varchar(190) NOT NULL,
                subject varchar(255) NOT NULL,
                status varchar(20) NOT NULL,
                error text NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY (id), KEY appointment_id (appointment_id), KEY event (event)
            ) $charset;"
        );
        foreach ($tables as $sql) {
            dbDelta($sql);
        }
        $wpdb->query('UPDATE ' . self::table('appointments') . " SET reference=CONCAT('APT-',LPAD(id,8,'0')) WHERE reference IS NULL OR reference='' ");
        update_option('abp_schema_version', self::VERSION, false);
    }
}
