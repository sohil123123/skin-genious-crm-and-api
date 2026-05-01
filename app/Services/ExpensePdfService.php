<?php

namespace App\Services;

use App\Models\Clinic;
use Illuminate\Database\Eloquent\Collection;
use Mpdf\Mpdf;
use Symfony\Component\HttpFoundation\Response;

class ExpensePdfService
{
    public function downloadReport(Collection $records): Response
    {
        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4-L',
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 15,
            'margin_bottom' => 15,
        ]);

        $records->loadMissing(['clinic', 'category', 'creator', 'approver']);

        $html = view('pdf.expenses_report', [
            'expenses' => $records,
            'clinic' => auth()->user()->clinic ?? Clinic::first(),
            'generated_at' => now()->format('d-m-Y H:i'),
            'total_amount' => $records->sum('amount'),
        ])->render();

        $mpdf->WriteHTML($html);
        $pdf = $mpdf->Output('', 'S');

        return response()->streamDownload(function () use ($pdf): void {
            echo $pdf;
        }, 'expenses_report_' . now()->format('YmdHis') . '.pdf');
    }
}
