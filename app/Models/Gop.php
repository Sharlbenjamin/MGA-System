<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Mail\GopMailable;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Mail;

class Gop extends Model
{
    use HasFactory;

    protected $fillable = ['file_id','type','amount','status','date','gop_google_drive_link','document_path'];
    protected $casts = ['id' => 'integer','file_id' => 'integer','amount' => 'float','date' => 'date','status' => 'string',];

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }

    public function providerBranch()
    {
        return $this->file->providerBranch();
    }

    public function sendGopToBranch()
    {

        if($this->type == 'In'){
            return;
        }
        // cehck if there is a branch in the file
        $branch = $this->file->providerBranch;
        if (!$branch) {
            Notification::make()->title('GOP Notification')->body('This file doesn\'t have a branch')->danger()->send();
            return false;
        }
        $gopContact = $branch->contacts()->where('title', 'like', '%GOP%')->first();

        if (!$gopContact?->email) {
            Notification::make()->title('GOP Notification')->body('This branch doesn\'t have a GOP Contact')->danger()->send();
            return false;
        }

        try {
            Mail::to($gopContact->email)->send(new GopMailable($this));
            $this->status = 'Sent';
            $this->save();
            Notification::make()->title('GOP Notification')->body('GOP sent to branch')->success()->send();
            return true;

        } catch (\Exception $e) {
            Notification::make()->title('GOP Notification')->body('Failed to send GOP: ' . $e->getMessage())->danger()->send();
            return false;
        }
    }

    /**
     * Check if the GOP has a local document
     */
    public function hasLocalDocument(): bool
    {
        return !empty($this->document_path);
    }

    /**
     * Generate a signed URL for the GOP document
     * 
     * @param int $expirationMinutes Expiration time in minutes (default: 60)
     * @return string|null
     */
    public function getDocumentSignedUrl(int $expirationMinutes = 60): ?string
    {
        if (!$this->hasLocalDocument()) {
            return null;
        }

        return route('docs.serve', [
            'type' => 'gop',
            'id' => $this->id
        ], true, $expirationMinutes);
    }

    /**
     * Generate a signed URL for document metadata
     * 
     * @param int $expirationMinutes Expiration time in minutes (default: 60)
     * @return string|null
     */
    public function getDocumentMetadataSignedUrl(int $expirationMinutes = 60): ?string
    {
        if (!$this->hasLocalDocument()) {
            return null;
        }

        return route('docs.metadata', [
            'type' => 'gop',
            'id' => $this->id
        ], true, $expirationMinutes);
    }
}
