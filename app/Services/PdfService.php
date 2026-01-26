<?php

namespace App\Services;

use Mpdf\Mpdf;
use App\Models\Invoice;
use Mpdf\Output\Destination;
use Illuminate\Support\Facades\Config;

class PdfService
{
    public function getMpdfInstance(array $override = []): Mpdf
    {
        // Get the default config from config/mpdf.php
        $config = Config::get('mpdf', []);

        // If you have any overrides, merge them.
        $config = array_merge($config, $override);

        return new Mpdf($config);
    }

    public function generateInvoicePdf(Invoice $invoice): string
    {
        $mpdf = $this->getMpdfInstance();

        // Load relations
        $invoice->loadMissing(['invoiceItems', 'vtsAccount']);

        // Blade render
        $html = view('pdf.invoice', compact('invoice'))->render();

        // Write HTML content to PDF
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', Destination::STRING_RETURN);
    }
}