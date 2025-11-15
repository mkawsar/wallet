<?php

namespace Tests\Unit\Repositories;

use App\Models\Transaction;
use App\Repositories\TransactionRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Mockery;
use Tests\TestCase;

class TransactionRepositoryTest extends TestCase
{
    private TransactionRepository $repository;

    private $modelMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->modelMock = Mockery::mock(Transaction::class);
        $this->repository = new TransactionRepository($this->modelMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_get_user_transactions_returns_paginated_results(): void
    {
        $userId = 1;
        $perPage = 50;

        $queryMock = Mockery::mock();
        $paginatorMock = Mockery::mock(LengthAwarePaginator::class);

        $this->modelMock
            ->shouldReceive('newQuery')
            ->once()
            ->andReturn($queryMock);

        $queryMock
            ->shouldReceive('where')
            ->once()
            ->with(Mockery::on(function ($callback) {
                return is_callable($callback);
            }))
            ->andReturnSelf();

        $queryMock
            ->shouldReceive('with')
            ->once()
            ->with(Mockery::on(function ($relations) {
                return is_array($relations) &&
                       isset($relations['sender']) &&
                       isset($relations['receiver']);
            }))
            ->andReturnSelf();

        $queryMock
            ->shouldReceive('orderBy')
            ->once()
            ->with('created_at', 'desc')
            ->andReturnSelf();

        $queryMock
            ->shouldReceive('orderBy')
            ->once()
            ->with('id', 'desc')
            ->andReturnSelf();

        $queryMock
            ->shouldReceive('paginate')
            ->once()
            ->with($perPage)
            ->andReturn($paginatorMock);

        $result = $this->repository->getUserTransactions($userId, $perPage);

        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
    }

    public function test_create_transaction_creates_new_transaction(): void
    {
        $senderId = 1;
        $receiverId = 2;
        $amount = 100.00;
        $commissionFee = 1.50;

        $transaction = new Transaction;
        $transaction->id = 1;
        $transaction->sender_id = $senderId;
        $transaction->receiver_id = $receiverId;
        $transaction->amount = $amount;
        $transaction->commission_fee = $commissionFee;

        $this->modelMock
            ->shouldReceive('create')
            ->once()
            ->with([
                'sender_id' => $senderId,
                'receiver_id' => $receiverId,
                'amount' => $amount,
                'commission_fee' => $commissionFee,
            ])
            ->andReturn($transaction);

        $result = $this->repository->createTransaction($senderId, $receiverId, $amount, $commissionFee);

        $this->assertInstanceOf(Transaction::class, $result);
        $this->assertEquals($senderId, $result->sender_id);
        $this->assertEquals($receiverId, $result->receiver_id);
        $this->assertEquals($amount, $result->amount);
        $this->assertEquals($commissionFee, $result->commission_fee);
    }

    public function test_find_with_relations_returns_transaction_with_relationships(): void
    {
        $transactionId = 1;

        $transaction = new Transaction;
        $transaction->id = $transactionId;

        $queryMock = Mockery::mock();

        $this->modelMock
            ->shouldReceive('newQuery')
            ->once()
            ->andReturn($queryMock);

        $queryMock
            ->shouldReceive('with')
            ->once()
            ->with(Mockery::on(function ($relations) {
                return is_array($relations) &&
                       isset($relations['sender']) &&
                       isset($relations['receiver']);
            }))
            ->andReturnSelf();

        $queryMock
            ->shouldReceive('find')
            ->once()
            ->with($transactionId)
            ->andReturn($transaction);

        $result = $this->repository->findWithRelations($transactionId);

        $this->assertInstanceOf(Transaction::class, $result);
        $this->assertEquals($transactionId, $result->id);
    }

    public function test_find_with_relations_returns_null_when_not_found(): void
    {
        $transactionId = 999;

        $queryMock = Mockery::mock();

        $this->modelMock
            ->shouldReceive('newQuery')
            ->once()
            ->andReturn($queryMock);

        $queryMock
            ->shouldReceive('with')
            ->once()
            ->andReturnSelf();

        $queryMock
            ->shouldReceive('find')
            ->once()
            ->with($transactionId)
            ->andReturn(null);

        $result = $this->repository->findWithRelations($transactionId);

        $this->assertNull($result);
    }
}
