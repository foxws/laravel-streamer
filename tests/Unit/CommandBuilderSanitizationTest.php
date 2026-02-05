<?php

declare(strict_types=1);

use Foxws\Streamer\Support\CommandBuilder;

it('sanitizes leading dashes in input path', function () {
    $builder = CommandBuilder::make()
        ->addVideoStream('-Foo-Bar-(Foo-Bar-Foo).mp4', 'out.m4v');

    $args = $builder->buildArray();

    expect($args)->toHaveCount(1)
        ->and($args[0])->toContain('in=./-Foo-Bar-(Foo-Bar-Foo).mp4')
        ->and($args[0])->toContain('stream=video')
        ->and($args[0])->toContain('output=out.m4v');
});

it('normalizes smart quotes in input path', function () {
    $builder = CommandBuilder::make()
        ->addVideoStream('-foo-bar-foo-bar-i’m-foo-_1.m4v', 'out.m4v');

    $args = $builder->buildArray();

    expect($args[0])->toContain("in=./-foo-bar-foo-bar-i'm-foo-_1.m4v");
});

it('replaces commas with hyphens in input path', function () {
    $builder = CommandBuilder::make()
        ->addVideoStream('name,with,commas.mp4', 'out.m4v');

    $args = $builder->buildArray();

    expect($args[0])->toContain('in=name-with-commas.mp4');
});

it('sanitizes output filenames similarly', function () {
    $builder = CommandBuilder::make()
        ->addVideoStream('input.mp4', '-bad,name,i’m.m4v');

    $args = $builder->buildArray();

    expect($args[0])->toContain("output=./-bad-name-i'm.m4v");
});
