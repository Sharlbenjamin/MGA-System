/** Matches backend FileCaseStatusOverviewService::OPENED_STATUSES */
export const OPENED_CASE_STATUSES = ['New', 'Handling', 'Available', 'Confirmed', 'Hold'] as const;

export function isOpenedCaseStatus(status: unknown): boolean {
  return typeof status === 'string' && (OPENED_CASE_STATUSES as readonly string[]).includes(status);
}

export function filterOpenedCases<T extends { status?: string }>(rows: T[]): T[] {
  return rows.filter((row) => isOpenedCaseStatus(row.status));
}
