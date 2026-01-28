<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\MpesaService;
use App\Models\MpesaPayment;
use App\Http\Requests\StkPushRequest;
use App\Jobs\ProcessMpesaCallback;

class MpesaController extends Controller
{
    /**
     * Initiate STK Push
     */
    public function stkPush(StkPushRequest $request)
    {
        $business = auth()->user()->currentBusiness;
        if (!$business) {
            return response()->json(['success' => false, 'message' => 'No business found'], 403);
        }

        $mpesaService = new MpesaService($business);
        $result = $mpesaService->stkPush(
            $request->phone,
            $request->amount,
            $request->sale_id ?? null,
            $request->reference ?? null,
            'Payment'
        );

        $response = $result['response'];
        $payload = $result['payload'];
        $data = $response->successful() ? $response->json() : ['success' => false, 'body' => $response->body()];

        MpesaPayment::create([
            'business_id' => $business->id,
            'sale_id' => $request->sale_id ?? null,
            'checkout_request_id' => $data['CheckoutRequestID'] ?? null,
            'merchant_request_id' => $data['MerchantRequestID'] ?? null,
            'phone' => $request->phone,
            'amount' => $request->amount,
            'reference' => $request->reference ?? null,
            'account_reference' => $payload['AccountReference'] ?? null,
            'status' => $data['ResponseCode'] ?? ($response->successful() ? 'pending' : 'failed'),
            'raw_response' => $data,
            'metadata' => $payload,
        ]);

        return response()->json($data, $response->successful() ? 200 : 400);
    }

    /**
     * STK Callback
     */
    public function stkCallback(Request $request)
    {
        Log::info('MPESA STK CALLBACK', $request->all());

        // Process synchronously so activation happens immediately even if queue worker isn't running
        ProcessMpesaCallback::dispatchSync($request->all());

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }

    /**
     * Till / Paybill Validation
     */
    public function validation(Request $request)
    {
        Log::info('MPESA VALIDATION', $request->all());

        return response()->json([
            'ResultCode' => 0,
            'ResultDesc' => 'Accepted'
        ]);
    }

    /**
     * Till / Paybill Confirmation
     */
    public function confirmation(Request $request)
    {
        Log::info('MPESA CONFIRMATION', $request->all());

        MpesaPayment::create([
            'receipt' => $request->TransID,
            'phone' => $request->MSISDN,
            'amount' => $request->TransAmount,
            'reference' => $request->BillRefNumber,
            'status' => 'success',
            'raw_response' => $request->all()
        ]);

        return response()->json([
            'ResultCode' => 0,
            'ResultDesc' => 'Confirmed'
        ]);
    }
}
