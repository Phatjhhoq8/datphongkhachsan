<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class MongoReplicaSetTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        DB::connection('mongodb')->getMongoDB()->drop();
    }

    public function test_test_database_is_a_mongodb_replica_set_and_transactions_are_atomic(): void
    {
        $connection = DB::connection('mongodb');
        $hello = $connection->getMongoClient()
            ->selectDatabase('admin')
            ->command(['hello' => 1])
            ->toArray()[0];

        $this->assertSame('mysql', config('database.default'));
        $this->assertSame('rs0', $hello->setName ?? null);
        $this->assertTrue((bool) ($hello->isWritablePrimary ?? false));

        $connection->transaction(function () use ($connection): void {
            $connection->table('transaction_a')->insert(['key' => 'committed']);
            $connection->table('transaction_b')->insert(['key' => 'committed']);
        });

        $this->assertSame(1, $connection->table('transaction_a')->count());
        $this->assertSame(1, $connection->table('transaction_b')->count());

        try {
            $connection->transaction(function () use ($connection): void {
                $connection->table('transaction_a')->insert(['key' => 'rolled-back']);
                $connection->table('transaction_b')->insert(['key' => 'rolled-back']);

                throw new RuntimeException('rollback');
            });
        } catch (RuntimeException $exception) {
            $this->assertSame('rollback', $exception->getMessage());
        }

        $this->assertSame(1, $connection->table('transaction_a')->count());
        $this->assertSame(1, $connection->table('transaction_b')->count());
    }
}
