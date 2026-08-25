<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * Shared behaviour for the uploadable templates (PDS, leave form, master
 * ledger). Publishing never overwrites: each upload becomes the next numbered
 * version and the previous active row is stamped `superseded_at`, so a
 * submission can always be read against the version it was filled on.
 */
trait IsVersionedTemplate
{
    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** The version employees currently download. */
    public static function active(): ?static
    {
        return static::where('is_active', true)->latest('version')->first();
    }

    public static function nextVersion(): int
    {
        return (int) static::max('version') + 1;
    }

    /** Recognise a byte-identical re-upload instead of bumping the version. */
    public static function findByChecksum(string $checksum): ?static
    {
        return static::where('checksum', $checksum)->first();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function isSuperseded(): bool
    {
        return $this->superseded_at !== null;
    }

    public function versionLabel(): string
    {
        return 'v' . $this->version;
    }

    public function extension(): string
    {
        return strtolower(pathinfo($this->original_filename, PATHINFO_EXTENSION));
    }

    public function exists(): bool
    {
        return Storage::disk('public')->exists($this->file_path);
    }

    public function absolutePath(): string
    {
        return Storage::disk('public')->path($this->file_path);
    }

    public function url(): ?string
    {
        return $this->exists() ? Storage::disk('public')->url($this->file_path) : null;
    }

    public function sizeLabel(): string
    {
        if (! $this->exists()) {
            return '—';
        }

        $bytes = Storage::disk('public')->size($this->file_path);

        return $bytes > 1048576
            ? round($bytes / 1048576, 1) . ' MB'
            : max(1, round($bytes / 1024)) . ' KB';
    }
}
