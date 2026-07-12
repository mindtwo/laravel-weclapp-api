<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Mindtwo\LaravelWeclappApi\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);
uses(RefreshDatabase::class)->in('Feature/Factory');
