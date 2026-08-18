<?php
/**
 * Public landing page.
 */
class HomeController extends Controller
{
    public function index()
    {
        $summary = $this->repo->centralSummary();

        // Four bars for the hero card, scaled against the largest group so the
        // chart stays readable whatever the absolute numbers are.
        $year = $summary['survey_year'];
        $counts = array(
            'employed' => (int) $this->repo->scalar(
                'SELECT COUNT(*) FROM `{p}alumni_status` WHERE survey_year = ? AND is_draft = 0'
                . ' AND employment_status IN ("employed_match","employed_other")',
                array($year)
            ),
            'study' => (int) $this->repo->scalar(
                'SELECT COUNT(*) FROM `{p}alumni_status` WHERE survey_year = ? AND is_draft = 0'
                . ' AND employment_status = ?',
                array($year, 'study')
            ),
            'freelance' => (int) $this->repo->scalar(
                'SELECT COUNT(*) FROM `{p}alumni_status` WHERE survey_year = ? AND is_draft = 0'
                . ' AND employment_status = ?',
                array($year, 'freelance')
            ),
            'unemployed' => (int) $this->repo->scalar(
                'SELECT COUNT(*) FROM `{p}alumni_status` WHERE survey_year = ? AND is_draft = 0'
                . ' AND employment_status IN ("unemployed","military")',
                array($year)
            ),
        );

        $max = max(1, max($counts));
        $chart = array(
            array('label' => 'มีงานทำ', 'count' => $counts['employed'],
                'height' => round(($counts['employed'] / $max) * 100), 'class' => ''),
            array('label' => 'ศึกษาต่อ', 'count' => $counts['study'],
                'height' => round(($counts['study'] / $max) * 100), 'class' => ' alt'),
            array('label' => 'อิสระ', 'count' => $counts['freelance'],
                'height' => round(($counts['freelance'] / $max) * 100), 'class' => ' flat'),
            array('label' => 'ว่างงาน', 'count' => $counts['unemployed'],
                'height' => round(($counts['unemployed'] / $max) * 100), 'class' => ' flat'),
        );

        $this->renderPublic('landing', array(
            'title'   => 'ติดตามผู้สำเร็จการศึกษา',
            'summary' => $summary,
            'chart'   => $chart,
        ));
    }
}
