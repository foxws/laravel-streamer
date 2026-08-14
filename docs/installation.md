---
sidebar_position: 2
---

# Installation

## Requirements

- PHP 8.3+
- Laravel 12 or 13
- [Shaka Streamer](https://github.com/shaka-project/shaka-streamer) binary (`pip install shaka-streamer`)

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
