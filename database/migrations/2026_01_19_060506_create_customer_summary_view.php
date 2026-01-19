<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

class CreateCustomerSummaryView extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        DB::statement("
            CREATE OR REPLACE VIEW customer_summary AS
            SELECT 
                c.id AS vts_account_id,
                c.name,
                c.customer_type,
                c.status,
                
                -- Current Balance (calculated from customer_ledgers)
                COALESCE((
                    SELECT SUM(cl.credit - cl.debit)
                    FROM customer_ledgers cl
                    WHERE cl.vts_account_id = c.id
                ), 0) AS current_balance,
                
                -- Last Invoice Date & ID
                (
                    SELECT MAX(i.issued_date) 
                    FROM invoices i 
                    WHERE i.vts_account_id = c.id
                ) AS last_invoice_date,
                
                (
                    SELECT i.id 
                    FROM invoices i 
                    WHERE i.vts_account_id = c.id 
                    ORDER BY i.issued_date DESC 
                    LIMIT 1
                ) AS last_invoice_id,
                
                -- Last Payment Date & Amount
                (
                    SELECT MAX(p.payment_date) 
                    FROM payments p 
                    WHERE p.vts_account_id = c.id 
                      AND p.status = 'success'
                ) AS last_pay_date,
                
                (
                    SELECT p.amount 
                    FROM payments p 
                    WHERE p.vts_account_id = c.id 
                      AND p.status = 'success' 
                    ORDER BY p.payment_date DESC 
                    LIMIT 1
                ) AS last_payment_amount
            
            FROM vts_accounts c;
        ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW IF EXISTS customer_summary");
    }
}
