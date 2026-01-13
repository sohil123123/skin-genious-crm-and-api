<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\Request;

class CommonController extends BaseApiController
{
    public function getClinics(Request $request)
    {
        $response = get_clinics($request);

        return $this->success('Clinics get successfully.', $response);
    }

    public function getUsers(Request $request)
    {
        $response = get_users($request);

        return $this->success('Users get successfully.', $response);
    }

    public function getAssessments(Request $request)
    {
        $response = getAssessments($request);

        return $this->success('Assessments get successfully.', $response);
    }

    public function getTreatmentSessions(Request $request)
    {
        $response = getTreatmentSessions($request);

        return $this->success('Treatment sessions get successfully.', $response);
    }

    public function pdfTest(Request $request)
    {
        $assessment = \App\Models\Assessment::find(50);

        $html = view('pdf.diagnosis-report', ['record' => $assessment])->render();

        $mpdf = new \Mpdf\Mpdf(config('project.mpdf_config'));
        
        // Allow remote images if needed (though we use mostly local or base64)
        $mpdf->showImageErrors = true; 
        
        $mpdf->WriteHTML($html);
        return response($mpdf->Output('diagnosis-report.pdf', 'S'))
                ->header('Content-Type', 'application/pdf');
    }
}
