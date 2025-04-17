<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Staudenmeir\EloquentHasManyDeep\HasRelationships;


class Provider extends Model
{
    use HasFactory;
    use HasRelationships;
    protected $fillable = ['country_id','status','type','name','payment_due','payment_method','comment','gop_contact_id','operation_contact_id','financial_contact_id',];

    protected $casts = [
        'id' => 'integer',
    ];

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    public function branches()
    {
        return $this->hasMany(ProviderBranch::class);
    }
    public function leads()
    {
        return $this->hasMany(ProviderLead::class, 'provider_id');
    }
    public function latestLead()
    {
        return $this->hasOne(ProviderLead::class)->latestOfMany('last_contact_date');
    }

    public function tasks()
    {
        return $this->morphMany(Task::class, 'taskable');
    }

    public function gopContact()
    {
        return $this->belongsTo(Contact::class, 'gop_contact_id');
    }

    public function operationContact()
    {
        return $this->belongsTo(Contact::class, 'operation_contact_id');
    }

    public function financialContact()
    {
        return $this->belongsTo(Contact::class, 'financial_contact_id');
    }

    public function files(): HasManyThrough
    {
        return $this->hasManyThrough(
            File::class,  // Final model
            ProviderBranch::class,  // Intermediate model
            'provider_id',     // Foreign key on ProviderBranch table
            'provider_branch_id',    // Foreign key on files table
            'id',            // Local key on provider table
            'id'             // Local key on files table
        );
    }
    public function bankAccounts(): HasMany
    {
        return $this->hasMany(BankAccount::class);
    }

    public function bills()
    {
        return $this->hasManyDeep(
            Bill::class,[ProviderBranch::class, File::class]
        );
    }

    public function transactions()
    {
        return Transaction::where('related_type', 'Provider')->where('related_id', $this->id);
    }

    // calculations calculations calculations calculations calculations calculations

    public function getFilesCountAttribute()
    {
        return $this->files()->count();
    }
    public function getFilesCancelledCountAttribute()
    {
        return $this->files->where('status', 'Cancelled')->count();
    }
    public function getFilesAssistedCountAttribute()
    {
        return $this->files->where('status', 'Assisted')->count();
    }
    public function getBillsTotalNumberAttribute()
    {
        return $this->bills()->count();
    }
    public function getBillsTotalAttribute()
    {
        return $this->bills()->sum('total_amount');
    }
    public function getBillsTotalNumberPaidAttribute()
    {
        return $this->bills->where('status', 'Paid')->count();
    }
    public function getBillsTotalPaidAttribute()
    {
        return $this->bills->where('status', 'Paid')->sum('paid_amount');
    }
    public function getBillsTotalNumberOutstandingAttribute()
    {
        return $this->bills->where('status', 'Unpaid')->count();
    }
    public function getBillsTotalOutstandingAttribute()
    {
        return $this->bills->where('status', 'Unpaid')->sum('total_amount');
    }
    public function getTransactionsLastDateAttribute()
    {
        return $this->transactions()->latest()->first()?->date;
    }
    public function getTransactionLastAmountAttribute()
    {
        return $this->transactions()->latest()->first()?->amount;
    }
}
