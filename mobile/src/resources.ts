import { asOptions, lookup, Option } from './api';
import { Catalog } from './catalog';
import { ListDefaults } from './list-config';
import { money, prettyDate } from './theme';

export type FormValues = Record<string, string | boolean | string[]>;

export type FieldDef = {
  name: string;
  label: string;
  kind: 'text' | 'textarea' | 'number' | 'date' | 'select' | 'search' | 'switch' | 'multi';
  required?: boolean;
  secure?: boolean;
  optionsKey?: string;
  optionsFrom?: (catalog: Catalog, values: FormValues) => Option[];
  search?: (query: string, values: FormValues) => Promise<Option[]>;
  visible?: (values: FormValues) => boolean;
  when?: 'create' | 'edit';
};

export type AttachmentDef = { name: string; label: string };

export type ResourceConfig = {
  key: string;
  singular: string;
  plural: string;
  endpoint: string;
  permissionView: string;
  permissionCreate: string;
  permissionEdit: string;
  permissionDelete: string;
  adminOnly?: boolean;
  statusesKey?: string;
  extraFilters?: { param: string; label: string; optionsKey: string }[];
  title: (row: any) => string;
  subtitle: (row: any) => string;
  amount?: (row: any) => string | undefined;
  fields: FieldDef[];
  defaults?: FormValues;
  lineItems?: boolean;
  attachments?: AttachmentDef[];
  labels?: (row: any) => Record<string, string>;
  fromRecord?: (row: any) => FormValues;
  toPayload?: (values: FormValues) => Record<string, unknown>;
  widgets?: { key: string; label: string; status?: string; extra?: Record<string, string> }[];
  overviewEndpoint?: string;
  /** Applied on first load (and when resetting filters) so lists never fetch the full table. */
  listDefaults?: ListDefaults;
  /** Label for the reset pill when listDefaults is set. */
  defaultFilterLabel?: string;
};

const countrySearch = (query: string) => lookup('/cities', (row) => row.name, query);
const clientSearch = (query: string) => lookup('/clients', (row) => row.company_name || row.name, query);
const providerSearch = (query: string) => lookup('/providers', (row) => row.name, query);
const patientSearch = (query: string) =>
  lookup('/patients', (row) => [row.name, row.client?.company_name].filter(Boolean).join(' · '), query);
const fileSearch = (query: string) =>
  lookup('/files', (row) => row.mga_reference || `Case #${row.id}`, query, '&mobile=1&opened=1');
const contactSearch = (query: string) => lookup('/contacts', (row) => row.name || row.email, query);
const bankSearch = (query: string, extra = '') =>
  lookup('/bank-accounts', (row) => [row.beneficiary_name, row.type].filter(Boolean).join(' · '), query, extra);
const branchSearch = (query: string, values: FormValues) =>
  lookup(
    '/provider-branches',
    (row) => [row.branch_name, row.provider?.name].filter(Boolean).join(' · '),
    query,
    `${values.provider_id ? `&provider_id=${values.provider_id}` : ''}${values.city_id ? `&city_id=${values.city_id}` : ''}${values.service_type_id ? `&service_type_id=${values.service_type_id}` : ''}`,
  );

function namedOptions(catalog: Catalog, key: string): Option[] {
  return asOptions(catalog[key]);
}

