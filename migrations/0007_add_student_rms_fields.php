<?php
/**
 * Fields carried over when current students are transferred from RMS.
 *
 * They land in `alumni` rather than a table of their own: migration 0004
 * already made that table hold both current students and graduates, and
 * graduating flips a flag on the row that is already there. A separate table
 * would enter every person twice and break that hand-over.
 */
return array(

    'name' => 'เพิ่มฟิลด์ข้อมูลนักเรียนจากระบบ RMS',

    'up' => function (Schema $s) {

        // Which system the row came from, and its key there, so a repeat
        // transfer refreshes the same person. studentID is RMS's own id and
        // is stable even if the student code is reissued.
        $s->addColumn('alumni', 'external_source', 'VARCHAR(30) NOT NULL DEFAULT ""', 'status');
        $s->addColumn('alumni', 'external_id', 'VARCHAR(64) NOT NULL DEFAULT ""', 'external_source');

        $s->addColumn('alumni', 'gender', 'VARCHAR(5) NOT NULL DEFAULT ""', 'last_name');

        // Class group. group_code is what the RMS timetable and group feeds
        // join on, so it is kept verbatim.
        $s->addColumn('alumni', 'group_code', 'VARCHAR(50) NOT NULL DEFAULT ""', 'level');
        $s->addColumn('alumni', 'group_name', 'VARCHAR(150) NOT NULL DEFAULT ""', 'group_code');
        $s->addColumn('alumni', 'grade_name', 'VARCHAR(150) NOT NULL DEFAULT ""', 'group_name');
        $s->addColumn('alumni', 'major_name', 'VARCHAR(150) NOT NULL DEFAULT ""', 'grade_name');

        // Enrolment status as RMS reports it: "03 / กำลังศึกษา" and so on.
        $s->addColumn('alumni', 'status_code', 'VARCHAR(10) NOT NULL DEFAULT ""', 'major_name');
        $s->addColumn('alumni', 'status_name', 'VARCHAR(100) NOT NULL DEFAULT ""', 'status_code');

        $s->addColumn('alumni', 'entrance_year', 'SMALLINT UNSIGNED NOT NULL DEFAULT 0', 'status_name');
        $s->addColumn('alumni', 'entrance_semester', 'TINYINT UNSIGNED NOT NULL DEFAULT 0', 'entrance_year');
        $s->addColumn('alumni', 'gpax', 'DECIMAL(4,2) NULL DEFAULT NULL', 'entrance_semester');

        $s->addIndex('alumni', 'idx_alumni_external', '`school_id`, `external_source`, `external_id`');
        $s->addIndex('alumni', 'idx_alumni_group', '`school_id`, `group_code`');
    },

    'down' => function (Schema $s) {
        $s->dropIndex('alumni', 'idx_alumni_group');
        $s->dropIndex('alumni', 'idx_alumni_external');

        foreach (array(
            'gpax', 'entrance_semester', 'entrance_year', 'status_name', 'status_code',
            'major_name', 'grade_name', 'group_name', 'group_code', 'gender',
            'external_id', 'external_source',
        ) as $column) {
            $s->dropColumn('alumni', $column);
        }
    },
);
