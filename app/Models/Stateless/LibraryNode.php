<?php


namespace App\Models\Stateless;


use App\Exceptions\CouldNotAcquireLockException;
use App\Models\Document;
use App\Models\Library;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Symfony\Component\Mime\MimeTypes;

class LibraryNode implements \JsonSerializable
{
    public const TYPE_FILE = 'file';
    public const TYPE_DIRECTORY = 'directory';

    public const FLAG_TRASH = 'trash';
    public const FLAG_TRASHED = 'trashed';
    public const FLAG_INBOX = 'inbox';

    protected \SplFileInfo $fileInfo;

    // null = not queried
    // false = not found
    // Document = found
    protected Document|null|false $documentCache = null;

    /**
     * FilesystemNode constructor.
     * @param Library $library
     * @param string $path
     */
    public function __construct(
        protected Library $library,
        protected string $path,
    )
    {
        $this->fileInfo = new \SplFileInfo($this->getAbsolutePath());
    }

    public function getFileInfo(): \SplFileInfo
    {
        return $this->fileInfo;
    }

    private function getAbsolutePath()
    {
        return $this->library->getAbsolutePath($this->path);
    }

    public function getType()
    {
        return is_dir($this->getAbsolutePath())
            ? self::TYPE_DIRECTORY
            : self::TYPE_FILE;
    }

    public function getDocument(): ?Document
    {
        if ($this->documentCache === null) {
            $this->documentCache = $this->library->documents()->where('path', $this->path)->first() ?? false;
        }

        // Prevent returning false
        return $this->documentCache === false
            ? null
            : $this->documentCache;
    }

    public function getFlags()
    {
        $flags = [];

        $absolutePath = $this->getAbsolutePath();
        if ($absolutePath === $this->library->getAbsolutePath($this->library->trash_path)) {
            $flags[] = self::FLAG_TRASH;
        }
        if ($absolutePath === $this->library->getAbsolutePath($this->library->inbox_path)) {
            $flags[] = self::FLAG_INBOX;
        }
        if (Str::of($absolutePath)->startsWith($this->library->getAbsolutePath($this->library->trash_path))) {
            $flags[] = self::FLAG_TRASHED;
        }

        return $flags;
    }

    public function getMime(): ?string
    {
        return MimeTypes::getDefault()->guessMimeType($this->getAbsolutePath());
    }

    public function jsonSerialize()
    {
        return [
            'path' => $this->path,
            'type' => $this->getType(),
            'parent_path' => $this->library->getParentRelativePath($this->path),
            'extension' => $this->fileInfo->getExtension(),
            'basename' => $this->fileInfo->getBasename(),
            'size' => $this->fileInfo->getSize(),
            'document' => $this->getDocument(),
            'flags' => $this->getFlags(),
            'needs_sync' => $this->getType() === self::TYPE_FILE && ($this->getDocument() === null || $this->getDocument()->needs_sync),
        ];
    }

    /**
     * @throws CouldNotAcquireLockException
     */
    public function moveToTrash()
    {
        $trashPath = $this->library->getAvailableTrashPath($this->getFileInfo()->getBasename());

        if ($this->getType() === self::TYPE_DIRECTORY) {
            $oldDocuments = $this
                ->library
                ->documents()
                ->where('path', 'like', '%' . str_replace('%', '\\%', $this->path))
                ->get();

            /** @var Document $document */
            foreach ($oldDocuments as $document) {
                if (!$document->makeLock(3)->get()) {
                    throw new CouldNotAcquireLockException();
                }

                $document->path = Str::of($document->path)->replaceFirst($this->path, $trashPath);
                $document->trashed_at = Carbon::now();
                $document->save();
            }
        } else {
            $document = $this->getDocument();
            if ($document) {
                if (!$document->makeLock(3)->get()) {
                    throw new CouldNotAcquireLockException();
                }

                $document->path = $trashPath;
                $document->trashed_at = Carbon::now();
                $document->save();
            }
        }

        rename($this->getAbsolutePath(), $this->library->getAbsolutePath($trashPath));
    }

    public function delete()
    {
        if ($document = $this->getDocument()) {
            if (!$document->makeLock(3)->get()) {
                throw new CouldNotAcquireLockException();
            }

            $document->delete();
        }

        unlink($this->fileInfo->getRealPath());
    }
}
