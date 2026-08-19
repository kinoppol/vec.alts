<?php
/**
 * The address of the RMS installation becomes part of an institution's own
 * details, so a system serving several institutions can pull each one's staff
 * from its own RMS.
 *
 * The value in settings (rms_base_url) stays as the default, used when an
 * institution has not been given an address of its own.
 */
return array(

    'name' => 'ผูกที่อยู่ระบบ RMS กับสถานศึกษา',

    'up' => function (Schema $s) {

        $s->addColumn(
            'schools',
            'rms_base_url',
            'VARCHAR(255) NOT NULL DEFAULT ""',
            'affiliation'
        );

        // Institutions that predate this column inherit the system-wide value,
        // so the transfer that worked yesterday keeps working today.
        $default = $s->run(
            'SELECT `setting_value` FROM `{p}settings` WHERE `setting_key` = ?',
            array('rms_base_url')
        )->fetch();

        if ($default && isset($default['setting_value']) && trim($default['setting_value']) !== '') {
            $s->run(
                'UPDATE `{p}schools` SET `rms_base_url` = ? WHERE `rms_base_url` = ""',
                array(trim($default['setting_value']))
            );
        }
    },

    'down' => function (Schema $s) {
        $s->dropColumn('schools', 'rms_base_url');
    },
);
