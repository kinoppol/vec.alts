<?php
/**
 * Marks records whose contact details were corrected inside this system.
 *
 * RMS is the source of those fields and overwrites them on every transfer.
 * Once a teacher or the person themselves has corrected a number here,
 * that correction wins: the flag tells the transfer to leave phone and email
 * alone for that row. Records nobody has touched still track RMS, so a genuine
 * correction made upstream still lands.
 *
 * Also carries the switch that turns error detail on and off, kept in the
 * database so the central administrator can reach it from the settings screen
 * without the configuration file needing to be writable at runtime.
 */
return array(

    'name' => 'รองรับการแก้ไขข้อมูลติดต่อทับข้อมูลจาก RMS',

    'up' => function (Schema $s) {

        $s->addColumn(
            'alumni',
            'contact_overridden',
            'TINYINT(1) NOT NULL DEFAULT 0',
            'contact_note'
        );

        $now = date('Y-m-d H:i:s');
        $s->run(
            'INSERT IGNORE INTO `{p}settings` (`setting_key`, `setting_value`, `updated_at`)'
            . ' VALUES (?, ?, ?)',
            // Off by default: a live site must never show error detail to
            // whoever happens to trigger it.
            array('app_debug', '0', $now)
        );
    },

    'down' => function (Schema $s) {
        $s->dropColumn('alumni', 'contact_overridden');
        $s->run('DELETE FROM `{p}settings` WHERE `setting_key` = ?', array('app_debug'));
    },
);
