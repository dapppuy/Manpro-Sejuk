<?php

use Illuminate\Support\Facades\Artisan;

it('runs the scheduler when the cron token matches', function () {
    config(['sejuk.cron_token' => 'rahasia-cron-123']);
    Artisan::spy();

    $this->get(route('cron.run', 'rahasia-cron-123'))->assertOk()->assertJson(['ran' => true]);

    Artisan::shouldHaveReceived('call')->with('schedule:run')->once();
});

it('hides the endpoint when the token is wrong or not configured', function (string $configured, string $given) {
    config(['sejuk.cron_token' => $configured]);
    Artisan::spy();

    $this->get(route('cron.run', $given))->assertNotFound();

    Artisan::shouldNotHaveReceived('call');
})->with([
    'wrong token' => ['rahasia-cron-123', 'tebakan'],
    'not configured' => ['', 'apa-saja'],
]);
