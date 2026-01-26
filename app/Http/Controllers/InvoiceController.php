<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\PdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class InvoiceController extends Controller
{
    protected $pdfService;

    public function __construct(PdfService $pdfService)
    {
        $this->pdfService = $pdfService;
    }

    public function streamPdf(Invoice $invoice)
    {
        try {
            $pdfContent = $this->pdfService->generateInvoicePdf($invoice);

            $filename = "invoice_{$invoice->invoice_number}.pdf";

            return response($pdfContent)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', "attachment; filename=\"{$filename}\"")
                ->header('Content-Length', strlen($pdfContent))
                ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
                ->header('Pragma', 'no-cache')
                ->header('Expires', '0');
        } catch (\Exception $e) {
            Log::error("PDF generation failed for invoice {$invoice->id}: " . $e->getMessage());
            return response()->json(['error' => 'PDF generation failed.'], 500);
        }
    }
}
