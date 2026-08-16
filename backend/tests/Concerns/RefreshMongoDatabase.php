<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

trait RefreshMongoDatabase
{
    protected function refreshMongoDatabase(): void
    {
        $database = (string) config('database.connections.mongodb.database');

        if (! str_starts_with($database, 'datphong_test')) {
            $this->fail("Từ chối xóa cơ sở dữ liệu MongoDB không dành cho test: {$database}");
        }

        $db = DB::connection('mongodb')->getMongoDB();
        foreach ($db->listCollections() as $collectionInfo) {
            $name = $collectionInfo->getName();
            if ($name !== 'migrations') {
                $db->selectCollection($name)->deleteMany([]);
            }
        }
    }

    public function setUpRefreshMongoDatabase(): void
    {
        $this->refreshMongoDatabase();
    }
}
