<?php

declare(strict_types=1);

use App\Services\Bot\TokenRenderer;

it('replaces known tokens', function () {
    $out = (new TokenRenderer)->render('Hi {first_name}!', ['first_name' => 'Sara']);

    expect($out)->toBe('Hi Sara!');
});

it('renders unknown tokens as empty strings, never raw braces', function () {
    $out = (new TokenRenderer)->render('Hi {first_name}{unknown_token}!', ['first_name' => 'Sara']);

    expect($out)->toBe('Hi Sara!');
});

it('is case-insensitive on token names', function () {
    $out = (new TokenRenderer)->render('Hi {First_Name}!', ['first_name' => 'Sara']);

    expect($out)->toBe('Hi Sara!');
});
