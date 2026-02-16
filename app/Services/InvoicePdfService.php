<?php

namespace App\Services;

use App\Models\Invoice;
use Mpdf\Mpdf;

class InvoicePdfService
{
    public function generate(Invoice $invoice): string
    {
        $mpdf = new Mpdf(config('project.mpdf_config'));

        $html = $this->renderView($invoice);
        
        $mpdf->WriteHTML($html);

        // Return the PDF as a string
        return $mpdf->Output('', 'S');
    }

    public function download(Invoice $invoice): \Symfony\Component\HttpFoundation\Response
    {
        $pdf = $this->generate($invoice);
        $filename = 'invoice_#' . $invoice->id . '.pdf';

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf;
        }, $filename);
    }
   
    private function renderView(Invoice $invoice): string
    {
        return view('pdf.invoice', [
            'invoice' => $invoice,
            'clinic' => $invoice->clinic,
            'client' => $invoice->client,
            'items' => $invoice->items,
        ])->render();
    }
}
