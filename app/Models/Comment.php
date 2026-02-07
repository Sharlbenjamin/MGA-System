<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\LogsActivity;

class Comment extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = ['file_id', 'user_id', 'content'];

    public function getActivityReference(): ?string
    {
        $ref = $this->file?->mga_reference ?? 'File #' . $this->file_id;
        $preview = strlen($this->content ?? '') > 40 ? substr($this->content, 0, 40) . '…' : ($this->content ?? '');
        return "Comment ({$ref}): {$preview}";
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}