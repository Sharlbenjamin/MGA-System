import * as Notifications from 'expo-notifications';
import * as Device from 'expo-device';
import Constants from 'expo-constants';
import * as SecureStore from 'expo-secure-store';
import { AppState, Platform } from 'react-native';
import { api, getToken } from './api';
import { fileAlertDescription } from './file-alert-text';
import { getNotificationSound, localNotificationSound } from './notification-sounds';

const SEEN_KEY = 'mga.seenAlerts';
const BASELINE_KEY = 'mga.alertBaseline';
const PUSH_KEY = 'mga.pushReady';
const PUSH_ERROR_KEY = 'mga.pushLastError';
const EXPO_TOKEN_KEY = 'mga.expoPushToken';

Notifications.setNotificationHandler({
  handleNotification: async () => ({
    shouldShowAlert: true,
    shouldPlaySound: true,
    shouldSetBadge: true,
    shouldShowBanner: true,
    shouldShowList: true,
  }),
});

export type AppNotification = {
  id: string;
  read_at?: string | null;
  created_at?: string;
  data?: {
    title?: string;
    body?: string;
    viewData?: {
      kind?: string;
      subject_type?: string;
      subject_id?: number | string;
    };
  };
};

export function notificationRoute(item: AppNotification): string | null {
  const subject = item.data?.viewData;
  const id = subject?.subject_id;
  if (!id) return null;
  if (subject?.subject_type === 'file') return `/cases/${id}`;
  if (subject?.subject_type === 'client') return `/crm/clients/${id}`;
  if (subject?.subject_type === 'provider') return `/prm/providers/${id}`;
  return null;
}

export async function fetchNotifications(unreadOnly = false): Promise<{ rows: AppNotification[]; unread: number }> {
  const payload = await api<any>(`/notifications?per_page=10${unreadOnly ? '&unread=1' : ''}`);
  return {
    rows: payload?.data ?? [],
    unread: Number(payload?.unread_count ?? 0),
  };
}

async function remember(ids: string[]) {
  await SecureStore.setItemAsync(SEEN_KEY, ids.slice(-30).join(','));
}

export async function pollNewAlerts(presentLocal: boolean, forceLocal = false): Promise<number> {
  const token = await getToken();
  if (!token) return 0;

  const { rows, unread } = await fetchNotifications(true);
  const baseline = await SecureStore.getItemAsync(BASELINE_KEY);
  const seen = new Set((await SecureStore.getItemAsync(SEEN_KEY) || '').split(',').filter(Boolean));

  if (!baseline) {
    await SecureStore.setItemAsync(BASELINE_KEY, new Date().toISOString());
    await remember(rows.map((row) => row.id));
    return unread;
  }

  const pushReady = (await SecureStore.getItemAsync(PUSH_KEY)) === '1';
  const appActive = AppState.currentState === 'active';
  const fresh: string[] = [];
  for (const row of rows) {
    if (seen.has(row.id)) continue;
    const created = row.created_at || baseline;
    if (created < baseline) {
      fresh.push(row.id);
      continue;
    }
    // Remote push delivers lock-screen alerts. Local inbox banners only when push is not registered.
    const shouldPresentLocal = presentLocal && appActive && !pushReady;
    if (forceLocal && !pushReady && presentLocal) {
      // Reserved for future fallback; remote push is the closed-app delivery path.
    }
    if (shouldPresentLocal) {
      const soundPref = await getNotificationSound();
      const sound = localNotificationSound(soundPref);
      await Notifications.scheduleNotificationAsync({
        identifier: `mga-inbox-${row.id}`,
        content: {
          title: row.data?.title || 'MGA',
          body: row.data?.body || 'There is a new update.',
          ...(sound !== undefined ? { sound } : {}),
          data: {
            subject_type: row.data?.viewData?.subject_type || '',
            subject_id: String(row.data?.viewData?.subject_id || ''),
          },
        },
        trigger: null,
      });
    }
    fresh.push(row.id);
  }

  if (fresh.length) {
    await remember([...seen, ...fresh]);
  }
  return unread;
}

