import { api } from './api';

export type WidgetSpec = {
  key: string;
  label: string;
  description?: string;
  status?: string;
  extra?: Record<string, string>;
};

export type WidgetCount = {
  key: string;
  label: string;
  count: number;
  description?: string;
};

export const leadWidgets: WidgetSpec[] = [
  { key: 'leads', label: 'Leads' },
  { key: 'needs_action', label: 'Needs Action', extra: { needs_action: '1' } },
  { key: 'searching', label: 'Searching', extra: { client_status: 'Searching' } },
  { key: 'interested', label: 'Interested', extra: { client_status: 'Interested', past_due: '1' } },
  { key: 'follow_up', label: 'Follow up', extra: { past_due: '1' } },
];

export const fileWidgets: WidgetSpec[] = [
  { key: 'New', label: 'New Cases', extra: { status: 'New', without_invoices: '1' } },
  { key: 'Hold', label: 'Hold Cases', extra: { status: 'Hold', without_invoices: '1' } },
  { key: 'Handling', label: 'Handling', extra: { status: 'Handling', without_invoices: '1' } },
  { key: 'Available', label: 'Available Cases', extra: { status: 'Available', without_invoices: '1' } },
];

export const caseWidgets = fileWidgets;

export async function loadOverviewWidgets(endpoint: string): Promise<WidgetCount[]> {
  const payload = await api<{ widgets?: Array<Record<string, unknown>> }>(endpoint);
  return (payload?.widgets || []).map((item) => ({
    key: String(item.key ?? ''),
    label: String(item.label ?? item.key ?? ''),
    count: Number(item.count ?? 0),
    description: item.description ? String(item.description) : undefined,
  }));
}

export async function loadWidgetCounts(endpoint: string, specs: WidgetSpec[]): Promise<Record<string, number>> {
  const pairs = await Promise.all(
    specs.map(async (spec) => {
      const params = new URLSearchParams({ page: '1', per_page: '1', mobile: '1' });
      if (spec.status) params.set('status', spec.status);
      Object.entries(spec.extra || {}).forEach(([key, value]) => params.set(key, value));
      try {
        const payload = await api<any>(`${endpoint}?${params.toString()}`);
        const total = Number(payload?.total ?? payload?.meta?.total);
        if (!Number.isFinite(total)) return null;
        return [spec.key, total] as const;
      } catch {
        return null;
      }
    }),
  );
  return Object.fromEntries(pairs.filter((pair): pair is readonly [string, number] => pair !== null));
}

export function widgetIsActive(spec: WidgetSpec, status: string, extra: Record<string, string>): boolean {
  const specExtra = spec.extra || {};
  const specStatus = spec.status || specExtra.status || '';
  const extraWithoutStatus = { ...extra };
  delete extraWithoutStatus.status;
  const specExtraWithoutStatus = { ...specExtra };
  delete specExtraWithoutStatus.status;

  if (status !== specStatus) return false;
  const extraKeys = Object.keys(extraWithoutStatus).filter((key) => extraWithoutStatus[key]);
  const specKeys = Object.keys(specExtraWithoutStatus).filter((key) => specExtraWithoutStatus[key]);
  if (extraKeys.length !== specKeys.length) return false;
  return specKeys.every((key) => extraWithoutStatus[key] === specExtraWithoutStatus[key]);
}
