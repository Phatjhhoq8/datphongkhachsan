<?php

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

class MongoModelArchitectureTest extends TestCase
{
    public function test_all_persistent_models_use_mongodb_document_models(): void
    {
        $models = glob(dirname(__DIR__, 2).'/app/Models/*.php');

        foreach ($models as $model) {
            $source = file_get_contents($model);

            if (basename($model) === 'MongoModel.php') {
                $this->assertStringContainsString('MongoDB\Laravel\Eloquent\Model', $source, $model);

                continue;
            }

            if (basename($model) === 'User.php') {
                $this->assertStringContainsString('MongoDB\Laravel\Auth\User as Authenticatable', $source, $model);

                continue;
            }

            if (basename($model) === 'PersonalAccessToken.php') {
                $this->assertStringContainsString('MongoDB\Laravel\Eloquent\DocumentModel', $source, $model);

                continue;
            }

            $this->assertStringContainsString('class '.pathinfo($model, PATHINFO_FILENAME).' extends MongoModel', $source, $model);
        }
    }

    public function test_inventory_has_a_room_night_document_model(): void
    {
        $model = dirname(__DIR__, 2).'/app/Models/RoomNight.php';

        $this->assertFileExists($model);
        $this->assertStringContainsString('class RoomNight extends MongoModel', file_get_contents($model));
    }
}
