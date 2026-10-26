<?php

test('returns a successful response', function () {
    $response = $this->get(route('home'));

    $response->assertOk();
});

test('the header hides the technician entry button unless enabled', function () {
    $this->get(route('home'))->assertDontSee('Untuk Teknisi');

    config(['sejuk.show_technician_entry' => true]);

    $this->get(route('home'))->assertSee('Untuk Teknisi');
});
