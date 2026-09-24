<?php

declare(strict_types=1);

use Foxws\Streamer\Support\StreamerResult;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->root = sys_get_temp_dir().'/test-local-move-'.bin2hex(random_bytes(4));
    $this->source = "{$this->root}/source";
    $this->target = Storage::build(['driver' => 'local', 'root' => "{$this->root}/target"]);

    mkdir("{$this->source}/stream", 0777, true);
    file_put_contents("{$this->source}/index.mpd", '<MPD/>');
    file_put_contents("{$this->source}/stream/video.mp4", 'video data');
});

afterEach(function () {
    (new Filesystem)->deleteDirectory($this->root);
});

it('moves files onto a local disk instead of copying them', function () {
    $inode = fileinode("{$this->source}/stream/video.mp4");

    (new StreamerResult('success', null, $this->source))->toDisk($this->target, null, true, 'playlist/');

    expect($this->target->get('playlist/index.mpd'))->toBe('<MPD/>')
        ->and($this->target->get('playlist/stream/video.mp4'))->toBe('video data')
        ->and(fileinode($this->target->path('playlist/stream/video.mp4')))->toBe($inode)
        ->and(is_dir($this->source))->toBeFalse();
});

it('applies visibility to moved files', function () {
    (new StreamerResult('success', null, $this->source))->toDisk($this->target, 'private');

    expect($this->target->getVisibility('index.mpd'))->toBe('private')
        ->and($this->target->getVisibility('stream/video.mp4'))->toBe('private');
});

it('copies instead of moving when temporary files are kept', function () {
    (new StreamerResult('success', null, $this->source))->toDisk($this->target, null, false);

    expect($this->target->get('stream/video.mp4'))->toBe('video data')
        ->and(file_exists("{$this->source}/stream/video.mp4"))->toBeTrue();
});
