<?php

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

class MongoModelArchitectureTest extends TestCase
{
    public function test_models_extend_correct_parents(): void
    {
        $models = glob(dirname(__DIR__, 2).'/app/Models/*.php');

        $mongoModels = ['Conversation.php', 'Message.php', 'ActivityEvent.php', 'MongoModel.php'];

        foreach ($models as $model) {
            $source = file_get_contents($model);
            $filename = basename($model);

            if ($filename === 'MongoModel.php') {
                $this->assertStringContainsString('MongoDB\Laravel\Eloquent\Model', $source, $model);
                continue;
            }

            if (in_array($filename, $mongoModels)) {
                $this->assertStringContainsString('class '.pathinfo($model, PATHINFO_FILENAME).' extends MongoModel', $source, $model);
            } else {
                if ($filename === 'User.php') {
                    $this->assertStringContainsString('class User extends Authenticatable', $source, $model);
                } elseif ($filename === 'PersonalAccessToken.php') {
                    $this->assertStringContainsString('class PersonalAccessToken extends SanctumPersonalAccessToken', $source, $model);
                } else {
                    $this->assertStringContainsString('class '.pathinfo($model, PATHINFO_FILENAME).' extends Model', $source, $model);
                }
            }
        }
    }

    public function test_inventory_has_a_room_night_sql_model(): void
    {
        $model = dirname(__DIR__, 2).'/app/Models/RoomNight.php';

        $this->assertFileExists($model);
        $this->assertStringContainsString('class RoomNight extends Model', file_get_contents($model));
    }
}
