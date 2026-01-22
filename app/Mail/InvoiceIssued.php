<?php

namespace App\Mail;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Queue\SerializesModels;

class InvoiceIssued extends Mailable
{
    use Queueable, SerializesModels;

    public $invoice;

    public function __construct(Invoice $invoice)
    {
        $this->invoice = $invoice;
    }

    public function build()
    {
        // Signed URL — valid for 7 days
        // $expiration = now()->addMinutes(3);
        $expiration = now()->addDays(7);
        $downloadUrl = URL::temporarySignedRoute('invoice.pdf', $expiration, [
            'invoice' => $this->invoice->id
        ]);

        return $this->subject("Your Invoice #{$this->invoice->invoice_number}")
                    ->view('emails.invoice_issued')
                    ->with([
                        'downloadUrl' => $downloadUrl,
                        'invoice_number' => $this->invoice->invoice_number,
                        'total_amount' => number_format($this->invoice->total_amount, 2),
                        'due_date' => $this->invoice->due_date->format('d M Y') ?? 'N/A',
                    ]);
    }
}