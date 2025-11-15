<?php

namespace App\Services;

use App\Events\TransactionCompleted;
use App\Repositories\TransactionRepository;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransactionService
{
    private const COMMISSION_RATE = 0.015; // 1.5%

    public function __construct(
        private TransactionRepository $transactionRepository,
        private UserRepository $userRepository
    ) {}

    /**
     * Get user transactions and balance.
     */
    public function getUserTransactions(int $userId): array
    {
        $user = $this->userRepository->getUser($userId);
        if (! $user) {
            throw new \RuntimeException('User not found.');
        }

        $transactions = $this->transactionRepository->getUserTransactions($userId);

        return [
            'balance' => (float) $user->balance,
            'transactions' => $transactions,
        ];
    }

    /**
     * Execute a money transfer.
     *
     * @throws ValidationException
     */
    public function executeTransfer(int $senderId, int $receiverId, float $amount): array
    {
        // Validate receiver exists
        $receiver = $this->userRepository->find($receiverId);
        if (! $receiver) {
            throw ValidationException::withMessages([
                'receiver_id' => ['The selected receiver does not exist.'],
            ]);
        }

        // Validate sender cannot send to themselves
        if ($senderId === $receiverId) {
            throw ValidationException::withMessages([
                'receiver_id' => ['You cannot send money to yourself.'],
            ]);
        }

        // Calculate commission and total debit
        $commissionFee = round($amount * self::COMMISSION_RATE, 2);
        $totalDebit = $amount + $commissionFee;

        // Use database transaction to ensure atomicity
        try {
            DB::beginTransaction();

            // Lock sender and receiver rows for update to prevent race conditions
            $sender = $this->userRepository->findWithLock($senderId);
            if (! $sender) {
                throw ValidationException::withMessages([
                    'sender_id' => ['Sender not found.'],
                ]);
            }

            $receiver = $this->userRepository->findWithLock($receiverId);
            if (! $receiver) {
                throw ValidationException::withMessages([
                    'receiver_id' => ['Receiver not found.'],
                ]);
            }

            // Validate sender has sufficient balance
            if ($sender->balance < $totalDebit) {
                throw ValidationException::withMessages([
                    'amount' => [
                        'Insufficient balance. Required: '.number_format((float) $totalDebit, 2).
                        ', Available: '.number_format((float) $sender->balance, 2),
                    ],
                ]);
            }

            // Update balances
            $this->userRepository->updateBalance($sender, -$totalDebit);
            $this->userRepository->updateBalance($receiver, $amount);

            // Create transaction record
            $transaction = $this->transactionRepository->createTransaction(
                $senderId,
                $receiverId,
                $amount,
                $commissionFee
            );

            // Refresh both sender and receiver to get updated balances
            $sender->refresh();
            $receiver->refresh();

            DB::commit();

            // Load transaction with relationships
            $transaction = $this->transactionRepository->findWithRelations($transaction->id);

            // Broadcast event to both sender and receiver (synchronously for immediate delivery)
            try {
                $broadcastDriver = config('broadcasting.default');
                \Log::info('Broadcasting transaction', [
                    'transaction_id' => $transaction->id,
                    'sender_id' => $transaction->sender_id,
                    'receiver_id' => $transaction->receiver_id,
                    'broadcast_driver' => $broadcastDriver,
                    'pusher_key' => $broadcastDriver === 'pusher' ? (config('broadcasting.connections.pusher.key') ? 'configured' : 'missing') : 'n/a',
                ]);

                // Broadcast synchronously (not queued) to ensure immediate delivery
                $event = new TransactionCompleted($transaction);
                broadcast($event);

                \Log::info('Transaction broadcast sent successfully', [
                    'transaction_id' => $transaction->id,
                    'channels' => ['user.'.$transaction->sender_id, 'user.'.$transaction->receiver_id],
                ]);
            } catch (\Exception $e) {
                // Log but don't fail the transaction if broadcasting fails
                \Log::error('Broadcasting failed', [
                    'transaction_id' => $transaction->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }

            return [
                'transaction' => $transaction,
                'new_balance' => (float) $sender->balance,
            ];
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            throw new \RuntimeException('Transfer failed: '.$e->getMessage(), 0, $e);
        }
    }
}
