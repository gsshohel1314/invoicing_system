<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class InvoiceController extends Controller
{
    public function streamPdf(Invoice $invoice)
    {
        // PDF on-the-fly generate
        $pdf = Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice->load(['invoiceItems', 'vtsAccount']) // relations load
        ]);

        // Direct download
        return $pdf->download("invoice_{$invoice->invoice_number}.pdf");
        
        // or Open in browser
        // return $pdf->stream("invoice_{$invoice->invoice_number}.pdf");
    }
}
