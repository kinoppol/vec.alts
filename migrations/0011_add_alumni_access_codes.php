<?php
/**
 * A second factor for students and graduates.
 *
 * The national ID used to be the password, and national IDs have leaked from
 * government databases in bulk. From here on a person proves themselves once
 * with their ID plus a one-time code their teacher hands them, then chooses a
 * password of their own; the ID stops being a credential at all.
 *
 * Installations that already hold people start with the requirement switched
 * off, so teachers can hand codes out before anyone is locked out. A fresh
 * installation has nobody to hand codes to yet and starts with it on.
 */
return array(

    'name' => 'เพิ่มรหัสเข้าใช้ครั้งแรกและรหัสผ่านของผู้เรียน',

    'up' => function (Schema $s) {
        $s->addColumn('alumni', 'password_hash', 'VARCHAR(255) NULL DEFAULT NULL', 'national_id_hash');
        $s->addColumn('alumni', 'password_set_at', 'DATETIME NULL DEFAULT NULL', 'password_hash');
        $s->addColumn('alumni', 'access_code_hash', 'VARCHAR(255) NULL DEFAULT NULL', 'password_set_at');
        $s->addColumn('alumni', 'access_code_expires_at', 'DATETIME NULL DEFAULT NULL', 'access_code_hash');
        $s->addColumn('alumni', 'login_failures', 'INT NOT NULL DEFAULT 0', 'access_code_expires_at');
        $s->addColumn('alumni', 'locked_until', 'DATETIME NULL DEFAULT NULL', 'login_failures');

        $existing = (int) $s->run('SELECT COUNT(*) FROM `{p}alumni`')->fetchColumn();
        $s->run(
            'INSERT IGNORE INTO `{p}settings` (`setting_key`, `setting_value`, `updated_at`)'
            . ' VALUES (?, ?, ?)',
            array('alumni_access_code_required', $existing > 0 ? '0' : '1', date('Y-m-d H:i:s'))
        );
    },

    'down' => function (Schema $s) {
        $s->dropColumn('alumni', 'locked_until');
        $s->dropColumn('alumni', 'login_failures');
        $s->dropColumn('alumni', 'access_code_expires_at');
        $s->dropColumn('alumni', 'access_code_hash');
        $s->dropColumn('alumni', 'password_set_at');
        $s->dropColumn('alumni', 'password_hash');
        $s->run('DELETE FROM `{p}settings` WHERE `setting_key` = ?', array('alumni_access_code_required'));
    },
);
