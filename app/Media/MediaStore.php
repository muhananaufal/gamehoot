<?php

declare(strict_types=1);

namespace App\Media;

use App\Enums\MediaVariant;
use App\Enums\MediaVisibility;
use App\Models\MediaFile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LogicException;

/**
 * G11, E8, E9, E14: stores the two sizes of an image made in the host's browser (1920 px for the
 * projector, 720 px for phones). The browser never sends the original, so the w1920 file is the
 * parent row and the w720 file its variant. Files are never overwritten (D-9): every upload gets
 * a new random name. Validation of the files (type from content, size, width) happens before.
 */
final readonly class MediaStore
{
    public const string DISK = 'media';

    private const array EXTENSIONS = ['image/jpeg' => 'jpg', 'image/png' => 'png'];

    public function store(UploadedFile $large, UploadedFile $small, MediaVisibility $visibility, User $uploader): MediaFile
    {
        $originalName = $large->getClientOriginalName();
        $base = $this->baseName($visibility, $originalName);

        $parent = $this->write($large, $base, MediaVariant::W1920, $visibility, $uploader, $originalName, null);
        $this->write($small, $base.'-w720', MediaVariant::W720, $visibility, $uploader, null, $parent);

        return $parent;
    }

    /**
     * E9: answer images keep the original name; question images do not, because their URL
     * reaches phones before Reveal and the name could give the answer away.
     */
    private function baseName(MediaVisibility $visibility, string $originalName): string
    {
        $stamp = now()->format('Y-m-d').'-'.Str::lower(Str::random(16));

        if ($visibility === MediaVisibility::Public) {
            return "public/{$stamp}";
        }

        $name = Str::limit(Str::slug(pathinfo($originalName, PATHINFO_FILENAME)), 60, '');

        return 'private/'.($name === '' ? 'image' : $name)."-{$stamp}";
    }

    private function write(
        UploadedFile $file,
        string $base,
        MediaVariant $variant,
        MediaVisibility $visibility,
        User $uploader,
        ?string $originalName,
        ?MediaFile $parent,
    ): MediaFile {
        $mime = (string) $file->getMimeType();
        $extension = self::EXTENSIONS[$mime] ?? throw new LogicException("Unexpected image type {$mime}.");
        $path = "{$base}.{$extension}";

        $disk = Storage::disk(self::DISK);
        if ($disk->exists($path)) {
            throw new LogicException("Refusing to overwrite {$path}.");
        }

        $source = (string) $file->getRealPath();
        $size = getimagesize($source) ?: throw new LogicException('Not an image.');
        $disk->put($path, (string) file_get_contents($source));

        $media = new MediaFile;
        $media->forceFill([
            'parent_id' => $parent?->id,
            'disk' => self::DISK,
            'path' => $path,
            'variant' => $variant,
            'visibility' => $visibility,
            'mime' => $mime,
            'size_bytes' => (int) $file->getSize(),
            'width' => $size[0],
            'height' => $size[1],
            'sha256' => (string) hash_file('sha256', $source),
            'original_name' => $originalName,
            'created_by' => $uploader->id,
        ])->save();

        return $media;
    }
}