export const financeResources: Record<string, ResourceConfig> = {
  bills: {
    key: 'bills',
    singular: 'Bill',
    plural: 'Bills',
    endpoint: '/bills',
    permissionView: 'view Bill',
    permissionCreate: 'create Bill',
    permissionEdit: 'edit Bill',
    permissionDelete: 'delete Bill',
    statusesKey: 'bill_statuses',
    title: (row) => row.name || row.file?.mga_reference || `Bill #${row.id}`,
    subtitle: (row) => [row.status, row.file?.mga_reference, row.provider?.name].filter(Boolean).join(' · '),
    amount: (row) => money(row.total_amount),
    widgets: [
      { key: 'Unpaid', label: 'Unpaid', status: 'Unpaid' },
      { key: 'Partial', label: 'Partial', status: 'Partial' },
      { key: 'Paid', label: 'Paid', status: 'Paid' },
    ],
    listDefaults: { status: 'Unpaid' },
    defaultFilterLabel: 'Unpaid',
    defaults: { status: 'Unpaid' },
    lineItems: true,
    labels: (row) => ({
      file_id: row.file?.mga_reference || '',
      provider_id: row.provider?.name || '',
      branch_id: row.branch?.branch_name || '',
    }),
    fields: [
      { name: 'file_id', label: 'Case', kind: 'search', required: true, search: (q) => fileSearch(q) },
      { name: 'name', label: 'Bill name', kind: 'text' },
      { name: 'provider_id', label: 'Provider', kind: 'search', search: (q) => providerSearch(q) },
      { name: 'branch_id', label: 'Branch', kind: 'search', search: (q, values) => branchSearch(q, values) },
      { name: 'bank_account_id', label: 'Bank account', kind: 'search', search: (q) => bankSearch(q) },
      { name: 'status', label: 'Status', kind: 'select', required: true, optionsKey: 'bill_statuses' },
      {
        name: 'payment_date',
        label: 'Payment date',
        kind: 'date',
        visible: (values) => values.status === 'Paid' || values.status === 'Partial',
      },
      { name: 'bill_google_link', label: 'Google Drive link', kind: 'text' },
      { name: 'discount', label: 'Discount', kind: 'number' },
      { name: 'paid_amount', label: 'Paid amount', kind: 'number' },
    ],
  },
  invoices: {
    key: 'invoices',
    singular: 'Invoice',
    plural: 'Invoices',
    endpoint: '/invoices',
    permissionView: 'view Invoice',
    permissionCreate: 'create Invoice',
    permissionEdit: 'edit Invoice',
    permissionDelete: 'delete Invoice',
    statusesKey: 'invoice_statuses',
    title: (row) => row.name || `Invoice #${row.id}`,
    subtitle: (row) => [row.status, row.file?.mga_reference, row.patient?.name].filter(Boolean).join(' · '),
    amount: (row) => money(row.total_amount),
    widgets: [
      { key: 'Unpaid', label: 'Unpaid', status: 'Unpaid' },
      { key: 'Partial', label: 'Partial', status: 'Partial' },
      { key: 'Paid', label: 'Paid', status: 'Paid' },
      { key: 'Draft', label: 'Draft', status: 'Draft' },
    ],
    listDefaults: { status: 'Unpaid' },
    defaultFilterLabel: 'Unpaid',
    defaults: { status: 'Draft' },
    lineItems: true,
    labels: (row) => ({
      file_id: row.file?.mga_reference || '',
      patient_id: row.patient?.name || '',
    }),
    fields: [
      { name: 'file_id', label: 'Case', kind: 'search', required: true, search: (q) => fileSearch(q) },
      { name: 'patient_id', label: 'Patient', kind: 'search', search: (q) => patientSearch(q) },
      { name: 'name', label: 'Invoice number', kind: 'text' },
      { name: 'bank_account_id', label: 'Bank account', kind: 'search', search: (q) => bankSearch(q, '&type=Internal') },
      { name: 'status', label: 'Status', kind: 'select', required: true, optionsKey: 'invoice_statuses' },
      { name: 'invoice_date', label: 'Invoice date', kind: 'date' },
      { name: 'payment_date', label: 'Payment date', kind: 'date' },
      { name: 'payment_link', label: 'Payment link', kind: 'text' },
      { name: 'discount', label: 'Discount', kind: 'number' },
      { name: 'tax', label: 'Tax', kind: 'number' },
      { name: 'paid_amount', label: 'Paid amount', kind: 'number' },
    ],
  },
  transactions: {
    key: 'transactions',
    singular: 'Transaction',
    plural: 'Transactions',
    endpoint: '/transactions',
    permissionView: 'view Transaction',
    permissionCreate: 'create Transaction',
    permissionEdit: 'edit Transaction',
    permissionDelete: 'delete Transaction',
    statusesKey: 'transaction_statuses',
    extraFilters: [{ param: 'type', label: 'Type', optionsKey: 'transaction_types' }],
    title: (row) => row.name || row.reference || `${row.type || 'Transaction'} #${row.id}`,
    subtitle: (row) => [row.type, row.status, row.related_type, row.related_label, prettyDate(row.date)].filter(Boolean).join(' · '),
    amount: (row) => money(row.amount),
    widgets: [
      { key: 'Income', label: 'Income', extra: { type: 'Income' } },
      { key: 'Outflow', label: 'Outflow', extra: { type: 'Outflow' } },
      { key: 'Pending', label: 'Pending', status: 'Pending' },
    ],
    listDefaults: { status: 'Pending' },
    defaultFilterLabel: 'Pending',
    defaults: { type: 'Outflow', related_type: 'Provider', status: 'Completed' },
    labels: (row) => ({ related_id: row.related_label || '' }),
    fields: [
      { name: 'type', label: 'Type', kind: 'select', required: true, optionsKey: 'transaction_types' },
      {
        name: 'related_type',
        label: 'Related to',
        kind: 'select',
        required: true,
        optionsFrom: (catalog, values) => namedOptions({ related: catalog.transaction_related_types?.[String(values.type)] || {} }, 'related'),
      },
      {
        name: 'related_id',
        label: 'Related record',
        kind: 'search',
        visible: (values) => ['Client', 'Patient', 'Provider', 'Branch'].includes(String(values.related_type)),
        search: (query, values) => {
          if (values.related_type === 'Client') return clientSearch(query);
          if (values.related_type === 'Patient') return patientSearch(query);
          if (values.related_type === 'Provider') return providerSearch(query);
          return branchSearch(query, values);
        },
      },
      { name: 'bank_account_id', label: 'Internal bank account', kind: 'search', search: (q) => bankSearch(q, '&type=Internal') },
      { name: 'amount', label: 'Amount', kind: 'number', required: true },
      { name: 'date', label: 'Date', kind: 'date', required: true },
      { name: 'status', label: 'Payment status', kind: 'select', required: true, optionsKey: 'transaction_statuses' },
      { name: 'reference', label: 'Reference', kind: 'text' },
      { name: 'name', label: 'Name', kind: 'text' },
      { name: 'bank_charges', label: 'Bank charges', kind: 'number' },
      { name: 'charges_covered_by_client', label: 'Charges covered by client', kind: 'switch' },
      { name: 'documentation_category', label: 'Documentation category', kind: 'select', optionsKey: 'documentation_categories' },
      { name: 'notes', label: 'Notes', kind: 'textarea' },
    ],
  },
};

