<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Payment terms
    |--------------------------------------------------------------------------
    |
    | Number of days after an Invoice/SupplierBill is ISSUED before it's due.
    | due_date = issued_at (now) + N days. Used by both the approve step (to
    | stamp due_date) and the overdue-detection command (to flip ISSUED rows
    | past due_date to OVERDUE).
    |
    */
    'invoice_due_days' => (int) env('FINANCE_INVOICE_DUE_DAYS', 14),
    'supplier_bill_due_days' => (int) env('FINANCE_SUPPLIER_BILL_DUE_DAYS', 14),
];