export async function registerPushToken(): Promise<boolean> {
  if (!Device.isDevice) {
    await SecureStore.setItemAsync(PUSH_KEY, '0');
    return false;
  }

  const permission = await Notifications.getPermissionsAsync();
  let status = permission.status;
  if (status !== 'granted') {
    status = (
      await Notifications.requestPermissionsAsync({
        ios: {
          allowAlert: true,
          allowBadge: true,
          allowSound: true,
        },
      })
    ).status;
  }
  if (status !== 'granted') {
    await SecureStore.setItemAsync(PUSH_KEY, '0');
    return false;
  }

  if (Platform.OS === 'android') {
    await Notifications.setNotificationChannelAsync('default', {
      name: 'MGA alerts',
      importance: Notifications.AndroidImportance.MAX,
      vibrationPattern: [0, 250, 250, 250],
      lightColor: '#0F3D3E',
    });
  }

  const projectId =
    Constants.expoConfig?.extra?.eas?.projectId ||
    Constants.easConfig?.projectId ||
    '';
  if (!projectId) {
    await SecureStore.setItemAsync(PUSH_KEY, '0');
    return false;
  }

  try {
    const token = await Notifications.getExpoPushTokenAsync({ projectId });
    await SecureStore.setItemAsync(EXPO_TOKEN_KEY, token.data);
    const notification_sound = await getNotificationSound();
    await api('/push-token', {
      method: 'POST',
      json: { token: token.data, platform: Platform.OS, notification_sound },
    });
    await SecureStore.setItemAsync(PUSH_KEY, '1');
    await SecureStore.deleteItemAsync(PUSH_ERROR_KEY);
    return true;
  } catch (err) {
    const message = err instanceof Error ? err.message : 'Push registration failed';
    await SecureStore.setItemAsync(PUSH_KEY, '0');
    await SecureStore.setItemAsync(PUSH_ERROR_KEY, message.slice(0, 180));
    return false;
  }
}

export async function unregisterPushToken(): Promise<void> {
  const stored = await SecureStore.getItemAsync(EXPO_TOKEN_KEY);
  if (stored) {
    try {
      await api('/push-token', { method: 'DELETE', json: { token: stored } });
    } catch {
      // Session may already be invalid during logout.
    }
  }
  await SecureStore.setItemAsync(PUSH_KEY, '0');
}

export async function fetchPushDiagnostics(): Promise<{
  local_ready: boolean;
  local_error?: string;
  server?: { registered_device_count?: number; note?: string; storage?: string; last_registered_at?: string | null };
}> {
  const local_ready = (await SecureStore.getItemAsync(PUSH_KEY)) === '1';
  const local_error = (await SecureStore.getItemAsync(PUSH_ERROR_KEY)) || undefined;
  try {
    const server = await api<any>('/push-token/status');
    return { local_ready, local_error, server };
  } catch (err) {
    return {
      local_ready,
      local_error: local_error || (err instanceof Error ? err.message : 'Could not reach server'),
    };
  }
}

const WATCH_KEY = 'mga.caseWatch';
const FRESH_MS = 36 * 60 * 60 * 1000;

type WatchState = { seeded: boolean; created: string[]; assisted: string[]; sent: string[] };

function rememberId(ids: string[], id: string): string[] {
  return [...ids.filter((item) => item !== id), id].slice(-60);
}

function isRecent(value: unknown): boolean {
  const time = Date.parse(String(value || ''));
  if (!Number.isFinite(time)) return false;
  const age = Date.now() - time;
  return age >= -5 * 60 * 1000 && age < FRESH_MS;
}

async function readWatch(): Promise<WatchState> {
  const raw = await SecureStore.getItemAsync(WATCH_KEY);
  if (!raw) return { seeded: false, created: [], assisted: [], sent: [] };
  try {
    const parsed = JSON.parse(raw) as Partial<WatchState>;
    return {
      seeded: Boolean(parsed.seeded),
      created: Array.isArray(parsed.created) ? parsed.created.map(String) : [],
      assisted: Array.isArray(parsed.assisted) ? parsed.assisted.map(String) : [],
      sent: Array.isArray(parsed.sent) ? parsed.sent.map(String) : [],
    };
  } catch {
    return { seeded: false, created: [], assisted: [], sent: [] };
  }
}

