<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SalesInvoice;
use App\Models\User;

class FillUserNamesInInvoices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:fill-user-names-in-invoices';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
      $invoices = SalesInvoice::all();

        foreach ($invoices as $invoice) {
            if (empty($invoice->user_name)) {
                $user = User::withTrashed()->find($invoice->user_id);
                $invoice->user_name = $user ? $user->name : 'مستخدم محذوف';
            }

            if (empty($invoice->cashier_name)) {
                $cashier = User::withTrashed()->find($invoice->cashier_id);
                $invoice->cashier_name = $cashier ? $cashier->name : 'مستخدم محذوف';
            }

            $invoice->save();
        }

        $this->info('تم ملء أسماء المستخدمين في الفواتير بنجاح');
        return 0;
    }
}
