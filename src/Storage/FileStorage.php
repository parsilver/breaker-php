<?php

namespace Farzai\Breaker\Storage;

class FileStorage implements StorageInterface
{
    protected string $storageDir;

    /**
     * Create a new file storage instance.
     */
    public function __construct(string $storageDir)
    {
        $this->storageDir = rtrim($storageDir, '/');

        if (! is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0755, true);
        }
    }

    /**
     * Load the circuit state data.
     */
    public function load(string $serviceKey): ?array
    {
        $filePath = $this->getFilePath($serviceKey);

        if (! file_exists($filePath)) {
            return null;
        }

        $content = file_get_contents($filePath);

        if ($content === false) {
            return null;
        }

        $data = json_decode($content, true);

        if (! is_array($data)) {
            return null;
        }

        return $data;
    }

    /**
     * Save the circuit state data.
     */
    public function save(string $serviceKey, array $data): void
    {
        $filePath = $this->getFilePath($serviceKey);

        file_put_contents($filePath, json_encode($data), LOCK_EX);
    }

    /**
     * Get the file path for the given service key.
     */
    protected function getFilePath(string $serviceKey): string
    {
        $safeKey = preg_replace('/[^a-zA-Z0-9_-]/', '_', $serviceKey);

        return $this->storageDir.'/'.$safeKey.'.json';
    }
}
