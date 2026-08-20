<?php
/**
 * Class groups transferred from RMS, and the advisor each one belongs to.
 *
 * RMS identifies the advisor by national ID (`teacherIdcard`). Staff accounts
 * transferred from the same system carry that number as their username,
 * because RMS uses the national ID as `people_id` — so the two datasets join
 * on it. The resolved user is stored alongside the raw number, so a group
 * whose teacher has not been transferred yet still keeps the number and can be
 * linked on a later run.
 */
return array(

    'name' => 'สร้างตารางกลุ่มเรียนและผูกครูที่ปรึกษา',

    'up' => function (Schema $s) {

        $s->createTable('student_groups',
              '`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,'
            . '`school_id` INT UNSIGNED NOT NULL,'
            . '`academic_year` SMALLINT UNSIGNED NOT NULL DEFAULT 0,'
            . '`semester` TINYINT UNSIGNED NOT NULL DEFAULT 0,'
            . '`group_code` VARCHAR(50) NOT NULL DEFAULT "",'
            . '`grade` VARCHAR(50) NOT NULL DEFAULT "",'
            . '`group_name` VARCHAR(150) NOT NULL DEFAULT "",'
            . '`group_abbr` VARCHAR(150) NOT NULL DEFAULT "",'
            // The national ID as RMS gave it, kept even when no account
            // matches, so a later transfer can still resolve it.
            . '`teacher_idcard` VARCHAR(20) NOT NULL DEFAULT "",'
            . '`teacher_name` VARCHAR(191) NOT NULL DEFAULT "",'
            . '`advisor_user_id` INT UNSIGNED NULL DEFAULT NULL,'
            . '`classroom_id` VARCHAR(50) NOT NULL DEFAULT "",'
            . '`external_source` VARCHAR(30) NOT NULL DEFAULT "",'
            . '`created_at` DATETIME NULL DEFAULT NULL,'
            . '`updated_at` DATETIME NULL DEFAULT NULL,'
            . 'PRIMARY KEY (`id`),'
            . 'UNIQUE KEY `uq_group` (`school_id`, `academic_year`, `semester`, `group_code`),'
            . 'KEY `idx_group_code` (`school_id`, `group_code`),'
            . 'KEY `idx_group_advisor` (`advisor_user_id`)'
        );
    },

    'down' => function (Schema $s) {
        $s->dropTable('student_groups');
    },
);
