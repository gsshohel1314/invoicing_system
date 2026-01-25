<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Mail\InvoiceIssued;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;

class SendInvoiceEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $invoice;
    public $tries = 3;
    public $backoff = [60, 300];

    public function __construct(Invoice $invoice)
    {
        $this->invoice = $invoice;
    }

    public function handle()
    {
        if (!$this->invoice->vtsAccount->email) {
            Log::info("No email found for invoice {$this->invoice->id}");
            return;
        }

        Mail::to($this->invoice->vtsAccount->email)->send(new InvoiceIssued($this->invoice));

        Log::info("Invoice email sent for {$this->invoice->invoice_number}");

        $this->invoice->update(['email_status' => 'sent']);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("Failed to send invoice email {$this->invoice->id}: " . $exception->getMessage());

        $this->invoice->update(['email_status' => 'failed']);
    }
}
