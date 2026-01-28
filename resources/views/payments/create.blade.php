<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Record New Payment - iTracker Admin</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" integrity="sha384-JcKb8q3iqJ61gNV9KGb8thSsNjpSL0n8PARn9HuZOnIxN0hoP+VmmDGMN5t9UJ0Z" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body { 
            background-color: #f8f9fa; 
        }
        .card-header { 
            background-color: #007bff; 
            color: white; 
        }
        .required::after { 
            content: " *"; 
            color: red; 
        }
        .loading { 
            color: #6c757d; 
            font-style: italic; 
        }
        .error-text { 
            color: #dc3545; 
            font-size: 0.875rem; 
        }
        .form-control:focus { 
            border-color: #007bff; 
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25); 
        }
    </style>
</head>
<body>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="card shadow-lg border-0">
                <div class="card-header text-center py-4">
                    <h3 class="mb-0">Record New Payment</h3>
                    <small class="d-block mt-2">Enter payment details manually</small>
                </div>

                <div class="card-body">
                    <form method="POST" action="/payments" id="paymentForm">
                        <div class="row">
                            <!-- Customer Type -->
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="customer_type" class="font-weight-bold required">Customer Type</label>
                                    <select class="form-control" id="customer_type" name="customer_type" required>
                                        <option value="">-- Select customer type --</option>
                                        <option value="retail">Retil</option>
                                        <option value="corporate">Corporate</option>
                                    </select>
                                </div>
                            </div>
    
                            <!-- Customer -->
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="vts_account_id" class="font-weight-bold required">Customer</label>
                                    <select class="form-control" id="vts_account_id" name="vts_account_id" required disabled>
                                        <option value="">-- Select customer type first --</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Unpaid Invoices -->
                        <div class="form-group">
                            <label for="invoice_id" class="font-weight-bold required">Invoice</label>
                            <select class="form-control" id="invoice_id" name="invoice_id" disabled>
                                <option value="">-- Select customer first --</option>
                            </select>
                        </div>

                        <!-- Invoice Items Preview -->
                        <div id="invoice-items-container" class="mt-4 d-none">
                            <div class="row">
                                <!-- Reference -->
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="reference" class="font-weight-bold">Reference / Txn ID</label>
                                        <input type="text" name="reference" id="reference" class="form-control">
                                    </div>
                                </div>
    
                                <!-- Payment method -->
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="method" class="font-weight-bold required">Payment Method</label>
                                        <select name="method" id="method" class="form-control" required>
                                            <option value="cash">Cash</option>
                                            <option value="bkash">bKash</option>
                                            <option value="nagad">Nagad</option>
                                            <option value="bank">Bank</option>
                                        </select>
                                    </div>
                                </div>
    
                                <!-- Payment date -->
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="payment_date" class="font-weight-bold required">Payment Date</label>
                                        <input type="date" name="payment_date" id="payment_date" value="{{ now()->format('Y-m-d') }}" class="form-control" required>
                                    </div>
                                </div>
                            </div>

                            <!-- Total amount -->
                            <div class="form-group">
                                <label for="amount" class="font-weight-bold required">Total Amount Paid (৳)</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="amount" name="amount" step="0.01" min="0.01" required>
                                    
                                    <div class="input-group-append">
                                        <button type="button" id="auto-allocate-btn" class="btn btn-success btn-sm" title="Click to auto allocate the entered amount across invoice items">
                                            <i class="fas fa-magic mr-1"></i> Auto Allocate Payment
                                        </button>
                                    </div>
                                </div>
                                <small class="form-text text-muted mt-1">
                                    Enter the total amount paid by the customer. Use "Auto Allocate" to distribute it automatically.
                                </small>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-2">
                                    <h5><i class="fas fa-list mr-1"></i> Invoice Items</h5>
                                </div>

                                <div class="col-md-10 summary-box">
                                    <div class="row text-center">
                                        <div class="col-md-3">
                                            <div class="label">Total Paid (৳)</div>
                                            <div class="value" id="total-paid">0.00</div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="label">Allocated (৳)</div>
                                            <div class="value" id="total-allocated">0.00</div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="label">Remaining Due (৳)</div>
                                            <div class="value" id="remaining-due">0.00</div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="label">Remaining Advance (৳)</div>
                                            <div class="value" id="remaining-advance">0.00</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div id="invoice-items-table" class="table-responsive border rounded bg-light p-3 mb-4"></div>
                        </div>

                        <!-- Submit -->
                        <div class="text-center mt-5">
                            <button type="submit" class="btn btn-primary btn-lg px-5">
                                <i class="fas fa-save mr-2"></i> Record Payment
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- jQuery & Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        /**
         * =========================
         * Global State & Selectors
         * =========================
         */
        let invoiceTotalDue = 0;

        const $customerType = $('#customer_type');
        const $customer     = $('#vts_account_id');
        const $invoice      = $('#invoice_id');
        const $amount       = $('#amount');

        const $container    = $('#invoice-items-container');
        const $table        = $('#invoice-items-table');

        const $totalPaid    = $('#total-paid');
        const $allocatedEl  = $('#total-allocated');
        const $dueEl        = $('#remaining-due');
        const $advanceEl    = $('#remaining-advance');

        const num = v => parseFloat(v) || 0;

        /**
         * =========================
         * Helpers
         * =========================
         */
        function resetSummary() {
            $totalPaid.text('0.00 ৳').attr('class', 'value text-muted');
            $allocatedEl.text('0.00 ৳').attr('class', 'value text-muted');
            $advanceEl.text('0.00 ৳').attr('class', 'value text-muted');
            $dueEl.text(invoiceTotalDue.toFixed(2) + ' ৳').attr('class', 'value text-danger');
        }

        function resetPayableInputs() {
            $('.payable-input').each(function () {
                const originalAmount = $(this).data('original-amount');
                $(this).val(parseFloat(originalAmount).toFixed(2));
            });
        }

        /**
         * =========================
         * Customer Type Change → Load Customers
         * =========================
         */
        $customerType.on('change', function () {
            const type = $(this).val();            
            $customer.prop('disabled', true).html('<option>Loading customers...</option>');
            $invoice.prop('disabled', true).html('<option>-- Select customer first --</option>');
            $container.addClass('d-none');

            if (!type) return;

            $.ajax({
                url: `/customers/by-type/${type}`,
                success: function(data) {
                    let html = '<option value="">-- Select customer --</option>';
                    $.each(data, function(i, c) {
                        html += `<option value="${c.id}">${c.name}</option>`;
                    });
                    $customer.html(html).prop('disabled', false);
                },
                error: function() {
                    $customer.html('<option value="">Error loading customers</option>');
                }
            });
        });

        /**
         * =========================
         * Customer Change → Load Unpaid Invoices
         * =========================
         */
        $customer.on('change', function () {
            const accountId = $(this).val();
            $invoice.prop('disabled', true).html('<option>Loading invoices...</option>');
            $container.addClass('d-none');

            if (!accountId) return;

            $.ajax({
                url: `/invoices/unpaid/${accountId}`,
                success: function(data) {
                    let html = '<option value="">-- Select invoice --</option>';
                    $.each(data, function(i, inv) {
                        html += `<option value="${inv.id}">${inv.invoice_number} - Due: ${inv.due_amount} ৳</option>`;
                    });
                    $invoice.html(html).prop('disabled', false);
                },
                error: function() {
                    $invoice.html('<option value="">Error loading invoices</option>');
                }
            });            
        });

        /**
         * =========================
         * Invoice Change → Load Items + Summary
         * =========================
         */
        $invoice.on('change', function () {
            const invoiceId = $(this).val();

            if (!invoiceId) {
                $container.addClass('d-none');
                updateSummary(0);
                return;
            }

            $container.removeClass('d-none');
            $table.html(`
                <div class="text-center py-4">
                    <div class="spinner-border text-primary"></div>
                    <p class="mt-2">Loading items...</p>
                </div>
            `);

            $.ajax({
                url: `/invoice-items/${invoiceId}`,
                success: function(items) {
                    let totalDue = 0;
                    let rows = [];

                    if (items.length === 0) {
                        rows.push('<tr><td colspan="5" class="text-center py-4">No items found</td></tr>');
                    } else {
                        items.forEach(item => {
                            const period = (item.period_start || 'N/A') + ' - ' + (item.period_end || 'N/A');
                            const unitPrice = parseFloat(item.unit_price || 0).toFixed(2);
                            const amount = parseFloat(item.amount || 0);

                            totalDue += amount;

                            rows.push(`
                                <tr>
                                    <td>${item.description || 'N/A'}</td>
                                    <td>${period}</td>
                                    <td class="text-right">${unitPrice}</td>
                                    <td class="text-right">${amount.toFixed(2)}</td>
                                    <td class="text-right">
                                        <input 
                                            type="number" 
                                            class="form-control text-right payable-input" 
                                            name="allocated_amount[${item.id}]" 
                                            value="${amount.toFixed(2)}" 
                                            step="0.01" 
                                            min="0" 
                                            max="${amount}"
                                            data-original-amount="${amount}"
                                            readonly
                                        >
                                    </td>
                                </tr>
                            `);
                        });
                    }

                    $table.html(`
                        <table class="table table-bordered table-hover mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>Description</th>
                                    <th>Period</th>
                                    <th class="text-right">Unit Price (৳)</th>
                                    <th class="text-right">Amount (৳)</th>
                                    <th class="text-right">Payable Amount (৳)</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${rows.join('')}
                            </tbody>
                        </table>
                    `);

                    invoiceTotalDue = totalDue;
                    updateSummary(0);
                },
                error: function() {
                    $table.html('<div class="text-danger text-center py-4">Error loading invoice items</div>');
                    updateSummary(0);
                }
            });
        });

        /**
         * =========================
         * Live Update
         * =========================
         */
        $amount.on('input', function () {
            const paid = $(this).val();

            // Empty or zero → reset everything
            if (paid === '' || num(paid) <= 0) {
                resetSummary();
                resetPayableInputs();
                return;
            } else {
                updateSummary();
            }
        });

        /**
         * =========================
         * Auto Allocate
         * =========================
         */
        $(document).on('click', '#auto-allocate-btn', function () {
            let remaining = num($amount.val());

            if (remaining <= 0) {
                alert('Please enter a valid payment amount first!');
                return;
            }

            $('.payable-input').each(function () {
                const max = num($(this).attr('max'));
                const allocate = Math.min(remaining, max);

                $(this).val(allocate.toFixed(2));
                remaining -= allocate;
            });

            updateSummary();

            if (remaining > 0) {
                alert(`Remaining ${remaining.toFixed(2)} ৳ will be added as advance.`);
            }
        });

        /**
         * =========================
         * Summary
         * =========================
         */
        function updateSummary() {
            const paid = num($amount.val());
            
            let allocatedTotal = 0;
            
            if (paid > invoiceTotalDue) {
                allocatedTotal = invoiceTotalDue;
            } else {
                $('.payable-input').each(function () {
                    allocatedTotal += num($(this).val());
                });
            }

            const allocated = Math.min(allocatedTotal, invoiceTotalDue, paid);
            const remainingDue = Math.max(invoiceTotalDue - allocated, 0);
            const remainingAdvance = Math.max(paid - allocated, 0);

            // console.log('Total paid amount', paid);
            // console.log('Invoice total due', invoiceTotalDue);
            // console.log('Allocated amount', allocated);
            // console.log('Remaining due', remainingDue);
            // console.log('Remaining advance', remainingAdvance);
            
            // Total Paid
            $totalPaid.text(paid.toFixed(2) + ' ৳').attr('class', 'value text-primary');

            // Allocated
            $allocatedEl.text(allocated.toFixed(2) + ' ৳').attr('class', 'value text-success');

            // Remaining Due
            if (remainingDue > 0) {
                $dueEl.text(remainingDue.toFixed(2) + ' ৳').attr('class', 'value text-danger');
            } else {
                $dueEl.text('0.00 ৳').attr('class', 'value text-muted');
            }

            // Remaining Advance
            if (remainingAdvance > 0) {
                $advanceEl.text(remainingAdvance.toFixed(2) + ' ৳').attr('class', 'value text-success');
            } else {
                $advanceEl.text('0.00 ৳').attr('class', 'value text-muted');
            }
        }
</script>

</body>
</html>