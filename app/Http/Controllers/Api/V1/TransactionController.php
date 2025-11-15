<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TransactionCollection;
use App\Http\Resources\TransactionResource;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class TransactionController extends Controller
{
    public function __construct(
        private TransactionService $transactionService
    ) {}

    /**
     * Get transaction history and current balance for authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $data = $this->transactionService->getUserTransactions($userId);

        return response()->json([
            'balance' => $data['balance'],
            'transactions' => new TransactionCollection($data['transactions']),
        ]);
    }

    /**
     * Execute a new money transfer.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'receiver_id' => 'required|integer|exists:users,id|different:'.$request->user()->id,
            'amount' => 'required|numeric|min:0.01|max:999999999.99',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $senderId = $request->user()->id;
            $receiverId = $request->input('receiver_id');
            $amount = (float) $request->input('amount');

            $result = $this->transactionService->executeTransfer($senderId, $receiverId, $amount);

            return response()->json([
                'message' => 'Transfer completed successfully',
                'transaction' => new TransactionResource($result['transaction']),
                'new_balance' => $result['new_balance'],
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
