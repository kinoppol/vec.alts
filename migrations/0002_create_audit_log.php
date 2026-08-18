<?php
/**
 * Audit trail. Kept in its own migration because sites that already run the
 * core schema should be able to adopt it without touching the other tables.
 */
return array(

    'name' => 'สร้างตารางบันทึกการใช้งาน (Audit log)',

    'up' => function (Schema $s) {

        $s->createTable('audit_log',
              '`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,'
            . '`school_id` INT UNSIGNED NULL DEFAULT NULL,'
            . '`actor_kind` VARCHAR(10) NOT NULL DEFAULT "",'
            . '`actor_id` INT UNSIGNED NULL DEFAULT NULL,'
            . '`actor_name` VARCHAR(191) NOT NULL DEFAULT "",'
            . '`action` VARCHAR(100) NOT NULL DEFAULT "",'
            . '`target` VARCHAR(191) NOT NULL DEFAULT "",'
            . '`detail` TEXT NULL,'
            . '`ip` VARCHAR(45) NOT NULL DEFAULT "",'
            . '`created_at` DATETIME NULL DEFAULT NULL,'
            . 'PRIMARY KEY (`id`),'
            . 'KEY `idx_audit_school` (`school_id`, `created_at`),'
            . 'KEY `idx_audit_action` (`action`)'
        );

        // Defaults the settings screen reads. INSERT IGNORE keeps a re-run
        // harmless if the rows already exist.
        $now = date('Y-m-d H:i:s');
        $defaults = array(
            'survey_year'      => (string) (((int) date('n') < 5 ? (int) date('Y') - 1 : (int) date('Y')) + 543),
            'site_title'       => 'ระบบติดตามศิษย์เก่า',
            'allow_self_update' => '1',
        );
        foreach ($defaults as $key => $value) {
            $s->run(
                'INSERT IGNORE INTO `{p}settings` (`setting_key`, `setting_value`, `updated_at`)'
                . ' VALUES (?, ?, ?)',
                array($key, $value, $now)
            );
        }
    },

    'down' => function (Schema $s) {
        $s->dropTable('audit_log');
        $s->run(
            'DELETE FROM `{p}settings` WHERE `setting_key` IN (?, ?, ?)',
            array('survey_year', 'site_title', 'allow_self_update')
        );
    },
);
