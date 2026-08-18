<?php
/**
 * Core schema: schools, departments, staff users, alumni, survey answers.
 *
 * Notes on portability:
 *  - Statuses are VARCHAR rather than ENUM. Adding an ENUM value means an
 *    ALTER on every server; a VARCHAR plus application validation does not,
 *    and ENUM handling differs under strict mode.
 *  - Every indexed VARCHAR stays at or below 191 characters so utf8mb4 fits
 *    the 767-byte InnoDB prefix limit still in force on MySQL 5.5/5.6.
 *  - Only DATETIME columns, written by the application. MySQL 5.5 allows just
 *    one CURRENT_TIMESTAMP column per table, so relying on it is not portable.
 */
return array(

    'name' => 'สร้างตารางหลักของระบบ',

    'up' => function (Schema $s) {

        $s->createTable('schools',
              '`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,'
            . '`code` VARCHAR(32) NOT NULL DEFAULT "",'
            . '`name` VARCHAR(191) NOT NULL,'
            . '`province` VARCHAR(100) NOT NULL DEFAULT "",'
            . '`affiliation` VARCHAR(100) NOT NULL DEFAULT "",'
            . '`contact_name` VARCHAR(191) NOT NULL DEFAULT "",'
            . '`contact_phone` VARCHAR(40) NOT NULL DEFAULT "",'
            . '`contact_email` VARCHAR(191) NOT NULL DEFAULT "",'
            . '`status` VARCHAR(20) NOT NULL DEFAULT "pending",'
            . '`note` TEXT NULL,'
            . '`created_at` DATETIME NULL DEFAULT NULL,'
            . '`updated_at` DATETIME NULL DEFAULT NULL,'
            . 'PRIMARY KEY (`id`),'
            . 'KEY `idx_schools_status` (`status`),'
            . 'KEY `idx_schools_code` (`code`)'
        );

        $s->createTable('departments',
              '`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,'
            . '`school_id` INT UNSIGNED NOT NULL,'
            . '`code` VARCHAR(32) NOT NULL DEFAULT "",'
            . '`name` VARCHAR(191) NOT NULL,'
            . '`sort_order` INT NOT NULL DEFAULT 0,'
            . '`created_at` DATETIME NULL DEFAULT NULL,'
            . 'PRIMARY KEY (`id`),'
            . 'KEY `idx_dept_school` (`school_id`, `sort_order`)'
        );

        // school_id is NULL for the central administrator, who belongs to no
        // single institution.
        $s->createTable('users',
              '`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,'
            . '`school_id` INT UNSIGNED NULL DEFAULT NULL,'
            . '`department_id` INT UNSIGNED NULL DEFAULT NULL,'
            . '`role` VARCHAR(20) NOT NULL DEFAULT "advisor",'
            . '`username` VARCHAR(100) NULL DEFAULT NULL,'
            . '`email` VARCHAR(191) NOT NULL,'
            . '`password_hash` VARCHAR(255) NOT NULL DEFAULT "",'
            . '`full_name` VARCHAR(191) NOT NULL DEFAULT "",'
            . '`phone` VARCHAR(40) NOT NULL DEFAULT "",'
            . '`status` VARCHAR(20) NOT NULL DEFAULT "active",'
            . '`created_at` DATETIME NULL DEFAULT NULL,'
            . '`updated_at` DATETIME NULL DEFAULT NULL,'
            . '`last_login_at` DATETIME NULL DEFAULT NULL,'
            . 'PRIMARY KEY (`id`),'
            . 'UNIQUE KEY `uq_users_email` (`email`),'
            . 'UNIQUE KEY `uq_users_username` (`username`),'
            . 'KEY `idx_users_school_role` (`school_id`, `role`)'
        );

        // Alumni sign in with the student code they had while studying plus
        // their national ID, so the ID is stored only as a hash.
        $s->createTable('alumni',
              '`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,'
            . '`school_id` INT UNSIGNED NOT NULL,'
            . '`department_id` INT UNSIGNED NULL DEFAULT NULL,'
            . '`advisor_user_id` INT UNSIGNED NULL DEFAULT NULL,'
            . '`student_code` VARCHAR(32) NOT NULL,'
            . '`national_id_hash` VARCHAR(255) NOT NULL DEFAULT "",'
            . '`national_id_last4` VARCHAR(4) NOT NULL DEFAULT "",'
            . '`title` VARCHAR(30) NOT NULL DEFAULT "",'
            . '`first_name` VARCHAR(100) NOT NULL DEFAULT "",'
            . '`last_name` VARCHAR(100) NOT NULL DEFAULT "",'
            . '`level` VARCHAR(20) NOT NULL DEFAULT "",'
            . '`graduation_year` SMALLINT UNSIGNED NOT NULL DEFAULT 0,'
            . '`phone` VARCHAR(40) NOT NULL DEFAULT "",'
            . '`email` VARCHAR(191) NOT NULL DEFAULT "",'
            . '`line_id` VARCHAR(100) NOT NULL DEFAULT "",'
            . '`address` TEXT NULL,'
            . '`status` VARCHAR(20) NOT NULL DEFAULT "active",'
            . '`created_at` DATETIME NULL DEFAULT NULL,'
            . '`updated_at` DATETIME NULL DEFAULT NULL,'
            . '`last_login_at` DATETIME NULL DEFAULT NULL,'
            . 'PRIMARY KEY (`id`),'
            . 'UNIQUE KEY `uq_alumni_code` (`school_id`, `student_code`),'
            . 'KEY `idx_alumni_year` (`school_id`, `graduation_year`),'
            . 'KEY `idx_alumni_advisor` (`advisor_user_id`),'
            . 'KEY `idx_alumni_dept` (`department_id`)'
        );

        // One row per alumnus per survey year, so year-on-year comparison is a
        // plain GROUP BY rather than a history table walk.
        $s->createTable('alumni_status',
              '`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,'
            . '`alumni_id` INT UNSIGNED NOT NULL,'
            . '`school_id` INT UNSIGNED NOT NULL,'
            . '`survey_year` SMALLINT UNSIGNED NOT NULL DEFAULT 0,'
            . '`employment_status` VARCHAR(30) NOT NULL DEFAULT "",'
            . '`company_name` VARCHAR(191) NOT NULL DEFAULT "",'
            . '`job_position` VARCHAR(191) NOT NULL DEFAULT "",'
            . '`salary` DECIMAL(10,2) NULL DEFAULT NULL,'
            . '`work_province` VARCHAR(100) NOT NULL DEFAULT "",'
            . '`study_place` VARCHAR(191) NOT NULL DEFAULT "",'
            . '`study_level` VARCHAR(100) NOT NULL DEFAULT "",'
            . '`study_major` VARCHAR(191) NOT NULL DEFAULT "",'
            . '`note` TEXT NULL,'
            . '`is_draft` TINYINT(1) NOT NULL DEFAULT 0,'
            . '`updated_by_kind` VARCHAR(10) NOT NULL DEFAULT "",'
            . '`updated_by_id` INT UNSIGNED NULL DEFAULT NULL,'
            . '`submitted_at` DATETIME NULL DEFAULT NULL,'
            . '`created_at` DATETIME NULL DEFAULT NULL,'
            . '`updated_at` DATETIME NULL DEFAULT NULL,'
            . 'PRIMARY KEY (`id`),'
            . 'UNIQUE KEY `uq_status_year` (`alumni_id`, `survey_year`),'
            . 'KEY `idx_status_report` (`school_id`, `survey_year`, `employment_status`)'
        );

        $s->createTable('settings',
              '`setting_key` VARCHAR(100) NOT NULL,'
            . '`setting_value` TEXT NULL,'
            . '`updated_at` DATETIME NULL DEFAULT NULL,'
            . 'PRIMARY KEY (`setting_key`)'
        );
    },

    'down' => function (Schema $s) {
        // Reverse creation order; there are no FK constraints to fight, but
        // dropping children first keeps the intent obvious.
        $s->dropTable('settings');
        $s->dropTable('alumni_status');
        $s->dropTable('alumni');
        $s->dropTable('users');
        $s->dropTable('departments');
        $s->dropTable('schools');
    },
);
