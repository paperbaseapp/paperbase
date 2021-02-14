<?php


namespace App\Models\Stateless;


use App\Models\Document;
use App\Models\Library;

class LibraryNode implements \JsonSerializable
{
    public const TYPE_FILE = 'file';
    public const TYPE_DIRECTORY = 'directory';

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

    public function jsonSerialize()
    {
        return [
            'path' => $this->path,
            'type' => $this->getType(),
            'extension' => $this->fileInfo->getExtension(),
            'basename' => $this->fileInfo->getBasename(),
            'size' => $this->fileInfo->getSize(),
            'document' => $this->getDocument(),
            'needs_sync' => $this->getType() === self::TYPE_FILE && ($this->getDocument() === null || $this->getDocument()->needs_sync),
        ];
    }
}
