<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Concerns\InteractsWithFerlem;

abstract class TestCase extends BaseTestCase
{
    use InteractsWithFerlem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }
}
