<?php

declare(strict_types=1);

namespace Foxws\Streamer\Support;

/**
 * Content protection scheme for Shaka Streamer's raw key encryption
 * (pipeline_config.encryption.protection_scheme), which maps directly onto
 * Shaka Packager's --protection_scheme flag under the hood.
 *
 * Pattern-based schemes (Cens, Cbcs) apply to video streams only.
 */
enum ProtectionScheme: string
{
    case Cenc = 'cenc';
    case Cbc1 = 'cbc1';
    case Cens = 'cens';
    case Cbcs = 'cbcs';
}
