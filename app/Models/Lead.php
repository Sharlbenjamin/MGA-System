<?php

namespace App\Models;

use App\Mail\TailoredMailable;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Carbon;

class Lead extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'client_id',
        'email',
        'first_name',
        'status',
        'last_contact_date',
        'linked_in',
        'phone',
        'contact_method',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
        'client_id' => 'integer',
        'last_contact_date' => 'date',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public static function sendTailoredMail(array $cc, string $subject, string $body)
    {
        Mail::cc($cc)->send(new TailoredMailable($subject, $body));
        self::whereIn('email', $cc)->update(['last_contact_date' => Carbon::now()]);
        Notification::make()->title('success')->body('Emails sent successfully!');
    }

    public static function boot()
    {
        parent::boot();
        static::updated(function ($lead) {
            // if lead status is Error or Missing Information
            if($lead->client->leads->where('status', 'Error')->count() > 0) {
                $lead->client->update([
                    'status' => 'Searching'
                ]);
            }
        });
    }
}
