<?php

namespace App;

use App\Commands\SendCommand;
use Aws\Result;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\Utils;
use GuzzleHttp\Psr7\Response;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Rych\ByteSize\ByteSize;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use function Termwind\render;
use function Termwind\renderUsing;

class AudioFile
{
    private const TRANSCRIPTION_JOB_PREFIX = 'dictaphone-to-email-';

    private readonly ConsoleSectionOutput $output;
    public readonly string $filename;
    private readonly string $uniqueName;
    private readonly string $transcript;

    public function __construct(
        private readonly SendCommand $command,
        public readonly string $path,
        public readonly array|string $targetEmail,
    )
    {
        $this->filename = basename($path);
        $this->uniqueName = Str::random(40);
    }

    public function initialiseOutput(): void
    {
        $this->output = $this->command->getOutput()->getOutput()->section();

        $this->setStatus(1, 'Waiting to transcribe...', 'bg-red-300 text-black');
    }

    private function setStatus(int $stage, string $message, string $class): void
    {
        $filename = e($this->filename);

        $this->output->clear();
        renderUsing($this->output);

        render(<<<HTML
            <div class="flex mx-1 max-w-70">
                <span class="text-brightwhite">
                    $filename
                </span>
                <span class="flex-1 px-1 text-gray content-repeat-[.]"></span>
                <span class="mr-1 text-gray">
                    $stage/9
                </span>
                <span class="px-1 $class">
                    $message
                </span>
            </div>
        HTML
        );
    }

    public function processFile(): PromiseInterface
    {
        return $this->uploadToS3();
    }

    private function uploadToS3(): PromiseInterface
    {
        $size = ByteSize::formatBinary(filesize($this->path), 1);
        $this->setStatus(2, "Uploading ($size)...", 'bg-amber-300 text-black');

        $promise = $this->command->s3->putObjectAsync([
            'Bucket' => config('aws.bucket'),
            'Key' => $this->uniqueName,
            'SourceFile' => $this->path,
        ]);

        return $promise->then($this->startTranscription(...));
    }

    private function startTranscription(): PromiseInterface
    {
        $this->setStatus(3, 'Starting transcription...', 'bg-amber-300 text-black');

        $bucket = config('aws.bucket');

        $promise = $this->command->transcribe->startTranscriptionJobAsync([
            'TranscriptionJobName' => self::TRANSCRIPTION_JOB_PREFIX . $this->uniqueName,
            'LanguageCode' => 'en-GB',
            'Media' => [
                'MediaFileUri' => "s3://$bucket/{$this->uniqueName}",
            ],
        ]);

        return $promise->then($this->waitForTranscriptionToFinish(...));
    }

    private function waitForTranscriptionToFinish(Result $result): PromiseInterface
    {
        $this->setStatus(4, 'Transcribing...', 'bg-amber-300 text-black');

        // Uncomment this to check the requests are being executed in parallel
        // $this->setStatus(4, Str::random(15), 'bg-amber-300 text-black');

        $job = $result->get('TranscriptionJob');

        if ($job['TranscriptionJobStatus'] !== 'IN_PROGRESS') {
            return $this->checkTranscriptionResult($job);
        }

        $promise = $this->command->transcribe->getTranscriptionJobAsync([
            'TranscriptionJobName' => self::TRANSCRIPTION_JOB_PREFIX . $this->uniqueName,
            '@http' => [
                'delay' => 1000, // Wait 1 second between requests, in a non-blocking way
            ],
        ]);

        return $promise->then($this->waitForTranscriptionToFinish(...));
    }

    private function checkTranscriptionResult(array $job): PromiseInterface
    {
        if ($job['TranscriptionJobStatus'] !== 'COMPLETED') {
            $this->setStatus(4, 'FAILED', 'bg-red text-white');

            // Uncomment this to see details - but for now I don't think it's worth handling this edge case
            // var_dump($job);exit;

            return new FulfilledPromise(null);
        }

        return $this->downloadTranscriptionResult($job);
    }

    private function downloadTranscriptionResult(array $job): PromiseInterface
    {
        $this->setStatus(5, 'Downloading transcript...', 'bg-amber-300 text-black');

        $promise = $this->command->guzzle->getAsync($job['Transcript']['TranscriptFileUri']);

        return $promise->then($this->transcriptDownloaded(...));
    }

    private function transcriptDownloaded(Response $response): PromiseInterface
    {
        $result = json_decode($response->getBody()->getContents(), false, 512, JSON_THROW_ON_ERROR);
        $this->transcript = $result->results->transcripts[0]->transcript;

        return $this->cleanup();
    }

    public function cleanup(): PromiseInterface
    {
        $this->setStatus(6, 'Cleaning up...', 'bg-amber-300 text-black');

        $promise1 = $this->command->s3->deleteObjectAsync([
            'Bucket' => config('aws.bucket'),
            'Key' => $this->uniqueName,
        ]);

        $promise2 = $this->command->transcribe->deleteTranscriptionJobAsync([
            'TranscriptionJobName' => self::TRANSCRIPTION_JOB_PREFIX . $this->uniqueName,
        ]);

        return Utils::all([$promise1, $promise2])
            ->then($this->readyToSend(...));
    }

    private function readyToSend(): PromiseInterface
    {
        $this->setStatus(7, 'Waiting to send...', 'bg-amber-300 text-black');
    }

    public function sendEmail(): void
    {
        $this->setStatus(8, 'Sending email...', 'bg-amber-300 text-black');

        Mail::raw($this->transcript, fn(Message $message) => $message
            ->to($this->targetEmail)
            ->subject($this->filename)
            ->attach($this->path)
        );

        $this->renameFile();
    }

    private function renameFile(): void
    {
        if (!$this->command->option('no-rename')) {
            File::move($this->path, $this->path . '.BAK');
        }

        $this->finished();
    }

    private function finished(): void
    {
        $this->setStatus(9, 'Finished', 'bg-green-300 text-black');
    }
}
