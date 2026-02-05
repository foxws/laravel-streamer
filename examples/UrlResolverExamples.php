<?php

declare(strict_types=1);

/**
 * Examples of using URL resolvers with Laravel Shaka Packager
 *
 * URL resolvers allow you to customize how URLs are generated for:
 * - Encryption keys (HLS)
 * - Media segments (HLS & DASH)
 * - Playlist/manifest files (HLS & DASH)
 * - Initialization segments (DASH)
 */

use Foxws\Streamer\Http\DynamicDASHManifest;
use Foxws\Streamer\Http\DynamicHLSPlaylist;
use Illuminate\Support\Facades\Storage;

// =============================================================================
// HLS - Basic URL Resolver Setup
// =============================================================================

// Example 1: Setting up all URL resolvers for HLS streaming
$hlsPlaylist = (new DynamicHLSPlaylist('s3'))
    ->setKeyUrlResolver(function ($key) {
        // Generate URL for encryption keys (for DRM/encrypted content)
        return route('video.key', ['key' => $key]);
    })
    ->setMediaUrlResolver(function ($mediaFilename) {
        // Generate URL for media segments
        return Storage::disk('public')->url($mediaFilename);
    })
    ->setPlaylistUrlResolver(function ($playlistFilename) {
        // Generate URL for playlist/manifest files
        return route('video.playlist', ['playlist' => $playlistFilename]);
    })
    ->open('videos/master.m3u8');

// Get the processed playlist
$processedPlaylist = $hlsPlaylist->get();

// Or return as HTTP response
return $hlsPlaylist->toResponse($request);

// =============================================================================
// DASH - Basic URL Resolver Setup
// =============================================================================

// Example 2: Setting up URL resolvers for DASH streaming
$dashManifest = (new DynamicDASHManifest('videos'))
    ->setMediaUrlResolver(function ($filename) {
        // Generate URL for media segments
        return Storage::disk('public')->url($filename);
    })
    ->setInitUrlResolver(function ($filename) {
        // Generate URL for initialization segments
        return Storage::disk('public')->url($filename);
    })
    ->open('videos/manifest.mpd');

// Get the processed manifest
$processedManifest = $dashManifest->get();

// Or return as HTTP response
return $dashManifest->toResponse($request);

// =============================================================================
// CDN Integration - HLS
// =============================================================================

// Example 3: Using a CDN for HLS media delivery
$hlsPlaylist = (new DynamicHLSPlaylist('videos'))
    ->setMediaUrlResolver(function ($filename) {
        $cdnDomain = config('services.cdn.domain');

        return "https://{$cdnDomain}/media/{$filename}";
    })
    ->setPlaylistUrlResolver(function ($filename) {
        $cdnDomain = config('services.cdn.domain');

        return "https://{$cdnDomain}/playlists/{$filename}";
    })
    ->open('master.m3u8');

// =============================================================================
// CDN Integration - DASH
// =============================================================================

// Example 4: Using a CDN for DASH media delivery
$dashManifest = (new DynamicDASHManifest('videos'))
    ->setMediaUrlResolver(function ($filename) {
        return "https://cdn.example.com/segments/{$filename}";
    })
    ->setInitUrlResolver(function ($filename) {
        return "https://cdn.example.com/init/{$filename}";
    })
    ->open('manifest.mpd');

// =============================================================================
// Signed URLs for Security - HLS
// =============================================================================

// Example 5: Using signed URLs for secure HLS content delivery
$hlsPlaylist = (new DynamicHLSPlaylist('private'))
    ->setKeyUrlResolver(function ($key) {
        // Generate a temporary signed URL valid for 1 hour
        return Storage::disk('s3')->temporaryUrl(
            "keys/{$key}",
            now()->addHour()
        );
    })
    ->setMediaUrlResolver(function ($filename) {
        return Storage::disk('s3')->temporaryUrl(
            "segments/{$filename}",
            now()->addHours(2)
        );
    })
    ->open('master.m3u8');

// =============================================================================
// Multi-tenant Applications - HLS
// =============================================================================

// Example 6: Tenant-specific URLs for HLS
$tenantId = 123; // From authenticated user

$hlsPlaylist = (new DynamicHLSPlaylist('tenants'))
    ->setMediaUrlResolver(function ($filename) use ($tenantId) {
        return route('tenant.media', [
            'tenant' => $tenantId,
            'file' => $filename,
        ]);
    })
    ->setPlaylistUrlResolver(function ($filename) use ($tenantId) {
        return route('tenant.playlist', [
            'tenant' => $tenantId,
            'playlist' => $filename,
        ]);
    })
    ->open("tenant-{$tenantId}/master.m3u8");

// =============================================================================
// Multi-tenant Applications - DASH
// =============================================================================

// Example 7: Tenant-specific URLs for DASH
$tenantId = 123; // From authenticated user

$dashManifest = (new DynamicDASHManifest('tenants'))
    ->setMediaUrlResolver(function ($filename) use ($tenantId) {
        return route('tenant.media', [
            'tenant' => $tenantId,
            'file' => $filename,
        ]);
    })
    ->open("tenant-{$tenantId}/manifest.mpd");

// =============================================================================
// Dynamic Key Rotation - HLS
// =============================================================================

// Example 8: Dynamic encryption key URLs with versioning
$hlsPlaylist = (new DynamicHLSPlaylist('videos'))
    ->setKeyUrlResolver(function ($key) {
        // Generate versioned key URLs for rotation
        $version = cache()->get('encryption.key.version', 1);

        return route('video.key', [
            'key' => $key,
            'version' => $version,
        ]);
    })
    ->setMediaUrlResolver(function ($filename) {
        return Storage::disk('public')->url($filename);
    })
    ->open('encrypted-master.m3u8');

// =============================================================================
// Advanced: Conditional URL Logic - HLS
// =============================================================================

// Example 9: Conditional URL generation based on file type
$hlsPlaylist = (new DynamicHLSPlaylist('media'))
    ->setMediaUrlResolver(function ($filename) {
        // Use different CDNs based on file type
        if (str_ends_with($filename, '.m3u8')) {
            return "https://playlist-cdn.example.com/{$filename}";
        }

        if (str_ends_with($filename, '.ts')) {
            return "https://segment-cdn.example.com/{$filename}";
        }

        return "https://default-cdn.example.com/{$filename}";
    })
    ->open('master.m3u8');

// =============================================================================
// Get All Processed Playlists
// =============================================================================

// Example 10: Get all segment playlists (HLS)
$hlsPlaylist = (new DynamicHLSPlaylist('videos'))
    ->setMediaUrlResolver(fn ($file) => "https://cdn.example.com/{$file}")
    ->open('master.m3u8');

// Returns collection of all processed playlists
$allPlaylists = $hlsPlaylist->all();

foreach ($allPlaylists as $playlistPath => $content) {
    // Process each playlist
    echo "Playlist: {$playlistPath}\n";
}

// =============================================================================
// Direct Disk Specification
// =============================================================================

// Example 11: Specify disk when creating instance
$hlsPlaylist = new DynamicHLSPlaylist('s3');
$dashManifest = new DynamicDASHManifest('videos');

// Or change disk later
$hlsPlaylist->fromDisk('local');
$dashManifest->fromDisk('s3');