export const crmResources: Record<string, ResourceConfig> = {
  leads: {
    key: 'leads',
    singular: 'Lead',
    plural: 'Client leads',
    endpoint: '/leads',
    permissionView: 'view Lead',
    permissionCreate: 'create Lead',
    permissionEdit: 'edit Lead',
    permissionDelete: 'delete Lead',
    statusesKey: 'lead_statuses',
    title: (row) => row.first_name || row.email || `Lead #${row.id}`,
    subtitle: (row) =>
      [row.client?.company_name, row.client?.status, row.follow_up_label, row.contact_method].filter(Boolean).join(' · '),
    overviewEndpoint: '/leads/overview',
    extraFilters: [
      { param: 'client_status', label: 'Client status', optionsKey: 'client_statuses' },
      { param: 'follow_up', label: 'Follow-up', optionsKey: 'lead_follow_up' },
    ],
    listDefaults: { extra: { needs_action: '1' } },
    defaultFilterLabel: 'Needs action',
    widgets: [
      { key: 'leads', label: 'Leads' },
      { key: 'needs_action', label: 'Needs Action', extra: { needs_action: '1' } },
      { key: 'searching', label: 'Searching', extra: { client_status: 'Searching' } },
      { key: 'interested', label: 'Interested', extra: { client_status: 'Interested', past_due: '1' } },
      { key: 'follow_up', label: 'Follow up', extra: { past_due: '1' } },
    ],
    defaults: { status: 'Introduction', create_new_client: false, new_client_type: 'Assistance', new_client_status: 'Interested' },
    labels: (row) => ({ client_id: row.client?.company_name || '' }),
    toPayload: (values) => {
      if (!values.create_new_client) return {};
      return {
        create_new_client: true,
        new_client_company_name: values.new_client_company_name,
        new_client_type: values.new_client_type,
        new_client_status: values.new_client_status,
        new_client_initials: values.new_client_initials,
      };
    },
    fields: [
      { name: 'create_new_client', label: 'Create new client', kind: 'switch', when: 'create' },
      {
        name: 'client_id',
        label: 'Client',
        kind: 'search',
        required: true,
        search: (q) => clientSearch(q),
        visible: (values) => !values.create_new_client,
      },
      {
        name: 'new_client_company_name',
        label: 'Company name',
        kind: 'text',
        required: true,
        when: 'create',
        visible: (values) => Boolean(values.create_new_client),
      },
      {
        name: 'new_client_type',
        label: 'Client type',
        kind: 'select',
        required: true,
        optionsKey: 'client_types',
        when: 'create',
        visible: (values) => Boolean(values.create_new_client),
      },
      {
        name: 'new_client_status',
        label: 'Client status',
        kind: 'select',
        required: true,
        optionsKey: 'client_statuses',
        when: 'create',
        visible: (values) => Boolean(values.create_new_client),
      },
      {
        name: 'new_client_initials',
        label: 'Initials',
        kind: 'text',
        required: true,
        when: 'create',
        visible: (values) => Boolean(values.create_new_client),
      },
      { name: 'first_name', label: 'First name', kind: 'text', required: true },
      { name: 'email', label: 'Email', kind: 'text', required: true },
      { name: 'phone', label: 'Phone', kind: 'text' },
      { name: 'linked_in', label: 'LinkedIn', kind: 'text' },
      { name: 'status', label: 'Status', kind: 'select', required: true, optionsKey: 'lead_statuses' },
      { name: 'contact_method', label: 'Contact method', kind: 'select', optionsKey: 'lead_contact_methods' },
      { name: 'last_contact_date', label: 'Last contact date', kind: 'date' },
    ],
  },
  clients: {
    key: 'clients',
    singular: 'Client',
    plural: 'Clients',
    endpoint: '/clients',
    permissionView: 'view Client',
    permissionCreate: 'create Client',
    permissionEdit: 'edit Client',
    permissionDelete: 'delete Client',
    statusesKey: 'client_statuses',
    extraFilters: [{ param: 'status', label: 'Status', optionsKey: 'client_statuses' }],
    title: (row) => row.company_name || `Client #${row.id}`,
    subtitle: (row) => [row.status, row.type, row.country?.name, row.email].filter(Boolean).join(' · '),
    widgets: [
      { key: 'Active', label: 'Active', status: 'Active' },
      { key: 'Searching', label: 'Searching', status: 'Searching' },
      { key: 'Interested', label: 'Interested', status: 'Interested' },
      { key: 'On Hold', label: 'On hold', status: 'On Hold' },
    ],
    listDefaults: { status: 'Active' },
    defaultFilterLabel: 'Active',
    defaults: { type: 'Assistance', status: 'Searching', number_requests: '0', invoice_file_fee_strategy: 'tier', invoice_template: 'itemized' },
    labels: (row) => ({
      country_id: row.country?.name || '',
      gop_contact_id: row.gop_contact?.name || '',
      operation_contact_id: row.operation_contact?.name || '',
      financial_contact_id: row.financial_contact?.name || '',
    }),
    fromRecord: (row) => ({
      invoice_cc_emails: Array.isArray(row.invoice_cc_emails) ? row.invoice_cc_emails.join(', ') : row.invoice_cc_emails || '',
    }),
    toPayload: (values) => ({
      invoice_cc_emails: String(values.invoice_cc_emails || '')
        .split(',')
        .map((item) => item.trim())
        .filter(Boolean),
    }),
    fields: [
      { name: 'company_name', label: 'Company name', kind: 'text', required: true },
      { name: 'type', label: 'Type', kind: 'select', required: true, optionsKey: 'client_types' },
      { name: 'status', label: 'Status', kind: 'select', required: true, optionsKey: 'client_statuses' },
      { name: 'initials', label: 'Initials', kind: 'text', required: true },
      { name: 'country_id', label: 'Country', kind: 'select', optionsKey: 'countries' },
      { name: 'niv_number', label: 'NIV number', kind: 'text' },
      { name: 'number_requests', label: 'Number of requests', kind: 'number', required: true },
      { name: 'gop_contact_id', label: 'GOP contact', kind: 'search', search: (q) => contactSearch(q) },
      { name: 'operation_contact_id', label: 'Operation contact', kind: 'search', search: (q) => contactSearch(q) },
      { name: 'financial_contact_id', label: 'Financial contact', kind: 'search', search: (q) => contactSearch(q) },
      { name: 'phone', label: 'Phone', kind: 'text' },
      { name: 'linkedin_url', label: 'LinkedIn', kind: 'text' },
      { name: 'email', label: 'Financial email', kind: 'text' },
      { name: 'operation_email', label: 'Operation email', kind: 'text' },
      { name: 'invoice_cc_emails', label: 'Invoice CC emails', kind: 'text' },
      { name: 'invoice_file_fee_strategy', label: 'File fee strategy', kind: 'select', optionsKey: 'client_fee_strategies' },
      { name: 'invoice_template', label: 'Invoice template', kind: 'select', optionsKey: 'client_invoice_templates' },
      { name: 'address', label: 'Address', kind: 'textarea' },
      { name: 'comment', label: 'Comment', kind: 'textarea' },
    ],
  },
};