async function allowBanners(): Promise<boolean> {
  const current = await Notifications.getPermissionsAsync();
  if (current.status === 'granted') return true;
  return (await Notifications.requestPermissionsAsync()).status === 'granted';
}

async function showBanner(title: string, body: string, id: string) {
  const soundPref = await getNotificationSound();
  const sound = localNotificationSound(soundPref);
  await Notifications.scheduleNotificationAsync({
    content: {
      title,
      body,
      ...(sound !== undefined ? { sound } : {}),
      data: { subject_type: 'file', subject_id: id },
    },
    trigger: null,
  });
}

export async function clearCaseWatch(): Promise<void> {
  await SecureStore.deleteItemAsync(WATCH_KEY);
}

let watchRunning = false;

export async function watchCaseChanges(): Promise<boolean> {
  if (watchRunning) return false;
  const token = await getToken();
  if (!token) return false;
  if ((await SecureStore.getItemAsync(PUSH_KEY)) === '1') {
    return false;
  }
  watchRunning = true;
  try {
    const allowed = await allowBanners();
    const [createdPayload, assistedPayload, sentPayload] = await Promise.all([
      api<any>('/files?mobile=1&per_page=10&sort=created_at&direction=desc&opened=1'),
      api<any>('/files?status=Assisted&per_page=10&sort=updated_at&direction=desc'),
      api<any>('/invoices?status=Sent&per_page=10&sort=updated_at&direction=desc').catch(() => ({ data: [] })),
    ]);
    const createdRows = Array.isArray(createdPayload?.data) ? createdPayload.data : [];
    const assistedRows = Array.isArray(assistedPayload?.data) ? assistedPayload.data : [];
    const sentRows = Array.isArray(sentPayload?.data) ? sentPayload.data : [];
    const state = await readWatch();
    let created = state.created;
    let assisted = state.assisted;
    let sent = state.sent;
    let found = false;

    if (!state.seeded) {
      await SecureStore.setItemAsync(
        WATCH_KEY,
        JSON.stringify({
          seeded: true,
          created: createdRows.map((row: { id: number }) => String(row.id)).slice(-60),
          assisted: assistedRows.map((row: { id: number }) => String(row.id)).slice(-60),
          sent: sentRows.map((row: { id: number }) => String(row.id)).slice(-60),
        }),
      );
      return false;
    }

    for (const row of createdRows) {
      const id = String(row.id);
      if (!created.includes(id)) {
        if (allowed && isRecent(row.created_at)) {
          const ref = row.mga_reference || `Case #${id}`;
          await showBanner(
            `New case ${ref}`,
            fileAlertDescription(row, 'A new case was created.'),
            id,
          );
          found = true;
        }
        created = rememberId(created, id);
      }
    }

    for (const row of assistedRows) {
      const id = String(row.id);
      if (!assisted.includes(id)) {
        if (allowed && isRecent(row.updated_at)) {
          const ref = row.mga_reference || `Case #${id}`;
          await showBanner(
            `Case ${ref} is Assisted`,
            fileAlertDescription(row, 'Status changed to Assisted.'),
            id,
          );
          found = true;
        }
        assisted = rememberId(assisted, id);
      }
    }

    for (const row of sentRows) {
      const id = String(row.id);
      if (!sent.includes(id)) {
        if (allowed && isRecent(row.updated_at)) {
          const fileId = String(row.file_id || row.file?.id || '');
          const ref = row.file?.mga_reference || (fileId ? `Case #${fileId}` : `Invoice #${id}`);
          const fileRow = row.file || {};
          await showBanner(
            `Case ${ref} is Sent`,
            fileRow.id
              ? fileAlertDescription(fileRow, row.name ? `${row.name} was marked Sent.` : 'A case invoice was marked Sent.')
              : row.name
                ? `${row.name} was marked Sent.`
                : 'A case invoice was marked Sent.',
            fileId || id,
          );
          found = true;
        }
        sent = rememberId(sent, id);
      }
    }

    await SecureStore.setItemAsync(WATCH_KEY, JSON.stringify({ seeded: true, created, assisted, sent }));
    return found;
  } finally {
    watchRunning = false;
  }
}

export async function backgroundPoll(): Promise<boolean> {
  await pollNewAlerts(false).catch(() => 0);
  return false;
}
