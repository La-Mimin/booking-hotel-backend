<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MidtransVerify
{
    public function handle(Request $request, Closure $next)
    {
        $serverKey = config('midtrans.server_key');

        $orderId = $request->input('order_id', '');
        $statusCode = $request->input('status_code', '');
        $grossAmount = $request->input('gross_amount', '');
        $signatureKey = $request->input('signature_key', '');

        $computedSignature = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);

        if (!hash_equals($computedSignature, $signatureKey)) {
            Log::warning('Midtrans Signature Invalid', [
                'order_id' => $orderId,
                'status_code' => $statusCode,
                'gross_amount' => $grossAmount,
                'ip' => $request->ip(),
            ]);

            return response()->json(['message' => 'Invalid signature'], 403);
        }

        return $next($request);
    }
}
