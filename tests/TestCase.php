<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Private uploads go to a throwaway disk in every test.
        Storage::fake(config('researchhub.disk'));
        $this->withoutVite();
    }
}
