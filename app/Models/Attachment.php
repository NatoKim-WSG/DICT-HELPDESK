<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Attachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'filename',
        'original_filename',
        'file_path',
        'mime_type',
        'file_size',
        'attachable_type',
        'attachable_id',
    ];

    public function attachable()
    {
        return $this->morphTo();
    }

    public function getFileSizeHumanAttribute()
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2).' '.$units[$i];
    }

    public function getDownloadUrlAttribute()
    {
        return route('attachments.download', $this->id);
    }

    public function getPreviewUrlAttribute()
    {
        return route('attachments.download', [
            'attachment' => $this->id,
            'preview' => 1,
        ]);
    }

    public function delete()
    {
        $disk = $this->resolvedDisk();
        if (Storage::disk($disk)->exists($this->file_path)) {
            Storage::disk($disk)->delete($this->file_path);
        }

        return parent::delete();
    }

    public function resolvedDisk(): string
    {
        $primaryDisk = (string) config('helpdesk.attachments_disk', 'local');
        if (Storage::disk($primaryDisk)->exists($this->file_path)) {
            return $primaryDisk;
        }

        return 'public';
    }
}
