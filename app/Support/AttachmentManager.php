<?php

namespace App\Support;

use App\Models\Attachment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class AttachmentManager
{
    /**
     * @param  iterable<int, UploadedFile|null>  $files
     */
    public function storeMany(Model $attachable, iterable $files): void
    {
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $originalName = $file->getClientOriginalName();
            $mimeType = $file->getMimeType();
            $size = $file->getSize();

            $path = $file->store(
                'attachments/'.$attachable->getTable().'/'.$attachable->getKey(),
                'local',
            );

            $attachable->attachments()->create([
                'user_id' => (int) $attachable->getAttribute('user_id'),
                'disk' => 'local',
                'path' => $path,
                'original_name' => $originalName,
                'mime_type' => $mimeType,
                'size' => $size,
            ]);
        }
    }

    public function delete(Attachment $attachment): void
    {
        Storage::disk($attachment->disk)->delete($attachment->path);
        $attachment->delete();
    }
}
