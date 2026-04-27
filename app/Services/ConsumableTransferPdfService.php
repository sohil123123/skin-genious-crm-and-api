<?php

namespace App\Services;

use App\Models\ConsumableTransfer;
use Mpdf\Mpdf;

class ConsumableTransferPdfService
{
    public function generate(ConsumableTransfer $transfer): string
    {
        $mpdf = new Mpdf([
            'mode'          => 'utf-8',
            'format'        => 'A4',
            'margin_left'   => 15,
            'margin_right'  => 15,
            'margin_top'    => 15,
            'margin_bottom' => 15,
        ]);

        $html = $this->renderView($transfer);
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', 'S');
    }

    public function download(ConsumableTransfer $transfer): \Symfony\Component\HttpFoundation\Response
    {
        $pdf      = $this->generate($transfer);
        $filename = 'consumable_transfer_#' . $transfer->id . '.pdf';

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf;
        }, $filename);
    }

    public function downloadReport($records): \Symfony\Component\HttpFoundation\Response
    {
        $mpdf = new Mpdf([
            'mode'          => 'utf-8',
            'format'        => 'A4-L', // Landscape for reports
            'margin_left'   => 10,
            'margin_right'  => 10,
            'margin_top'    => 15,
            'margin_bottom' => 15,
        ]);

        $html = view('pdf.consumable_transfers_report', [
            'transfers'    => $records,
            'clinic'       => auth()->user()->clinic ?? \App\Models\Clinic::first(),
            'generated_at' => now()->format('d-m-Y H:i'),
        ])->render();

        $mpdf->WriteHTML($html);
        $pdf = $mpdf->Output('', 'S');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf;
        }, 'consumable_transfers_report_' . now()->format('YmdHis') . '.pdf');
    }

    private function renderView(ConsumableTransfer $transfer): string
    {
        return view('pdf.consumable_transfer', [
            'transfer' => $transfer,
            'clinic'   => $transfer->clinic,
            'items'    => $transfer->items()->with('product')->get(),
        ])->render();
    }
}
