<?php

namespace App;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

trait ImageUploadTrait
{
    public function uploadFile(?UploadedFile $file, string $directory = 'assets/images', ?string $oldFilePath = null): ?string
    {
        if (!$file && $oldFilePath === null) return null;
        if (!$file && $oldFilePath) return $oldFilePath;

        // Delete exist file; IF, it is exist
        if ($oldFilePath && Storage::disk('public')->exists($oldFilePath)) {
            $this->unlinkFile($oldFilePath);
        }

        // Store new file
        $filePath = $file->store($directory, 'public');

        return $filePath;
    }


    public function unlinkFile(string $filePath)
    {
        Storage::disk('public')->delete($filePath);
    }
}
