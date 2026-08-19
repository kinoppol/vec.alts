<?php
/**
 * Columns needed to transfer staff accounts in from an external system, and
 * the setting that holds where to transfer them from.
 *
 * email becomes nullable because the source system leaves it blank for a
 * large share of its people. The column carries a UNIQUE index, and several
 * rows holding '' would collide on it; MySQL and MariaDB both allow any
 * number of NULLs in a unique index, so NULL is the correct "no address here"
 * value and real addresses stay unique.
 */
return array(

    'name' => 'รองรับการโอนข้อมูลผู้ใช้จากระบบภายนอก',

    'up' => function (Schema $s) {

        $s->modifyColumn('users', 'email', 'VARCHAR(191) NULL DEFAULT NULL');
        // Rows that predate this migration hold '' rather than NULL.
        $s->run('UPDATE `{p}users` SET `email` = NULL WHERE `email` = ""');

        // Where the profile picture was saved, relative to uploads/avatars/.
        $s->addColumn('users', 'avatar_path', 'VARCHAR(255) NOT NULL DEFAULT ""', 'phone');

        // Which system a row came from, and its identifier there, so a repeat
        // transfer updates the same person instead of creating a second one.
        $s->addColumn('users', 'external_source', 'VARCHAR(30) NOT NULL DEFAULT ""', 'avatar_path');
        $s->addColumn('users', 'external_id', 'VARCHAR(64) NOT NULL DEFAULT ""', 'external_source');
        $s->addIndex('users', 'idx_users_external', '`external_source`, `external_id`');

        $now = date('Y-m-d H:i:s');
        $defaults = array(
            // Only the origin is stored. The path and query live in the code,
            // in RmsImporter::API_PATH.
            'rms_base_url'       => 'http://rms.pbntc.ac.th',
            'rms_default_role'   => 'advisor',
            'rms_last_import_at' => '',
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
        // Restoring NOT NULL needs every row to hold a value, and the UNIQUE
        // index needs those values distinct, so rows without an address get a
        // placeholder derived from their id rather than being deleted.
        $s->run(
            'UPDATE `{p}users` SET `email` = CONCAT("no-email-", `id`, "@invalid.local")'
            . ' WHERE `email` IS NULL'
        );
        $s->modifyColumn('users', 'email', 'VARCHAR(191) NOT NULL');

        $s->dropIndex('users', 'idx_users_external');
        $s->dropColumn('users', 'external_id');
        $s->dropColumn('users', 'external_source');
        $s->dropColumn('users', 'avatar_path');

        $s->run(
            'DELETE FROM `{p}settings` WHERE `setting_key` IN (?, ?, ?)',
            array('rms_base_url', 'rms_default_role', 'rms_last_import_at')
        );
    },
);
