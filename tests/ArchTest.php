<?php

declare(strict_types=1);

arch('it will not use debugging functions')
    ->expect(['dd', 'dump', 'ray'])
    ->not->toBeUsed()
    ->ignoring('Foxws\\Streamer\\Exporters\\MediaExporter');

arch('classes in src/Support extend nothing or base classes')
    ->expect('Foxws\\Streamer\\Support')
    ->classes()
    ->not->toExtend('Illuminate\\Support\\Facades\\Facade');
