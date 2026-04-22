<?php

namespace App\Services;

use App\Models\Purchase;
use Mpdf\Mpdf;

class PurchasePdfService
{
    public function generate(Purchase $purchase): string
    {
        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 15,
            'margin_bottom' => 15,
        ]);

        $html = $this->renderView($purchase);
        
        $mpdf->WriteHTML($html);

        // Return the PDF as a string
        return $mpdf->Output('', 'S');
    }

    public function download(Purchase $purchase): \Symfony\Component\HttpFoundation\Response
    {
        $pdf = $this->generate($purchase);
        $filename = 'purchase_order_#' . $purchase->id . '.pdf';

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf;
        }, $filename);
    }

    public function downloadReport($records): \Symfony\Component\HttpFoundation\Response
    {
        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4-L', // Landscape for reports
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 15,
            'margin_bottom' => 15,
        ]);

        $html = view('pdf.purchases_report', [
            'purchases' => $records,
            'clinic' => auth()->user()->clinic ?? \App\Models\Clinic::first(),
            'generated_at' => now()->format('d-m-Y H:i'),
        ])->render();
        
        $mpdf->WriteHTML($html);
        $pdf = $mpdf->Output('', 'S');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf;
        }, 'purchases_report_' . now()->format('YmdHis') . '.pdf');
    }
   
    private function renderView(Purchase $purchase): string
    {
        return view('pdf.purchase', [
            'purchase' => $purchase,
            'clinic' => $purchase->clinic,
            'items' => $purchase->items()->with('product')->get(),
        ])->render();
    }
}
