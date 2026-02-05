<?php

declare(strict_types=1);

use Foxws\Streamer\Support\CommandBuilder;

it('sanitizes leading dashes in input path', function () {
    $builder = CommandBuilder::make()
        ->addVideoStream('-Foo-Bar-(Foo-Bar-Foo).mp4', 'out.m4v');

    $args = $builder->buildArray();

    // Find the config that contains our stream
    $found = false;
    foreach ($args as $arg) {
        if (is_string($arg) && strpos($arg, 'in=') !== false) {
            expect($arg)->toContain('in=./-Foo-Bar-(Foo-Bar-Foo).mp4')
                ->and($arg)->toContain('stream=video')
                ->and($arg)->toContain('output=out.m4v');
            $found = true;
            break;
        }
    }
    expect($found)->toBeTrue();
});

it('normalizes smart quotes in input path', function () {
    $builder = CommandBuilder::make()
        ->addVideoStream('-foo-bar-foo-bar-i'm-foo-_1.m4v', 'out.m4v');

    $args = $builder->buildArray();

    $found = false;
    foreach ($args as $arg) {
        if (is_string($arg) && strpos($arg, 'in=') !== false) {
            expect($arg)->toContain("in=./-foo-bar-foo-bar-i'm-foo-_1.m4v");
            $found = true;
            break;
        }
    }
    expect($found)->toBeTrue();
});

it('replaces commas with hyphens in input path', function () {
    $builder = CommandBuilder::make()
        ->addVideoStream('name,with,commas.mp4', 'out.m4v');

    $args = $builder->buildArray();

    $found = false;
    foreach ($args as $arg) {
        if (is_string($arg) && strpos($arg, 'in=') !== false) {
            expect($arg)->toContain('in=name-with-commas.mp4');
            $found = true;
            break;
        }
    }
    expect($found)->toBeTrue();
});

it('sanitizes output filenames similarly', function () {
    $builder = CommandBuilder::make()
        ->addVideoStream('input.mp4', '-bad,name,i'm.m4v');

    $args = $builder->buildArray();

    $found = false;
    foreach ($args as $arg) {
        if (is_string($arg) && strpos($arg, 'output=') !== false) {
            expect($arg)->toContain("output=./-bad-name-i'm.m4v");
            $found = true;
            break;
        }
    }
    expect($found)->toBeTrue();
});
