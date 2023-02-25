<?php

namespace App\Commands;

use App\AudioFile;
use Aws\Handler\GuzzleV6\GuzzleHandler;
use Aws\S3\S3Client;
use Aws\Sdk;
use Aws\TranscribeService\TranscribeServiceClient;
use Generator;
use GuzzleHttp\Client;
use GuzzleHttp\Promise\Each;
use Illuminate\Support\Collection;
use LaravelZero\Framework\Commands\Command;

class SendCommand extends Command
{
    private const DEFAULT_CONCURRENCY = 25;

    protected $signature = "
        send
        {--c|concurrency=" . self::DEFAULT_CONCURRENCY . " : Limit the number of MP3s processed at the same time}
        {--l|limit= : Limit the number of MP3s processed in total}
        {--N|no-rename : Do not rename the processed MP3s}
        {--M|keep-mounted : Don't unmount the USB drive at the end}
    ";

    protected $description = 'Transcribe and send dictaphone MP3s to my email';

    /** @var Collection<int, AudioFile> */
    private Collection $files;

    public Client $guzzle;
    public S3Client $s3;
    public TranscribeServiceClient $transcribe;

    public function handle(): void
    {
        $this->mountDriveIfNeeded();
        $this->searchForAudioFiles();

        if ($this->files->isEmpty()) {
            $this->output->writeln('No files found.');
        } else {
            $this->initialiseOutput();
            $this->createApiClients();
            $this->processFiles();
            $this->sendEmails();
        }

        $this->unmountDriveIfNeeded();
        $this->output->writeln('Finished.');
    }

    private function mountDriveIfNeeded(): void
    {
        $drive = config('dictaphone.drive');

        if (!glob("/mnt/$drive/*")) {
            $this->output->writeln("Mounting the $drive: drive...");
            passthru("sudo mkdir -p /mnt/$drive && sudo mount -t drvfs $drive: /mnt/$drive");
            $this->output->newLine();
        }
    }

    private function searchForAudioFiles(): void
    {
        $drive = config('dictaphone.drive');

        $this->files = collect();
        foreach (config('dictaphone.folders') as $folder => $targetEmail) {
            foreach (glob("/mnt/$drive/$folder/*.MP3") as $path) {
                $this->files[] = new AudioFile($this, $path, $targetEmail);
            }
        }

        if ($limit = $this->option('limit')) {
            $this->files->splice($limit);
        }
    }

    private function initialiseOutput(): void
    {
        foreach ($this->files as $file) {
            $file->initialiseOutput();
        }
    }

    private function createApiClients(): void
    {
        $this->guzzle = new Client();

        $sdk = new Sdk([
            // We need to use the same Guzzle client for everything, or they block each other
            'http_handler' => new GuzzleHandler($this->guzzle),
            'region' => config('aws.region'),
            'credentials' => [
                'key' => config('aws.key'),
                'secret' => config('aws.secret'),
            ],
        ]);

        $this->s3 = $sdk->createS3(['version' => '2006-03-01']);
        $this->transcribe = $sdk->createTranscribeService(['version' => '2017-10-26']);
    }

    private function processFiles(): void
    {
        $generator = function (): Generator {
            foreach ($this->files as $file) {
                yield $file->processFile();
            }
        };

        Each::ofLimit($generator(), $this->option('concurrency'))->wait();
    }

    private function sendEmails(): void
    {
        // Send emails in order, not concurrently, so they arrive in the correct order
        foreach ($this->files as $index => $file) {
            // Add an artificial delay so there is at least 1 second between emails' timestamps
            if ($index > 0) {
                sleep(1);
            }

            $file->sendEmail();
        }
    }

    private function unmountDriveIfNeeded(): void
    {
        if ($this->option('keep-mounted')) {
            return;
        }

        $drive = config('dictaphone.drive');

        $this->output->newLine();
        $this->output->writeln("Unmounting the $drive: drive...");

        // This seems to help avoid "umount: /mnt/e: target is busy"
        gc_collect_cycles();

        passthru("sudo umount /mnt/$drive && sudo rmdir /mnt/$drive");
    }
}
