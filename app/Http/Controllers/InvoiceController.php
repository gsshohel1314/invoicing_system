<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Mpdf\Output\Destination;
use Illuminate\Support\Facades\Log;

class InvoiceController extends Controller
{
    public function streamPdf(Invoice $invoice)
    {
        try {
            // Create new mPDF instance
            $mpdf = new \Mpdf\Mpdf([
                'mode'              => 'utf-8',
                'format'            => 'A4',
                'default_font_size' => 12,
                'default_font'      => 'solaimanlipi',
                'tempDir'           => storage_path('tmp'),
                'fontDir'           => [
                    base_path('vendor/mpdf/mpdf/ttfonts'),
                    resource_path('fonts'),
                ],
                'fontdata'          => [
                    'solaimanlipi' => [
                        'R'  => 'SolaimanLipi.ttf',
                        'useOTL' => 0xFF,
                        'useKashida' => 75,
                    ],
                ],
                'useSubstitutions'  => true,
                'useAdobeCJK'       => true,
                'autoScriptToLang'  => true,
                'autoLangToFont'    => true,
                // 'ignore_table_percents' => true,
                // 'ignore_table_widths'   => true,
                'use_kwt'           => true,
                'shrink_tables_to_fit'  => 1,
                'margin_left'       => 10,
                'margin_right'      => 10,
                'margin_top'        => 15,
                'margin_bottom'     => 15,
            ]);

            // Load relations (if not loaded, load it)
            $invoice->loadMissing(['invoiceItems', 'vtsAccount']);

            // Blade render
            $html = view('pdf.invoice', compact('invoice'))->render();


            // Write HTML content to PDF
            $mpdf->WriteHTML($html);

            // PDF as string
            $filename = "invoice_{$invoice->invoice_number}.pdf";
            $pdfContent = $mpdf->Output($filename, Destination::STRING_RETURN);

            return response($pdfContent)
                    ->header('Content-Type', 'application/pdf')
                    ->header('Content-Disposition', "attachment; filename=\"{$filename}\"")
                    ->header('Content-Length', strlen($pdfContent))
                    ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
                    ->header('Pragma', 'no-cache')
                    ->header('Expires', '0');
        } catch (\Mpdf\MpdfException $e) {
            Log::error("mPDF exception for invoice {$invoice->id}: " . $e->getMessage());
            return response()->json(['error' => 'PDF generation failed. Please try again.'], 500);
        } catch (\Exception $e) {
            Log::error("General error in PDF generation for invoice {$invoice->id}: " . $e->getMessage());
            return response()->json(['error' => 'An unexpected error occurred.'], 500);
        }
    }
}
