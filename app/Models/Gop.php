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

    protected $fillable = ['file_id','type','amount','status','date',];
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
            // Check if the model is dirty (has changes) and if status was previously 'Sent'
            if ($gop->isDirty() && $gop->getOriginal('status') === 'Sent') {
                $gop->status = 'Updated';
            }
        });
    }

    public function sendGopToBranch()
    {
        // cehck if there is a branch in the file
        $branch = $this->file->providerBranch;
        if (!$branch) {
            Notification::make()->title('GOP Notification')->body('This file doesn\'t have a branch')->danger()->send();
            return false;
        }
        $gopContact = $branch->contacts()->where('name', 'GOP')->first();

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
