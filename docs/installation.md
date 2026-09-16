---
section: Getting Started
order: 1
---

# Installation

## Requirements

| Requirement | Notes |
| --- | --- |
| PHP | 8.3 or newer |
| Laravel | 12 or 13 |
| Shaka Streamer | The [binary](https://github.com/shaka-project/shaka-streamer) must be installed separately (`pip install shaka-streamer`) |

## Install the package

```bash
composer require foxws/laravel-streamer
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag="streamer-config"
```

Verify the binary is accessible:

```bash
php artisan streamer:info
```

Continue to [Usage](./usage.md) for a walkthrough of the API.
