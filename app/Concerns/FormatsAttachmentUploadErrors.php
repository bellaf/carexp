<?php

namespace App\Concerns;

use Illuminate\Support\Str;

trait FormatsAttachmentUploadErrors
{
    /**
     * @return array<int, string>
     */
    public function attachmentUploadErrorMessages(): array
    {
        return collect($this->getErrorBag()->get('newAttachments'))
            ->merge($this->getErrorBag()->get('newAttachments.*'))
            ->flatten()
            ->filter(fn (mixed $message): bool => is_string($message) && $message !== '')
            ->map(fn (string $message): string => $this->formatAttachmentUploadError($message))
            ->unique()
            ->values()
            ->all();
    }

    protected function formatAttachmentUploadError(string $message): string
    {
        if (! Str::contains(Str::lower($message), 'failed to upload')) {
            return $message;
        }

        return 'One or more attachments failed to upload before validation. This usually means the file exceeds the server upload limit. Try a smaller file or ask the server admin to increase PHP upload_max_filesize and post_max_size.';
    }
}
