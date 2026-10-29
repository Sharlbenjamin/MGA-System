import Constants from 'expo-constants';
import * as SecureStore from 'expo-secure-store';

const PRODUCTION_API_URL = 'https://system.medguarda.com';
const STAGING_API_URL = 'https://mgastaging.medguarda.com';

function configuredBaseUrl(): string {
  const fromExtra = Constants.expoConfig?.extra?.apiUrl;
  if (typeof fromExtra === 'string' && fromExtra.trim()) {
    return fromExtra.trim().replace(/\/+$/, '');
  }

  return __DEV__ ? STAGING_API_URL : PRODUCTION_API_URL;
}

export const DEFAULT_BASE_URL = configuredBaseUrl();

const TOKEN_KEY = 'mga.token';
const USER_KEY = 'mga.user';
const BASE_KEY = 'mga.baseUrl';

let unauthorizedHandler: (() => void) | null = null;

export function setUnauthorizedHandler(handler: () => void) {
  unauthorizedHandler = handler;
}

export async function getBaseUrl(): Promise<string> {
  return (await SecureStore.getItemAsync(BASE_KEY)) || DEFAULT_BASE_URL;
}

export async function setBaseUrl(url: string): Promise<void> {
  const trimmed = url.trim().replace(/\/+$/, '');
  await SecureStore.setItemAsync(BASE_KEY, trimmed || DEFAULT_BASE_URL);
}

export async function getToken(): Promise<string | null> {
  return SecureStore.getItemAsync(TOKEN_KEY);
}

export async function saveSession(token: string, user: unknown): Promise<void> {
  await SecureStore.setItemAsync(TOKEN_KEY, token);
  await SecureStore.setItemAsync(USER_KEY, JSON.stringify(user));
}

export async function readStoredUser<T>(): Promise<T | null> {
  const raw = await SecureStore.getItemAsync(USER_KEY);
  if (!raw) return null;
  try {
    return JSON.parse(raw) as T;
  } catch {
    return null;
  }
}

export async function clearSession(): Promise<void> {
  await SecureStore.deleteItemAsync(TOKEN_KEY);
  await SecureStore.deleteItemAsync(USER_KEY);
}

export class ApiError extends Error {
  status: number;

  constructor(message: string, status: number) {
    super(message);
    this.status = status;
  }
}

type RequestOptions = {
  method?: string;
  json?: unknown;
  form?: FormData;
  auth?: boolean;
};

export async function api<T>(path: string, options: RequestOptions = {}): Promise<T> {
  const base = await getBaseUrl();
  const headers: Record<string, string> = {
    Accept: 'application/json',
  };
  let body: BodyInit | undefined;

  if (options.form) {
    body = options.form;
  } else if (options.json !== undefined) {
    headers['Content-Type'] = 'application/json';
    body = JSON.stringify(options.json);
  }

  if (options.auth !== false) {
    const token = await getToken();
    if (token) headers.Authorization = `Bearer ${token}`;
  }

  const response = await fetch(`${base}/api${path}`, {
    method: options.method || (body ? 'POST' : 'GET'),
    headers,
    body,
  });

  const text = await response.text();
  if (text.trim().startsWith('<')) {
    throw new ApiError(
      'The server returned a web page instead of JSON. Check the API address, and make sure /api is allowed through the firewall.',
      response.status,
    );
  }

  let payload: any = null;
  if (text) {
    try {
      payload = JSON.parse(text);
    } catch {
      payload = { message: text.slice(0, 180) };
    }
  }

  if (response.status === 401 && options.auth !== false) {
    unauthorizedHandler?.();
  }

  if (!response.ok) {
    const errors = payload?.errors;
    const detail = errors
      ? Object.values(errors)
          .flat()
          .filter((item) => typeof item === 'string')
          .join('\n')
      : '';
    throw new ApiError(detail || payload?.message || `Request failed (${response.status})`, response.status);
  }

  return payload as T;
}

export type Page<T> = {
  rows: T[];
  page: number;
  lastPage: number;
  unread?: number;
};

export function unwrapPage<T>(payload: any): Page<T> {
  if (Array.isArray(payload)) {
    return { rows: payload as T[], page: 1, lastPage: 1 };
  }
  const rows = Array.isArray(payload?.data) ? (payload.data as T[]) : [];
  const meta = payload?.meta ?? payload ?? {};
  return {
    rows,
    page: Number(meta.current_page ?? payload?.current_page ?? 1),
    lastPage: Number(meta.last_page ?? payload?.last_page ?? 1),
    unread: payload?.unread_count,
  };
}

export type Option = { label: string; value: string };

export function asOptions(value: unknown): Option[] {
  if (Array.isArray(value)) {
    return value.map((item) => {
      if (typeof item === 'string') return { label: item, value: item };
      const record = item as Record<string, unknown>;
      return {
        label: String(record.label ?? record.name ?? record.company_name ?? record.id ?? ''),
        value: String(record.value ?? record.id ?? record.name ?? ''),
      };
    });
  }
  if (value && typeof value === 'object') {
    return Object.entries(value as Record<string, unknown>).map(([entryValue, label]) => ({
      value: entryValue,
      label: String(label),
    }));
  }
  return [];
}

export async function lookup(path: string, labelOf: (row: any) => string, query = '', extra = ''): Promise<Option[]> {
  const joiner = path.includes('?') ? '&' : '?';
  const payload = await api<any>(
    `${path}${joiner}per_page=10&search=${encodeURIComponent(query)}${extra}`,
  );
  const rows = Array.isArray(payload) ? payload : payload?.data ?? [];
  return rows.map((row: any) => ({ value: String(row.id), label: labelOf(row) }));
}
