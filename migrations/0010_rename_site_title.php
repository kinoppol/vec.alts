<?php
/**
 * The system follows students from enrolment through graduation to work or
 * further study, so the old graduates-only name no longer fits. Only a title
 * still at one of the old defaults is renamed; one an administrator chose is
 * left alone.
 */
return array(
    'name' => 'เปลี่ยนชื่อระบบเป็นระบบติดตามข้อมูลนักศึกษา',

    'up' => function (Schema $s) {
        $s->run(
            'UPDATE `{p}settings` SET `setting_value` = ?, `updated_at` = ?'
            . ' WHERE `setting_key` = ? AND `setting_value` IN (?, ?)',
            array('ระบบติดตามข้อมูลนักศึกษา', date('Y-m-d H:i:s'), 'site_title',
                'ระบบติดตามผู้สำเร็จการศึกษา', 'ระบบติดตามศิษย์เก่า')
        );
    },

    'down' => function (Schema $s) {
        $s->run(
            'UPDATE `{p}settings` SET `setting_value` = ?, `updated_at` = ?'
            . ' WHERE `setting_key` = ? AND `setting_value` = ?',
            array('ระบบติดตามผู้สำเร็จการศึกษา', date('Y-m-d H:i:s'), 'site_title',
                'ระบบติดตามข้อมูลนักศึกษา')
        );
    },
);
