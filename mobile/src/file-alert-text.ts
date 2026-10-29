export function fileClientName(row: {
  client?: { company_name?: string | null } | null;
  patient?: { client?: { company_name?: string | null } | null } | null;
}): string {
  return (
    row.client?.company_name?.trim() ||
    row.patient?.client?.company_name?.trim() ||
    ''
  );
}

export function fileCountryName(row: {
  country?: { nicename?: string | null; name?: string | null } | null;
}): string {
  return row.country?.nicename?.trim() || row.country?.name?.trim() || '';
}

export function fileAlertDescription(
  row: Parameters<typeof fileClientName>[0] & Parameters<typeof fileCountryName>[0],
  suffix: string,
): string {
  const parts = [fileClientName(row), fileCountryName(row)].filter(Boolean);
  if (parts.length) {
    return `${parts.join(' · ')} — ${suffix}`;
  }
  return suffix;
}
