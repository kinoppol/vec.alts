<?php
/**
 * Current students share the alumni table.
 *
 * The person is the same before and after graduation, so keeping them in a
 * separate table would mean copying a row across on the day they finish and
 * holding two places in step forever. A state column does the job, and every
 * existing row defaults to "graduated" so the reports keep the meaning they
 * already had the moment this runs.
 */
return array(

    'name' => 'เพิ่มกลุ่มศิษย์ปัจจุบัน',

    'up' => function (Schema $s) {

        // 'studying' or 'graduated', validated in PHP by study_states().
        $s->addColumn(
            'alumni',
            'study_state',
            'VARCHAR(20) NOT NULL DEFAULT "graduated"',
            'graduation_year'
        );

        // What a student intends to do once they finish, collected while they
        // are still studying. The employment survey proper only begins after
        // graduation, and lives in alumni_status as before.
        $s->addColumn('alumni', 'plan_after', 'VARCHAR(30) NOT NULL DEFAULT ""', 'study_state');
        $s->addColumn('alumni', 'plan_note', 'VARCHAR(255) NOT NULL DEFAULT ""', 'plan_after');

        // Every report filters graduates out of the current students, so the
        // pair is worth an index of its own.
        $s->addIndex('alumni', 'idx_alumni_state', '`school_id`, `study_state`');
    },

    'down' => function (Schema $s) {
        $s->dropIndex('alumni', 'idx_alumni_state');
        $s->dropColumn('alumni', 'plan_note');
        $s->dropColumn('alumni', 'plan_after');
        $s->dropColumn('alumni', 'study_state');
    },
);
