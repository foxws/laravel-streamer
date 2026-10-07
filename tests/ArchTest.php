<?php

declare(strict_types=1);

arch()->preset()->php();

arch()->preset()->security();

arch('it will not use dd(), ddd(), env(), or exit()')
    ->expect(['dd', 'ddd', 'env', 'exit'])
    ->each->not->toBeUsed();

arch('the package source declares strict types')
    ->expect('Foxws\\Streamer')
    ->toUseStrictTypes();

arch('Shaka Streamer runs through laravel-media, not its own processes')
    ->expect('Foxws\\Streamer')
    ->not->toUse(['Illuminate\\Support\\Facades\\Process', 'Symfony\\Component\\Process']);
