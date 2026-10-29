/** Mobile list views always request small pages to keep first paint fast. */
export const MOBILE_LIST_PAGE_SIZE = 10;

export function appendMobileListParams(params: URLSearchParams): URLSearchParams {
  params.set('mobile', '1');
  if (!params.has('per_page')) {
    params.set('per_page', String(MOBILE_LIST_PAGE_SIZE));
  }
  return params;
}

export type ListDefaults = {
  status?: string;
  extra?: Record<string, string>;
};

export function listDefaultsActive(
  defaults: ListDefaults | undefined,
  status: string,
  extra: Record<string, string>,
): boolean {
  if (!defaults) {
    return !status && !Object.values(extra).some(Boolean);
  }
  const targetExtra = defaults.extra ?? {};
  const extraKeys = Object.keys(targetExtra);
  if ((defaults.status ?? '') !== status) {
    return false;
  }
  return extraKeys.every((key) => (extra[key] ?? '') === (targetExtra[key] ?? ''));
}

export function applyListDefaults(
  params: URLSearchParams,
  defaults: ListDefaults | undefined,
  status: string,
  extra: Record<string, string>,
  search: string,
): void {
  const hasSearch = Boolean(search.trim());
  const hasStatus = Boolean(status);
  const hasExtra = Object.values(extra).some(Boolean);

  if (hasStatus) {
    params.set('status', status);
  } else if (!hasSearch && defaults?.status) {
    params.set('status', defaults.status);
  }

  const mergedExtra = { ...extra };
  if (!hasSearch && !hasStatus && !hasExtra && defaults?.extra) {
    Object.entries(defaults.extra).forEach(([key, value]) => {
      if (value && !mergedExtra[key]) {
        mergedExtra[key] = value;
      }
    });
  }

  Object.entries(mergedExtra).forEach(([key, value]) => {
    if (value) {
      params.set(key, value);
    }
  });
}
