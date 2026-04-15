<?php

namespace App\Console\Commands;

use Aws\S3\S3Client;
use Illuminate\Console\Command;

class MinioSetupBuckets extends Command
{
    protected $signature = 'minio:setup-buckets';

    protected $description = 'Create MinIO buckets and set public policy';

    public function handle(): int
    {
        $disks = [
            'minio' => 'private',
            'minio-public' => 'public',
        ];

        foreach ($disks as $diskName => $visibility) {
            if (! config("filesystems.disks.{$diskName}")) {
                $this->warn("Disk {$diskName} not configured, skipping...");

                continue;
            }

            $bucket = config("filesystems.disks.{$diskName}.bucket");

            if (! $bucket) {
                $this->warn("No bucket configured for {$diskName}, skipping...");

                continue;
            }

            try {
                $client = $this->createS3Client($diskName);

                // Создаём бакет если не существует
                if ($client->doesBucketExist($bucket)) {
                    $this->info("✓ Bucket {$bucket} already exists ({$diskName})");
                } else {
                    $client->createBucket(['Bucket' => $bucket]);
                    $this->info("✓ Created bucket {$bucket} ({$visibility})");
                }

                // Для публичных бакетов всегда применяем политику
                if ($visibility === 'public') {
                    $this->setPublicBucketPolicy($client, $bucket);
                    $this->info("  ✓ Public policy applied for {$bucket}");
                }
            } catch (\Exception $e) {
                $this->error("Failed to setup bucket {$diskName}: {$e->getMessage()}");

                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }

    /**
     * Создать S3Client из конфигурации диска
     */
    private function createS3Client(string $diskName): S3Client
    {
        $config = config("filesystems.disks.{$diskName}");

        return new S3Client([
            'version' => 'latest',
            'use_path_style_endpoint' => $config['use_path_style_endpoint'] ?? false,
            'endpoint' => $config['endpoint'] ?? 'http://localhost:9000',
            'credentials' => [
                'key' => $config['key'],
                'secret' => $config['secret'],
            ],
            'region' => $config['region'] ?? 'us-east-1',
        ]);
    }

    /**
     * Установить публичную политику для бакета
     */
    private function setPublicBucketPolicy(S3Client $client, string $bucket): void
    {
        // Только GetObject - можно скачать по прямой ссылке
        // ListBucket НЕ включён - нельзя листать все файлы
        $policy = json_encode([
            'Version' => '2012-10-17',
            'Statement' => [
                [
                    'Effect' => 'Allow',
                    'Principal' => '*',
                    'Action' => ['s3:GetObject'],
                    'Resource' => ["arn:aws:s3:::{$bucket}/*"],
                ],
            ],
        ]);

        $client->putBucketPolicy([
            'Bucket' => $bucket,
            'Policy' => $policy,
        ]);
    }
}
