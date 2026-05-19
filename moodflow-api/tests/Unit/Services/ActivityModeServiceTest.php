<?php

namespace Tests\Unit\Services;

use App\Services\ActivityModeService;
use InvalidArgumentException;
use Tests\TestCase;

class ActivityModeServiceTest extends TestCase
{
    private ActivityModeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ActivityModeService;
    }

    public function test_all_returns_11_modes(): void
    {
        $modes = $this->service->all();
        $this->assertCount(11, $modes);
        $this->assertArrayHasKey('focus', $modes);
        $this->assertArrayHasKey('rainy_day', $modes);
    }

    public function test_get_returns_mode_by_slug(): void
    {
        $mode = $this->service->get('focus');
        $this->assertEquals('focus', $mode['slug']);
        $this->assertEquals(40, $mode['energy']);
        $this->assertEquals(0.2, $mode['valence']);
        $this->assertTrue($mode['filter_no_lyrics']);
    }

    public function test_get_throws_for_unknown_slug(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown activity mode: unknown_slug');
        $this->service->get('unknown_slug');
    }

    public function test_merge_uses_mode_as_base_neutral_user_mood(): void
    {
        $moodInput = ['source' => 'emoji', 'energy' => 50, 'valence' => 0.0, 'intensity' => 50];
        $merged = $this->service->merge($moodInput, 'focus');
        $this->assertEquals(40, $merged['energy']);
        $this->assertEqualsWithDelta(0.2, $merged['valence'], 0.001);
        $this->assertEquals(30, $merged['intensity']);
        $this->assertEquals('focus', $merged['activity_mode']);
        $this->assertEquals(['study', 'focus', 'instrumental', 'concentration'], $merged['mode_keywords']);
        $this->assertEquals(['lo-fi', 'classique', 'jazz'], $merged['mode_genres_boost']);
        $this->assertTrue($merged['filter_no_lyrics']);
    }

    public function test_merge_applies_user_energy_offset(): void
    {
        $moodInput = ['source' => 'emoji', 'energy' => 100, 'valence' => 0.0, 'intensity' => 50];
        $merged = $this->service->merge($moodInput, 'focus');
        $this->assertEquals(60, $merged['energy']); // 40 + (100-50)*0.4 = 60
    }

    public function test_merge_clamps_energy_to_100(): void
    {
        $moodInput = ['source' => 'emoji', 'energy' => 100, 'valence' => 0.0, 'intensity' => 50];
        $merged = $this->service->merge($moodInput, 'workout');
        $this->assertEquals(100, $merged['energy']); // 90+20=110 → clamped
    }

    public function test_merge_clamps_energy_to_0(): void
    {
        $moodInput = ['source' => 'emoji', 'energy' => 0, 'valence' => 0.0, 'intensity' => 50];
        $merged = $this->service->merge($moodInput, 'sleep');
        $this->assertEquals(0, $merged['energy']); // 10-20=-10 → clamped
    }

    public function test_merge_applies_valence_offset(): void
    {
        $moodInput = ['source' => 'emoji', 'energy' => 50, 'valence' => 1.0, 'intensity' => 50];
        $merged = $this->service->merge($moodInput, 'focus');
        $this->assertEqualsWithDelta(0.4, $merged['valence'], 0.001); // 0.2 + 1.0*0.2
    }

    public function test_merge_uses_user_mood_when_flag_set(): void
    {
        $moodInput = ['source' => 'emoji', 'energy' => 80, 'valence' => 0.7, 'intensity' => 75];
        $merged = $this->service->merge($moodInput, 'commute');
        $this->assertEquals(80, $merged['energy']);
        $this->assertEqualsWithDelta(0.7, $merged['valence'], 0.001);
        $this->assertEquals(75, $merged['intensity']);
        $this->assertEquals('commute', $merged['activity_mode']);
        $this->assertEquals(['commute', 'drive', 'travel'], $merged['mode_keywords']);
    }

    public function test_merge_filter_no_lyrics_defaults_false(): void
    {
        $moodInput = ['source' => 'emoji', 'energy' => 50, 'valence' => 0.0, 'intensity' => 50];
        $merged = $this->service->merge($moodInput, 'workout');
        $this->assertFalse($merged['filter_no_lyrics']);
    }
}
