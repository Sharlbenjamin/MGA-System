<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\Client;
use App\Models\City;
use App\Models\Country;
use App\Models\Contact;
use App\Models\DraftMail;
use App\Models\Lead;
use App\Models\ProviderBranch;
use App\Models\ProviderLead;
use App\Models\Provider;
use App\Models\Team;
use App\Models\User;
use App\Models\Patient;
use App\Models\File;
use App\Models\MedicalReport;
use App\Models\Gop;
use App\Models\Prescription;
use App\Models\Drug;
use App\Models\Employee;
use App\Models\JobTitle;
use App\Models\Task;
use App\Policies\ClientPolicy;
use App\Policies\CityPolicy;
use App\Policies\CountryPolicy;
use App\Policies\ContactPolicy;
use App\Policies\DraftMailPolicy;
use App\Policies\LeadPolicy;
use App\Policies\ProviderBranchPolicy;
use App\Policies\ProviderLeadPolicy;
use App\Policies\ProviderPolicy;
use App\Policies\TeamPolicy;
use App\Policies\UserPolicy;
use App\Policies\PatientPolicy;
use App\Policies\FilePolicy;
use App\Policies\MedicalReportPolicy;
use App\Policies\GopPolicy;
use App\Policies\PrescriptionPolicy;
use App\Policies\DrugPolicy;
use App\Policies\EmployeePolicy;
use App\Policies\JobTitlePolicy;
use App\Policies\TaskPolicy;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Client::class => ClientPolicy::class,
        City::class => CityPolicy::class,
        Country::class => CountryPolicy::class,
        Contact::class => ContactPolicy::class,
        DraftMail::class => DraftMailPolicy::class,
        Lead::class => LeadPolicy::class,
        ProviderBranch::class => ProviderBranchPolicy::class,
        ProviderLead::class => ProviderLeadPolicy::class,
        Provider::class => ProviderPolicy::class,
        Team::class => TeamPolicy::class,
        User::class => UserPolicy::class,
        Patient::class => PatientPolicy::class,
        File::class => FilePolicy::class,
        MedicalReport::class => MedicalReportPolicy::class,
        Gop::class => GopPolicy::class,
        Prescription::class => PrescriptionPolicy::class,
        Drug::class => DrugPolicy::class,
        Employee::class => EmployeePolicy::class,
        JobTitle::class => JobTitlePolicy::class,
        Task::class => TaskPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
    }
}