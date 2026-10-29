/** MGA web primary (Filament AdminPanelProvider). */
export const colors = {
  primary: '#191970',
  primarySoft: '#E8E8F5',
  ink: '#111827',
  muted: '#6B7280',
  paper: '#F3F4F6',
  card: '#FFFFFF',
  line: '#E5E7EB',
  hero: '#191970',
  heroMuted: '#2D2D8A',
  accent: '#4F46E5',
  danger: '#B91C1C',
  ok: '#15803D',
  warn: '#B45309',
  white: '#FFFFFF',
  /** @deprecated use primary */
  teal: '#191970',
  /** @deprecated use primarySoft */
  tealSoft: '#E8E8F5',
  /** @deprecated use accent */
  copper: '#4F46E5',
};

export function statusColor(status?: string | null): string {
  const value = (status || '').toLowerCase();
  if (['assisted', 'active', 'paid', 'completed', 'confirmed', 'available'].includes(value)) return colors.ok;
  if (['hold', 'pending', 'partial', 'draft', 'potential', 'searching', 'interviewing', 'handling', 'sent', 'posted', 'unpaid'].includes(value)) return colors.warn;
  if (['cancelled', 'void', 'refund', 'error', 'black list', 'rejected', 'former', 'closed'].includes(value)) return colors.danger;
  return colors.primary;
}

export function money(value: unknown): string {
  if (value === null || value === undefined || value === '') return '—';
  const amount = Number(value);
  if (Number.isNaN(amount)) return String(value);
  return `€${amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

export function prettyDate(value: unknown): string {
  if (!value) return '—';
  const text = String(value);
  return text.length >= 10 ? text.slice(0, 10) : text;
}

export function labelize(key: string): string {
  return key
    .replace(/_id$/, '')
    .replace(/_/g, ' ')
    .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

export const radii = {
  card: 20,
  pill: 999,
  button: 14,
};

export const shadow = {
  card: {
    shadowColor: '#000',
    shadowOpacity: 0.06,
    shadowRadius: 12,
    shadowOffset: { width: 0, height: 4 },
    elevation: 3,
  },
};
