<?php

namespace App\Http\Controllers\Api;

use App\Filament\Resources\TransactionResource;
use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Country;
use App\Models\Employee;
use App\Models\JobTitle;
use App\Models\Lead;
use App\Models\ServiceType;
use App\Models\User;
use App\Services\TransactionDocumentationStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class CatalogApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $lite = $request->boolean('lite');

        $payload = [
            'file_statuses' => ['New', 'Handling', 'Available', 'Confirmed', 'Assisted', 'Hold', 'Waiting MR', 'Refund', 'Cancelled', 'Void'],
            'contact_patient' => ['Client', 'MGA', 'Ask'],
            'patient_genders' => ['Male', 'Female'],
            'client_statuses' => Client::statusOptions(),
            'client_types' => ['Assistance', 'Insurance', 'Agency'],
            'client_fee_strategies' => [
                Client::FILE_FEE_STRATEGY_TIER => 'Tier (Simple / Middle / Complex by bill total)',
                Client::FILE_FEE_STRATEGY_MULTIPLIER => 'Multiplier (1 Simple fee per €350 of bill cost)',
            ],
            'client_invoice_templates' => [
                Client::INVOICE_TEMPLATE_ITEMIZED => 'Itemized (bill items and file fee separately)',
                Client::INVOICE_TEMPLATE_COMBINED => 'Combined (one line on PDF)',
            ],
            'provider_statuses' => ['Active', 'Hold', 'Potential', 'Searching', 'Black List'],
            'provider_types' => ['Doctor', 'Hospital', 'Clinic', 'Dental', 'Agency'],
            'payment_methods' => ['Online Link', 'Bank Transfer', 'AEAT'],
            'lead_statuses' => Lead::PIPELINE_STEPS,
            'lead_contact_methods' => ['Email', 'Phone', 'Linked In', 'Other'],
            'lead_follow_up' => [
                'overdue' => 'Past due',
                'due_today' => 'Due today',
                'remaining' => 'Days remaining',
                'no_date' => 'No contact date',
            ],
            'provider_lead_types' => ['Doctor', 'Clinic', 'Hospital', 'Dental'],
            'provider_lead_statuses' => [
                'Pending information',
                'Step one',
                'Step one sent',
                'Reminder',
                'Reminder sent',
                'Discount',
                'Discount sent',
                'Step two',
                'Step two sent',
                'Presentation',
                'Presentation sent',
                'Contract',
                'Contract sent',
                'Fake Case',
                'Fake Case sent',
                'Cancel Case',
                'Cancel Case sent',
                'Error',
            ],
            'provider_lead_contact_methods' => ['Email', 'WhatsApp', 'Phone', 'Linked In'],
            'bill_statuses' => ['Unpaid', 'Partial', 'Paid'],
            'invoice_statuses' => ['Draft', 'Posted', 'Sent', 'Unpaid', 'Partial', 'Paid'],
            'invoice_item_types' => [
                'bill' => 'Bill line',
                'file_fee' => 'File fee',
            ],
            'transaction_types' => ['Income', 'Outflow', 'Expense'],
            'transaction_statuses' => ['Completed', 'Draft', 'Pending'],
            'transaction_related_types' => [
                'Income' => TransactionResource::relatedTypes('Income'),
                'Outflow' => TransactionResource::relatedTypes('Outflow'),
                'Expense' => TransactionResource::relatedTypes('Expense'),
            ],
            'documentation_categories' => TransactionDocumentationStatsService::allCategoryOptions(),
            'employee_statuses' => Employee::statusOptions(),
            'departments' => ['Operation', 'Financial', 'Client Network', 'Provider Network'],
            'signature_departments' => ['Operation', 'Financial', 'Provider Network', 'Client Network'],
            'task_departments' => [
                'Operation' => 'Operation',
                'Financial' => 'Financial',
                'Network' => 'Client Network',
                'Network Providers' => 'Providers Network',
            ],
            'employment_types' => [
                'full_time' => 'Full time',
                'part_time' => 'Part time',
            ],
            'english_levels' => [
                'basic' => 'Basic',
                'good' => 'Good',
                'fluent' => 'Fluent',
                'native' => 'Native',
            ],
            'genders' => [
                'female' => 'Female',
                'male' => 'Male',
                'other' => 'Other',
            ],
        ];

        $shared = [
            'countries' => Country::query()->orderBy('name')->get(['id', 'name']),
            'service_types' => ServiceType::query()->orderBy('name')->get(['id', 'name']),
            'roles' => Role::query()->orderBy('name')->pluck('name'),
            'job_titles' => JobTitle::query()->orderBy('name')->get(['id', 'name']),
        ];

        if ($lite) {
            return response()->json($payload + $shared);
        }

        return response()->json($payload + $shared + [
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function contacts(Request $request): JsonResponse
    {
        $query = Contact::query()->orderBy('name');
        if ($request->filled('search')) {
            $term = $request->input('search');
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%");
            });
        }

        return response()->json($query->limit(40)->get(['id', 'name', 'email', 'phone_number']));
    }

    public function bankAccounts(Request $request): JsonResponse
    {
        $query = BankAccount::query()->orderBy('beneficiary_name');
        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }
        if ($request->filled('search')) {
            $term = $request->input('search');
            $query->where('beneficiary_name', 'like', "%{$term}%");
        }

        return response()->json($query->limit(40)->get(['id', 'beneficiary_name', 'type', 'iban']));
    }
}
