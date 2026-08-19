<?php
/**
 * The current student's own screen.
 *
 * Students sit in the same table as graduates and sign in the same way; the
 * study_state column is what sends them here instead of to the employment
 * survey. They fill in how to reach them and what they mean to do after
 * finishing, so that on the day they graduate the college already holds
 * current contact details instead of starting the chase from scratch.
 */
class StudentController extends Controller
{
    public function form()
    {
        $this->auth->require_role('student');

        $student = $this->repo->alumni($this->auth->id());
        if ($student === null) {
            flash('error', 'ไม่พบข้อมูลนักศึกษาของบัญชีนี้');
            $this->auth->logout();
            redirect('login');
        }

        if (is_post()) {
            csrf_verify();

            $this->repo->updateAlumniContact($student['id'], array(
                'phone'   => post('phone'),
                'email'   => post('email'),
                'line_id' => post('line_id'),
                'address' => post('address'),
            ));

            // An unknown value lands as '' rather than being rejected: this is
            // a plan, and "not decided yet" is a legitimate answer.
            $this->repo->updateAlumniPlan(
                $student['id'],
                post('plan_after'),
                post('plan_note')
            );

            $this->repo->audit(
                'student.update',
                $student['student_code'],
                'ปรับปรุงข้อมูลศิษย์ปัจจุบัน',
                $this->actor()
            );

            flash('success', 'บันทึกข้อมูลเรียบร้อยแล้ว ขอบคุณที่ช่วยให้ข้อมูลเป็นปัจจุบัน');
            redirect('student');
        }

        $this->render('student/form', array(
            'title'   => 'ข้อมูลของฉัน',
            'student' => $student,
        ));
    }
}
