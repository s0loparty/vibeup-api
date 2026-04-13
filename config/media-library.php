<?php

use Spatie\MediaLibrary\Conversions\Jobs\PerformConversionsJob;
use Spatie\MediaLibrary\MediaCollections\FileAdderFactory;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\ResponsiveImages\Jobs\GenerateResponsiveImagesJob;
use Spatie\MediaLibrary\ResponsiveImages\ResponsiveImageGenerator;
use Spatie\MediaLibrary\Support\FileFactory;

return [

    /*
     * The disk on which to store added files and media.
     */
    'disk_name' => env('MEDIA_DISK', 'minio'),

    /*
     * The disk on which to store generated conversions.
     */
    'conversions_disk' => env('MEDIA_DISK', 'minio'),

    /*
     * The default media model class.
     */
    'media_model' => Media::class,

    /*
     * The default media resolver class.
     */
    'media_resolver' => FileAdderFactory::class,

    /*
     * The queue connection to use for queued jobs.
     */
    'queue_connection_name' => env('QUEUE_CONNECTION', 'sync'),

    /*
     * The queue name for media conversions.
     */
    'queue_name' => '',

    /*
     * The job class for media conversions.
     */
    'job_class' => PerformConversionsJob::class,

    /*
     * The job class for responsive images.
     */
    'responsive_images_job_class' => GenerateResponsiveImagesJob::class,

    /*
     * Maximum file size in kB for uploads.
     */
    'max_file_size' => 1024 * 1024 * 100, // 100MB

    /*
     * The class that generates responsive images.
     */
    'responsive_images' => [
        'class' => ResponsiveImageGenerator::class,
    ],

    /*
     * The class that handles SVG images.
     */
    'svg_sanitizer' => FileFactory::class,

    /*
     * Remote headers configuration.
     * Required for S3 uploads to prevent array_merge() errors.
     */
    'remote' => [
        'extra_headers' => [
            'CacheControl' => 'public, max-age=604800',
        ],
    ],

    /*
     * Custom headers for S3 uploads.
     */
    'custom_headers' => [],

    /*
     * Temporary URL lifetime in seconds (default: 5 minutes).
     */
    'temporary_url_lifetime' => 60 * 5,

];
