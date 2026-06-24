<?php

class Cache
{
    private string $cacheDir;

    public function __construct()
    {
        $this->cacheDir = BASE_PATH . '/app/cache';
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    public function clear(): void
    {
        $files = glob($this->cacheDir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    public function clearView(string $view): void
    {
        $file = $this->cacheDir . '/' . md5($view) . '.cache';
        if (is_file($file)) {
            unlink($file);
        }
    }
}
