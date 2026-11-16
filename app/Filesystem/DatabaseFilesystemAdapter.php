<?php

namespace App\Filesystem;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use League\Flysystem\Config;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\Visibility;
use stdClass;
use Symfony\Component\Mime\MimeTypes;

/**
 * Stores files as rows in the `stored_files` table.
 *
 * Serverless hosts such as Vercel give every request its own temporary disk, so a file
 * uploaded in one request is missing in the next. Keeping files in the shared database
 * makes uploads (including Livewire's temporary ones) visible to every request.
 * Directories are implicit: a directory exists when a file path starts with it.
 */
class DatabaseFilesystemAdapter implements FilesystemAdapter
{
    public function __construct(
        private ConnectionInterface $connection,
        private string $disk,
        private string $table = 'stored_files',
    ) {}

    public function fileExists(string $path): bool
    {
        return $this->query()->where('path', $this->normalize($path))->exists();
    }

    public function directoryExists(string $path): bool
    {
        $prefix = $this->directoryPrefix($path);

        return $prefix === '' || $this->query()->where('path', 'like', $this->escapeLike($prefix).'%')->exists();
    }

    public function write(string $path, string $contents, Config $config): void
    {
        $path = $this->normalize($path);
        $now = Carbon::now();

        $this->query()->upsert([[
            'disk' => $this->disk,
            'path' => $path,
            'contents' => $contents,
            'mime_type' => $this->detectMimeType($path, $contents),
            'size' => strlen($contents),
            'visibility' => (string) $config->get(Config::OPTION_VISIBILITY, Visibility::PRIVATE),
            'created_at' => $now,
            'updated_at' => $now,
        ]], ['disk', 'path'], ['contents', 'mime_type', 'size', 'visibility', 'updated_at']);
    }

    /**
     * @param  resource  $contents
     */
    public function writeStream(string $path, $contents, Config $config): void
    {
        $this->write($path, (string) stream_get_contents($contents), $config);
    }

    public function read(string $path): string
    {
        return $this->contentsOf($this->findOrFail($path, UnableToReadFile::fromLocation(...)));
    }

    /**
     * @return resource
     */
    public function readStream(string $path)
    {
        $stream = fopen('php://temp', 'w+b');

        if ($stream === false) {
            throw UnableToReadFile::fromLocation($path, 'Could not open a temporary stream.');
        }

        fwrite($stream, $this->read($path));
        rewind($stream);

        return $stream;
    }

    public function delete(string $path): void
    {
        $this->query()->where('path', $this->normalize($path))->delete();
    }

    public function deleteDirectory(string $path): void
    {
        $prefix = $this->directoryPrefix($path);

        $query = $this->query();

        if ($prefix !== '') {
            $query->where('path', 'like', $this->escapeLike($prefix).'%');
        }

        $query->delete();
    }

    public function createDirectory(string $path, Config $config): void
    {
        // Directories are implicit, so there is nothing to create.
    }

    public function setVisibility(string $path, string $visibility): void
    {
        $this->query()->where('path', $this->normalize($path))->update(['visibility' => $visibility]);
    }

    public function visibility(string $path): FileAttributes
    {
        $row = $this->findOrFail($path, UnableToRetrieveMetadata::visibility(...));

        return new FileAttributes($this->normalize($path), visibility: (string) $row->visibility);
    }

    public function mimeType(string $path): FileAttributes
    {
        $row = $this->findOrFail($path, UnableToRetrieveMetadata::mimeType(...));

        return new FileAttributes($this->normalize($path), mimeType: $row->mime_type ?: 'application/octet-stream');
    }

    public function lastModified(string $path): FileAttributes
    {
        $row = $this->findOrFail($path, UnableToRetrieveMetadata::lastModified(...));

        return new FileAttributes($this->normalize($path), lastModified: Carbon::parse((string) $row->updated_at)->getTimestamp());
    }

    public function fileSize(string $path): FileAttributes
    {
        $row = $this->findOrFail($path, UnableToRetrieveMetadata::fileSize(...));

        return new FileAttributes($this->normalize($path), fileSize: (int) $row->size);
    }

    /**
     * @return iterable<FileAttributes|DirectoryAttributes>
     */
    public function listContents(string $path, bool $deep): iterable
    {
        $prefix = $this->directoryPrefix($path);
        $query = $this->query()->select(['path', 'size', 'mime_type', 'visibility', 'updated_at'])->orderBy('path');

        if ($prefix !== '') {
            $query->where('path', 'like', $this->escapeLike($prefix).'%');
        }

        $directories = [];

        foreach ($query->cursor() as $row) {
            /** @var stdClass $row */
            $relative = substr((string) $row->path, strlen($prefix));
            $segments = explode('/', $relative);

            // Report every directory between the listed path and the file once.
            $depth = $deep ? count($segments) - 1 : min(1, count($segments) - 1);

            for ($i = 1; $i <= $depth; $i++) {
                $directory = $prefix.implode('/', array_slice($segments, 0, $i));

                if (! isset($directories[$directory])) {
                    $directories[$directory] = true;

                    yield new DirectoryAttributes($directory);
                }
            }

            if ($deep || count($segments) === 1) {
                yield new FileAttributes(
                    (string) $row->path,
                    (int) $row->size,
                    (string) $row->visibility,
                    Carbon::parse((string) $row->updated_at)->getTimestamp(),
                    $row->mime_type ?: null,
                );
            }
        }
    }

    public function move(string $source, string $destination, Config $config): void
    {
        $source = $this->normalize($source);
        $destination = $this->normalize($destination);

        if ($source === $destination) {
            return;
        }

        if (! $this->fileExists($source)) {
            throw UnableToMoveFile::fromLocationTo($source, $destination);
        }

        $this->delete($destination);
        $this->query()->where('path', $source)->update(['path' => $destination, 'updated_at' => Carbon::now()]);
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        $row = $this->find($source);

        if ($row === null) {
            throw UnableToCopyFile::fromLocationTo($source, $destination);
        }

        $this->write($destination, $this->contentsOf($row), new Config([Config::OPTION_VISIBILITY => $row->visibility]));
    }

    private function query(): Builder
    {
        return $this->connection->table($this->table)->where('disk', $this->disk);
    }

    private function find(string $path): ?stdClass
    {
        /** @var stdClass|null $row */
        $row = $this->query()->where('path', $this->normalize($path))->first();

        return $row;
    }

    /**
     * @param  callable(string, string): \Throwable  $exception
     */
    private function findOrFail(string $path, callable $exception): stdClass
    {
        return $this->find($path) ?? throw $exception($path, 'File not found.');
    }

    private function contentsOf(stdClass $row): string
    {
        // PostgreSQL returns binary columns as streams; MySQL and SQLite return strings.
        return is_resource($row->contents) ? (string) stream_get_contents($row->contents) : (string) $row->contents;
    }

    private function normalize(string $path): string
    {
        return trim(str_replace('\\', '/', $path), '/');
    }

    private function directoryPrefix(string $path): string
    {
        $path = $this->normalize($path);

        return $path === '' ? '' : $path.'/';
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    private function detectMimeType(string $path, string $contents): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $detected = $finfo->buffer($contents);

        if (is_string($detected) && $detected !== '' && $detected !== 'application/octet-stream' && $detected !== 'text/plain') {
            return $detected;
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION);

        return MimeTypes::getDefault()->getMimeTypes($extension)[0] ?? ($detected ?: 'application/octet-stream');
    }
}
