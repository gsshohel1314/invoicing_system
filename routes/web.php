<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\PaymentController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return redirect()->route('payments.create');
});

Route::get('/invoice/pdf/{invoice}', [InvoiceController::class, 'streamPdf'])->name('invoice.pdf')->middleware('signed.valid');


Route::get('/customers/by-type/{type}', [PaymentController::class, 'getCustomersByType'])->name('customers.by-type');
Route::get('/invoices/unpaid/{accountId}', [PaymentController::class, 'getUnpaidInvoices'])->name('invoices.unpaid');
Route::get('/invoice-items/{invoiceId}', [PaymentController::class, 'getInvoiceItems'])->name('invoice-items');

Route::get('/payments/create', [PaymentController::class, 'create'])->name('payments.create');
Route::post('/payments', [PaymentController::class, 'store'])->name('payments.store');