export const prmResources: Record<string, ResourceConfig> = {
  'provider-leads': {
    key: 'provider-leads',
    singular: 'Provider lead',
    plural: 'Provider leads',
    endpoint: '/provider-leads',
    permissionView: 'view ProviderLead',
    permissionCreate: 'create ProviderLead',
    permissionEdit: 'edit ProviderLead',
    permissionDelete: 'delete ProviderLead',
    statusesKey: 'provider_lead_statuses',
    title: (row) => row.name || row.email || `Lead #${row.id}`,
    subtitle: (row) => [row.status, row.type, row.provider?.name, row.city?.name].filter(Boolean).join(' · '),
    widgets: [
      { key: 'Pending information', label: 'Pending', status: 'Pending information' },
      { key: 'Step one', label: 'Step one', status: 'Step one' },
      { key: 'Reminder', label: 'Reminder', status: 'Reminder' },
      { key: 'Contract', label: 'Contract', status: 'Contract' },
    ],
    listDefaults: { status: 'Pending information' },
    defaultFilterLabel: 'Pending',
    defaults: { status: 'Pending information', communication_method: 'Email' },
    labels: (row) => ({
      provider_id: row.provider?.name || '',
      city_id: row.city?.name || '',
      assigned_user_id: row.assigned_user?.name || '',
    }),
    fromRecord: (row) => ({
      service_types: typeof row.service_types === 'string' ? row.service_types.split(',').map((item: string) => item.trim()).filter(Boolean) : [],
    }),
    toPayload: (values) => ({
      service_types: Array.isArray(values.service_types) ? values.service_types.join(',') : values.service_types,
    }),
    fields: [
      { name: 'provider_id', label: 'Provider', kind: 'search', search: (q) => providerSearch(q) },
      { name: 'name', label: 'Lead name', kind: 'text', required: true },
      { name: 'type', label: 'Lead type', kind: 'select', required: true, optionsKey: 'provider_lead_types' },
      { name: 'email', label: 'Email', kind: 'text' },
      { name: 'phone', label: 'Phone', kind: 'text' },
      { name: 'city_id', label: 'City', kind: 'search', required: true, search: (q) => countrySearch(q) },
      {
        name: 'service_types',
        label: 'Service types',
        kind: 'multi',
        required: true,
        optionsFrom: (catalog) => (catalog.service_types || []).map((row: any) => ({ label: row.name, value: row.name })),
      },
      { name: 'communication_method', label: 'Contact method', kind: 'select', required: true, optionsKey: 'provider_lead_contact_methods' },
      { name: 'status', label: 'Status', kind: 'select', required: true, optionsKey: 'provider_lead_statuses' },
      {
        name: 'assigned_user_id',
        label: 'Assigned user',
        kind: 'select',
        optionsFrom: (catalog) => (catalog.users || []).map((row: any) => ({ label: row.name, value: String(row.id) })),
      },
      { name: 'last_contact_date', label: 'Last contact date', kind: 'date' },
      { name: 'comment', label: 'Comment', kind: 'textarea' },
    ],
  },
  providers: {
    key: 'providers',
    singular: 'Provider',
    plural: 'Providers',
    endpoint: '/providers',
    permissionView: 'view Provider',
    permissionCreate: 'create Provider',
    permissionEdit: 'edit Provider',
    permissionDelete: 'delete Provider',
    statusesKey: 'provider_statuses',
    title: (row) => row.name || `Provider #${row.id}`,
    subtitle: (row) => [row.status, row.type, row.country?.name, row.email].filter(Boolean).join(' · '),
    widgets: [
      { key: 'Active', label: 'Active', status: 'Active' },
      { key: 'Potential', label: 'Potential', status: 'Potential' },
      { key: 'Searching', label: 'Searching', status: 'Searching' },
      { key: 'Hold', label: 'Hold', status: 'Hold' },
    ],
    listDefaults: { status: 'Active' },
    defaultFilterLabel: 'Active',
    defaults: { status: 'Searching', type: 'Clinic' },
    labels: (row) => ({
      country_id: row.country?.name || '',
      assigned_user_id: row.assigned_user?.name || '',
    }),
    fields: [
      { name: 'name', label: 'Provider name', kind: 'text', required: true },
      { name: 'type', label: 'Provider type', kind: 'select', required: true, optionsKey: 'provider_types' },
      { name: 'status', label: 'Status', kind: 'select', required: true, optionsKey: 'provider_statuses' },
      { name: 'country_id', label: 'Country', kind: 'select', required: true, optionsKey: 'countries' },
      {
        name: 'assigned_user_id',
        label: 'Assigned employee',
        kind: 'select',
        visible: (values) => values.status === 'Potential',
        optionsFrom: (catalog) => (catalog.users || []).map((row: any) => ({ label: row.name, value: String(row.id) })),
      },
      { name: 'payment_due', label: 'Payment due (days)', kind: 'number' },
      { name: 'payment_method', label: 'Payment method', kind: 'select', optionsKey: 'payment_methods' },
      { name: 'needs_payment', label: 'Payment requested', kind: 'switch' },
      { name: 'phone', label: 'Phone', kind: 'text' },
      { name: 'email', label: 'Email', kind: 'text' },
      { name: 'gop_contact_id', label: 'GOP contact', kind: 'search', search: (q) => contactSearch(q) },
      { name: 'operation_contact_id', label: 'Operation contact', kind: 'search', search: (q) => contactSearch(q) },
      { name: 'financial_contact_id', label: 'Financial contact', kind: 'search', search: (q) => contactSearch(q) },
      { name: 'comment', label: 'Comment', kind: 'textarea' },
      { name: 'request_comment', label: 'Request comment', kind: 'textarea' },
    ],
  },
};

