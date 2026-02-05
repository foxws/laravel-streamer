#!/bin/bash

# Script to generate test video fixtures with different codecs
# Requires FFmpeg to be installed
# Run from the tests/Fixtures directory: bash generate-fixtures.sh

set -e

# Check if FFmpeg is installed
if ! command -v ffmpeg &> /dev/null; then
    echo "Error: FFmpeg is not installed. Please install FFmpeg first."
    exit 1
fi

echo "Generating test video fixtures..."

# Base settings for all videos
DURATION=5
WIDTH=1280
HEIGHT=720
FPS=30
AUDIO_FREQ=44100

# Generate H.264 (AVC) video
echo "Generating H.264/AVC video (sample_h264.mp4)..."
ffmpeg -f lavfi -i testsrc=duration=${DURATION}:size=${WIDTH}x${HEIGHT}:rate=${FPS} \
       -f lavfi -i sine=frequency=1000:duration=${DURATION}:sample_rate=${AUDIO_FREQ} \
       -c:v libx264 -preset medium -crf 23 -pix_fmt yuv420p \
       -c:a aac -b:a 128k \
       -movflags +faststart \
       -y sample_h264.mp4

# Generate HEVC/H.265 video
echo "Generating HEVC/H.265 video (sample_hevc.mp4)..."
ffmpeg -f lavfi -i testsrc=duration=${DURATION}:size=${WIDTH}x${HEIGHT}:rate=${FPS} \
       -f lavfi -i sine=frequency=1000:duration=${DURATION}:sample_rate=${AUDIO_FREQ} \
       -c:v libx265 -preset medium -crf 28 -pix_fmt yuv420p \
       -tag:v hvc1 \
       -c:a aac -b:a 128k \
       -movflags +faststart \
       -y sample_hevc.mp4

# Generate AV1 video (requires FFmpeg with libaom or libsvtav1)
echo "Generating AV1 video (sample_av1.mp4)..."
if ffmpeg -codecs 2>/dev/null | grep -q 'libaom-av1'; then
    ffmpeg -f lavfi -i testsrc=duration=${DURATION}:size=${WIDTH}x${HEIGHT}:rate=${FPS} \
           -f lavfi -i sine=frequency=1000:duration=${DURATION}:sample_rate=${AUDIO_FREQ} \
           -c:v libaom-av1 -cpu-used 8 -crf 35 -pix_fmt yuv420p \
           -c:a aac -b:a 128k \
           -movflags +faststart \
           -strict experimental \
           -y sample_av1.mp4
elif ffmpeg -codecs 2>/dev/null | grep -q 'libsvtav1'; then
    ffmpeg -f lavfi -i testsrc=duration=${DURATION}:size=${WIDTH}x${HEIGHT}:rate=${FPS} \
           -f lavfi -i sine=frequency=1000:duration=${DURATION}:sample_rate=${AUDIO_FREQ} \
           -c:v libsvtav1 -preset 8 -crf 35 -pix_fmt yuv420p \
           -c:a aac -b:a 128k \
           -movflags +faststart \
           -y sample_av1.mp4
else
    echo "Warning: AV1 encoder not found. Skipping sample_av1.mp4"
    echo "Install FFmpeg with libaom-av1 or libsvtav1 support to generate AV1 samples."
fi

echo ""
echo "Fixture generation complete!"
echo ""
ls -lh sample_*.mp4 2>/dev/null || true
echo ""
echo "Note: These files are gitignored by default. Update .gitignore if you want to commit them."
