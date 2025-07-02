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

    protected $fillable = ['file_id','type','amount','status','date','gop_google_drive_link'];
    protected $casts = ['id' => 'integer','file_id' => 'integer','amount' => 'float','date' => 'date','status' => 'string',];

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }

    public function providerBranch()
    {
        return $this->file->providerBranch();
    }

    protected static function boot()
    {
        parent::boot();

        static::updating(function ($gop) {
            // Only automatically change status to 'Updated' if:
            // 1. The original status was 'Sent'
            // 2. The status field is not being explicitly changed by the user
            // 3. Other fields are being updated (not just status)
            if ($gop->getOriginal('status') === 'Sent' && 
                !$gop->isDirty('status') && 
                $gop->isDirty()) {
                $gop->status = 'Updated';
            }
        });
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
        $gopContact = $branch->contacts()->where('name', 'like', '%GOP%')->first();

        if (!$gopContact?->email) {
            Notification::make()->title('GOP Notification')->body('This branch doesn\'t have a GOP contact')->danger()->send();
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
}