export const teamResources: Record<string, ResourceConfig> = {
  users: {
    key: 'users',
    singular: 'User',
    plural: 'Users',
    endpoint: '/users',
    permissionView: 'view User',
    permissionCreate: 'create User',
    permissionEdit: 'edit User',
    permissionDelete: 'delete User',
    title: (row) => row.name || `User #${row.id}`,
    subtitle: (row) => [Array.isArray(row.roles) ? row.roles.join(', ') : '', row.email].filter(Boolean).join(' · '),
    extraFilters: [{ param: 'role', label: 'Role', optionsKey: 'roles' }],
    listDefaults: { extra: { role: 'admin' } },
    defaultFilterLabel: 'Admins',
    widgets: [{ key: 'all', label: 'Users' }],
    fromRecord: (row) => ({
      roles: Array.isArray(row.roles) ? row.roles.map(String) : [],
      signature_name: row.signature?.name || '',
      signature_job_title: row.signature?.job_title || '',
      signature_department: row.signature?.department || '',
      signature_work_phone: row.signature?.work_phone || '',
      password: '',
      smtp_password: '',
    }),
    toPayload: (values) => {
      const payload: Record<string, unknown> = {
        name: values.name,
        email: values.email,
        roles: values.roles,
        smtp_username: values.smtp_username || null,
        signature: {
          name: values.signature_name || null,
          job_title: values.signature_job_title || null,
          department: values.signature_department || null,
          work_phone: values.signature_work_phone || null,
        },
      };
      if (values.password) payload.password = values.password;
      if (values.smtp_password) payload.smtp_password = values.smtp_password;
      return payload;
    },
    fields: [
      { name: 'name', label: 'Name', kind: 'text', required: true },
      { name: 'email', label: 'Email', kind: 'text', required: true },
      { name: 'password', label: 'Password', kind: 'text', secure: true },
      { name: 'roles', label: 'Roles', kind: 'multi', required: true, optionsKey: 'roles' },
      { name: 'smtp_username', label: 'SMTP username', kind: 'text' },
      { name: 'smtp_password', label: 'SMTP password', kind: 'text', secure: true },
      { name: 'signature_name', label: 'Signature name', kind: 'text' },
      { name: 'signature_job_title', label: 'Signature job title', kind: 'text' },
      { name: 'signature_department', label: 'Signature department', kind: 'select', optionsKey: 'signature_departments' },
      { name: 'signature_work_phone', label: 'Signature phone', kind: 'text' },
    ],
  },
  employees: {
    key: 'employees',
    singular: 'CV',
    plural: 'CVs',
    endpoint: '/employees',
    permissionView: 'view Employee',
    permissionCreate: 'create Employee',
    permissionEdit: 'edit Employee',
    permissionDelete: 'delete Employee',
    adminOnly: true,
    statusesKey: 'employee_statuses',
    extraFilters: [{ param: 'department', label: 'Department', optionsKey: 'departments' }],
    title: (row) => row.name || `Employee #${row.id}`,
    subtitle: (row) => [row.status, row.department, row.job_title?.name].filter(Boolean).join(' · '),
    widgets: [
      { key: 'interviewing', label: 'Interviewing', status: 'interviewing' },
      { key: 'active', label: 'Active', status: 'active' },
      { key: 'former', label: 'Former', status: 'former' },
    ],
    listDefaults: { status: 'interviewing' },
    defaultFilterLabel: 'Interviewing',
    defaults: { status: 'interviewing', basic_salary: '0' },
    labels: (row) => ({
      user_id: row.user?.name || '',
      job_title_id: row.job_title?.name || '',
      manager_id: row.manager?.name || '',
      bank_account_id: row.bank_account?.beneficiary_name || '',
    }),
    attachments: [
      { name: 'cv', label: 'CV file' },
      { name: 'photo_id', label: 'Photo of ID' },
      { name: 'signed_contract_file', label: 'Signed contract' },
    ],
    fields: [
      { name: 'name', label: 'Name', kind: 'text', required: true },
      {
        name: 'user_id',
        label: 'Login user',
        kind: 'select',
        optionsFrom: (catalog) => (catalog.users || []).map((row: any) => ({ label: row.name, value: String(row.id) })),
      },
      {
        name: 'job_title_id',
        label: 'Job title',
        kind: 'select',
        required: true,
        optionsFrom: (catalog) => (catalog.job_titles || []).map((row: any) => ({ label: row.name, value: String(row.id) })),
      },
      { name: 'manager_id', label: 'Manager', kind: 'search', search: (q) => lookup('/employees', (row) => row.name, q) },
      { name: 'department', label: 'Department', kind: 'select', required: true, optionsKey: 'departments' },
      { name: 'status', label: 'Status', kind: 'select', required: true, optionsKey: 'employee_statuses' },
      { name: 'start_date', label: 'Hiring date', kind: 'date' },
      { name: 'end_date', label: 'Leaving date', kind: 'date', visible: (values) => values.status === 'former' },
      { name: 'interview_date', label: 'Interview date', kind: 'date' },
      { name: 'linkedin_url', label: 'LinkedIn', kind: 'text' },
      { name: 'employment_type', label: 'Employment type', kind: 'select', optionsKey: 'employment_types' },
      { name: 'notice_period', label: 'Notice period', kind: 'text' },
      { name: 'english_level', label: 'English level', kind: 'select', optionsKey: 'english_levels' },
      { name: 'flexible_shifts', label: 'Flexible shifts', kind: 'switch' },
      { name: 'expected_salary', label: 'Expected salary', kind: 'number' },
      { name: 'offered_salary', label: 'Offered salary', kind: 'number' },
      { name: 'reason_for_leaving', label: 'Why leave last job?', kind: 'textarea' },
      { name: 'has_laptop', label: 'Has laptop', kind: 'switch' },
      { name: 'date_of_birth', label: 'Date of birth', kind: 'date' },
      { name: 'gender', label: 'Gender', kind: 'select', optionsKey: 'genders' },
      { name: 'national_id', label: 'National ID', kind: 'text' },
      { name: 'phone', label: 'Phone', kind: 'text' },
      { name: 'social_insurance_number', label: 'Social insurance number', kind: 'text' },
      { name: 'basic_salary', label: 'Basic salary', kind: 'number', required: true },
      { name: 'full_salary', label: 'Full salary', kind: 'number' },
      { name: 'social_insurance_salary', label: 'Social insurance salary', kind: 'number' },
      { name: 'bank_account_id', label: 'Bank account', kind: 'search', search: (q) => bankSearch(q) },
      { name: 'signed_contract', label: 'Signed contract', kind: 'switch' },
    ],
  },
};
