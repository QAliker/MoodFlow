<?php

namespace Tests\Unit\Models;

use App\Models\ActivityModeUsage;
use PHPUnit\Framework\TestCase;

class ActivityModeUsageTest extends TestCase
{
    public function test_fillable_fields(): void
    {
        $model = new ActivityModeUsage;
        $this->assertEquals(['user_id', 'mode_slug', 'used_at'], $model->getFillable());
    }

    public function test_timestamps_disabled(): void
    {
        $model = new ActivityModeUsage;
        $this->assertFalse($model->timestamps);
    }
}
