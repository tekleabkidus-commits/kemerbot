<?php

declare(strict_types=1);

use App\Models\TrackingLink;

it('redirects a valid code to our bot deep link and counts the click', function () {
    $link = TrackingLink::factory()->create(['code' => 'summer24']);

    $response = $this->get('/r/summer24');

    $response->assertStatus(302)
        ->assertRedirect('https://t.me/KemerBetTestBot?start=summer24');

    expect($link->refresh()->clicks_count)->toBe(1);
});

it('counts every click', function () {
    $link = TrackingLink::factory()->create(['code' => 'promo']);

    $this->get('/r/promo');
    $this->get('/r/promo');
    $this->get('/r/promo');

    expect($link->refresh()->clicks_count)->toBe(3);
});

it('404s unknown codes without counting anything', function () {
    $this->get('/r/nosuchcode')->assertNotFound();
});

it('404s inactive links', function () {
    TrackingLink::factory()->inactive()->create(['code' => 'oldcamp']);

    $this->get('/r/oldcamp')->assertNotFound();
});

it('rejects hostile codes with 404 — never an open redirect', function (string $hostile) {
    $response = $this->get('/r/'.$hostile);

    $response->assertNotFound();
    expect($response->headers->get('Location'))->toBeNull();
})->with([
    'url as code' => 'https:%2F%2Fevil.com',
    'path traversal' => '..%2F..%2Fetc',
    'at-host trick' => '%40evil.com',
    'double slash' => '%2F%2Fevil.com',
    'overlong' => str_repeat('a', 65),
    'space' => 'a%20b',
]);

it('only ever redirects to t.me with the exact validated code', function () {
    // Even a weird-but-valid code can only land inside our own start param.
    TrackingLink::factory()->create(['code' => 'a_b-C9']);

    $this->get('/r/a_b-C9')
        ->assertRedirect('https://t.me/KemerBetTestBot?start=a_b-C9');
});
