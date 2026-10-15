<?php

namespace App\Http\Controllers;

use App\Services\CustomerOrderAccess;
use Illuminate\Support\Facades\DB;

class OrderReceiptController extends Controller
{
    public function __invoke(int $order, int $payment)
    {
        if (auth()->user()?->role === 'admin' && !auth()->user()->trashed()) {
            $record = DB::table('sales_orders')->where('order_id', $order)->first();
            abort_if(!$record, 404);
        } else {
            $record = app(CustomerOrderAccess::class)->order($order);
        }
        $receipt = DB::table('payments')->where('payment_id', $payment)->where('reference_type', 'sales_order')->where('reference_id', $order)->where('status', 'completed')->first();
        abort_if(!$receipt, 404);
        return response()->view('orders.receipt', ['order' => $record, 'receipt' => $receipt])->header('Cache-Control', 'private, no-store');
    }
}
