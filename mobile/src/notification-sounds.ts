import * as SecureStore from 'expo-secure-store';
import { Platform } from 'react-native';
import { api } from './api';

export const NOTIFICATION_SOUND_KEY = 'mga.notificationSound';

export type NotificationSoundId = 'default' | 'none' | 'mga_bell.wav' | 'mga_chime.wav' | 'mga_alert.wav';

export const NOTIFICATION_SOUND_OPTIONS: { id: NotificationSoundId; label: string }[] = [
  { id: 'default', label: 'System default' },
  { id: 'mga_bell.wav', label: 'Bell' },
  { id: 'mga_chime.wav', label: 'Chime' },
  { id: 'mga_alert.wav', label: 'Alert tone' },
  { id: 'none', label: 'Silent' },
];

const ALLOWED = new Set(NOTIFICATION_SOUND_OPTIONS.map((item) => item.id));

export function normalizeNotificationSound(value: unknown): NotificationSoundId {
  if (typeof value === 'string' && ALLOWED.has(value as NotificationSoundId)) {
    return value as NotificationSoundId;
  }
  return 'default';
}

export async function getNotificationSound(): Promise<NotificationSoundId> {
  const stored = await SecureStore.getItemAsync(NOTIFICATION_SOUND_KEY);
  return normalizeNotificationSound(stored);
}

export async function setNotificationSound(id: NotificationSoundId): Promise<void> {
  const next = normalizeNotificationSound(id);
  await SecureStore.setItemAsync(NOTIFICATION_SOUND_KEY, next);
  try {
    await api('/push-token/preferences', {
      method: 'PATCH',
      json: { notification_sound: next },
    });
  } catch {
    // Preference is stored locally; remote push picks it up on next token registration.
  }
}

/** Local notification `content.sound` value for expo-notifications. */
export function localNotificationSound(id: NotificationSoundId): string | boolean | undefined {
  if (id === 'none') {
    return Platform.OS === 'android' ? undefined : false;
  }
  if (id === 'default') {
    return 'default';
  }
  return id;
}
