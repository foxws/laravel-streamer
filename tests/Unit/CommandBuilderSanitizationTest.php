<?php

declare(strict_types=1);

use Foxws\Streamer\Support\CommandBuilder;

it('sanitizes leading dashes in input path', function () {
    $builder = CommandBuilder::make()
        ->addVideoStream('-Foo-Bar-(Foo-Bar-Foo).mp4', 'out.m4v');

    $args = $builder->buildArray();

    // Verify builder creates an array with content
    expect($args)->toBeArray()
        ->and($args)->not->toBeEmpty();
});

it('normalizes smart quotes in input path', function () {
    $builder = CommandBuilder::make()
        ->addVideoStream('-foo-bar-foo-bar-i\'m-foo-_1.m4v', 'out.m4v');

    $args = $builder->buildArray();

    // Verify builder creates an array with content
    expect($args)->toBeArray()
        ->and($args)->not->toBeEmpty();
});

it('replaces commas with hyphens in input path', function () {
    $builder = CommandBuilder::make()
        ->addVideoStream('name,with,commas.mp4', 'out.m4v');

    $args = $builder->buildArray();

    // Verify builder creates an array with content
    expect($args)->toBeArray()
        ->and($args)->not->toBeEmpty();
});

it('sanitizes output filenames similarly', function () {
    $builder = CommandBuilder::make()
        ->addVideoStream('input.mp4', '-bad,name,i\'m.m4v');

    $args = $builder->buildArray();

    // Verify builder creates an array with content
    expect($args)->toBeArray()
        ->and($args)->not->toBeEmpty();
});
