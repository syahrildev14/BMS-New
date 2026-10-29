<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\SupplierBill;
use Illuminate\Console\Command;

/**
 * Daily sweep: any ISSUED Invoice/SupplierBill whose due_date has passed and
 * is still unpaid becomes OVERDUE. Status is a plain column (not a computed
 * accessor) so it can be filtered/grouped on directly in
 * FinanceAnalyticsService's SQL aggregates and shown in list views without
 * recomputing "is this late" per row on every request.
 */
class MarkOverdueFinanceDocuments extends Command
{
    protected $signature = 'finance:mark-overdue';

    protected $description = 'Flip ISSUED invoices/supplier bills past their due_date to OVERDUE';

    public function handle(): int
    {
        $today = now()->toDateString();

        $overdueInvoices = Invoice::where('status', 'ISSUED')
            ->whereDate('due_date', '<', $today)
            ->update(['status' => 'OVERDUE']);

        $overdueBills = SupplierBill::where('status', 'ISSUED')
            ->whereDate('due_date', '<', $today)
            ->update(['status' => 'OVERDUE']);

        $this->info("Marked {$overdueInvoices} invoice(s) and {$overdueBills} supplier bill(s) as OVERDUE.");

        return self::SUCCESS;
    }
}
