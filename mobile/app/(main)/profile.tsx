import { router } from 'expo-router';
import React, { useCallback, useState } from 'react';
import { Image, Pressable, StyleSheet, Text, View } from 'react-native';
import { useFocusEffect } from 'expo-router';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { fetchPushDiagnostics, registerPushToken } from '../../src/alerts';
import { useAuth } from '../../src/auth';
import {
  getNotificationSound,
  NOTIFICATION_SOUND_OPTIONS,
  NotificationSoundId,
  setNotificationSound,
} from '../../src/notification-sounds';
import { colors } from '../../src/theme';

export default function ProfileScreen() {
  const { user, signOut } = useAuth();
  const insets = useSafeAreaInsets();
  const roles = user?.roles?.length ? user.roles.join(', ') : 'Signed in';
  const [sound, setSound] = useState<NotificationSoundId>('default');
  const [pushNote, setPushNote] = useState('');

  useFocusEffect(
    useCallback(() => {
      getNotificationSound().then(setSound).catch(() => undefined);
      fetchPushDiagnostics()
        .then((diag) => {
          const parts = [
            diag.local_ready ? 'Device registered for push' : 'Device not registered for push',
            diag.local_error ? `Local: ${diag.local_error}` : '',
            diag.server?.note || '',
            diag.server?.storage ? `Server storage: ${diag.server.storage}` : '',
          ].filter(Boolean);
          setPushNote(parts.join('\n'));
        })
        .catch(() => undefined);
    }, []),
  );

  return (
    <View style={[styles.screen, { paddingTop: insets.top + 24, paddingBottom: insets.bottom + 24 }]}>
      <View style={styles.mark}>
        <Image source={require('../../assets/logo.png')} style={styles.logo} resizeMode="contain" />
      </View>
      <Text style={styles.name}>{user?.name || 'Account'}</Text>
      <Text style={styles.email}>{user?.email}</Text>
      <Text style={styles.role}>{roles}</Text>
      {user?.signature?.job_title ? <Text style={styles.role}>{user.signature.job_title}</Text> : null}

      <Text style={styles.sectionTitle}>Remote notifications</Text>
      {pushNote ? <Text style={styles.sectionHint}>{pushNote}</Text> : null}
      <Pressable
        style={styles.pushButton}
        onPress={async () => {
          await registerPushToken();
          const diag = await fetchPushDiagnostics();
          setPushNote(diag.server?.note || (diag.local_ready ? 'Registered' : 'Registration failed'));
        }}
      >
        <Text style={styles.pushButtonText}>Re-register this device</Text>
      </Pressable>

      <Text style={styles.sectionTitle}>Notification sound</Text>
      <Text style={styles.sectionHint}>Applies to alerts on this device, including lock-screen push when supported.</Text>
      <View style={styles.soundList}>
        {NOTIFICATION_SOUND_OPTIONS.map((option) => (
          <Pressable
            key={option.id}
            style={[styles.soundRow, sound === option.id && styles.soundRowActive]}
            onPress={async () => {
              setSound(option.id);
              await setNotificationSound(option.id);
            }}
          >
            <Text style={[styles.soundLabel, sound === option.id && styles.soundLabelActive]}>{option.label}</Text>
            {sound === option.id ? <Text style={styles.check}>✓</Text> : null}
          </Pressable>
        ))}
      </View>

      <Pressable
        style={styles.button}
        onPress={async () => {
          await signOut();
          router.replace('/login');
        }}
      >
        <Text style={styles.buttonText}>Log out</Text>
      </Pressable>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.paper, paddingHorizontal: 24, gap: 8 },
  mark: {
    width: 84,
    height: 84,
    borderRadius: 22,
    backgroundColor: '#000000',
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 8,
  },
  logo: { width: 60, height: 60 },
  name: { fontSize: 28, fontWeight: '700', color: colors.ink },
  email: { fontSize: 16, color: colors.muted },
  role: { fontSize: 15, color: colors.ink, fontWeight: '600' },
  sectionTitle: { marginTop: 20, fontSize: 17, fontWeight: '700', color: colors.ink },
  sectionHint: { fontSize: 14, color: colors.muted, lineHeight: 20, marginBottom: 4 },
  soundList: { gap: 8, marginBottom: 8 },
  soundRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    backgroundColor: colors.card,
    borderWidth: 1,
    borderColor: colors.line,
    borderRadius: 12,
    paddingHorizontal: 14,
    minHeight: 48,
  },
  soundRowActive: { borderColor: colors.primary, backgroundColor: colors.primarySoft },
  soundLabel: { fontSize: 16, color: colors.ink, fontWeight: '500' },
  soundLabelActive: { color: colors.primary, fontWeight: '700' },
  check: { color: colors.primary, fontSize: 18, fontWeight: '700' },
  pushButton: {
    backgroundColor: colors.primary,
    borderRadius: 12,
    minHeight: 44,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 8,
  },
  pushButtonText: { color: colors.white, fontWeight: '700' },
  button: {
    marginTop: 24,
    backgroundColor: colors.card,
    borderWidth: 1,
    borderColor: colors.line,
    borderRadius: 14,
    minHeight: 52,
    alignItems: 'center',
    justifyContent: 'center',
  },
  buttonText: { color: colors.danger, fontWeight: '700', fontSize: 17 },
});
