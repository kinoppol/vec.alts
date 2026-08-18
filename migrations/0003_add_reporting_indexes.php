<?php
/**
 * Indexes and columns the reporting screens need once a school has real
 * volume. Written with the idempotent Schema helpers so it can be re-run
 * against a database that was hand-patched earlier.
 */
return array(

    'name' => 'เพิ่มดัชนีและคอลัมน์สำหรับรายงาน',

    'up' => function (Schema $s) {

        // The executive dashboard groups by department and year together.
        $s->addIndex('alumni', 'idx_alumni_dept_year', '`school_id`, `department_id`, `graduation_year`');

        // Advisors filter their list by "not yet updated".
        $s->addIndex('alumni_status', 'idx_status_draft', '`school_id`, `is_draft`');

        // Contact attempts, so an advisor can record that someone is
        // unreachable without inventing an employment status for them.
        $s->addColumn('alumni', 'contact_state', 'VARCHAR(20) NOT NULL DEFAULT "ok"', 'status');
        $s->addColumn('alumni', 'contact_note', 'VARCHAR(255) NOT NULL DEFAULT ""', 'contact_state');
        $s->addIndex('alumni', 'idx_alumni_contact', '`school_id`, `contact_state`');

        // Salary banding for reports that must not expose individual figures.
        $s->addColumn('alumni_status', 'salary_band', 'VARCHAR(20) NOT NULL DEFAULT ""', 'salary');
    },

    'down' => function (Schema $s) {
        $s->dropIndex('alumni', 'idx_alumni_contact');
        $s->dropColumn('alumni_status', 'salary_band');
        $s->dropColumn('alumni', 'contact_note');
        $s->dropColumn('alumni', 'contact_state');
        $s->dropIndex('alumni_status', 'idx_status_draft');
        $s->dropIndex('alumni', 'idx_alumni_dept_year');
    },
);
