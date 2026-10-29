import React, { useCallback, useMemo, useState } from 'react';
import { View, StyleSheet, Text } from 'react-native';
import { useFocusEffect } from 'expo-router';
import { api } from '../../../src/api';
import { useAuth } from '../../../src/auth';
import { DashboardPayload, DashboardView } from '../../../src/components/DashboardView';
import { colors } from '../../../src/theme';

function greetingForHour(hour: number): string {
  if (hour < 12) return 'Good morning';
  if (hour < 17) return 'Good afternoon';
  return 'Good evening';
}

export default function HomeScreen() {
  const { user } = useAuth();
  const [duration, setDuration] = useState('Month');
  const [data, setData] = useState<DashboardPayload | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const header = useMemo(() => {
    const now = new Date();
    const weekday = now.toLocaleDateString(undefined, { weekday: 'long' });
    const date = now.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
    return {
      line: `${weekday}, ${date}`,
      greet: `${greetingForHour(now.getHours())}, ${user?.name?.split(' ')[0] || 'team'}`,
      initial: (user?.name?.trim()?.[0] || user?.email?.trim()?.[0] || '?').toUpperCase(),
    };
  }, [user?.email, user?.name]);

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const payload = await api<DashboardPayload>(`/dashboard?duration=${encodeURIComponent(duration)}`);
      setData(payload);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Dashboard unavailable');
    } finally {
      setLoading(false);
    }
  }, [duration]);

  useFocusEffect(
    useCallback(() => {
      load();
    }, [load]),
  );

  return (
    <View style={styles.screen}>
      <View style={styles.header}>
        <View style={{ flex: 1, gap: 2 }}>
          <Text style={styles.dateLine}>{header.line}</Text>
          <Text style={styles.greeting}>{header.greet}</Text>
        </View>
        <View style={styles.avatar}>
          <Text style={styles.avatarText}>{header.initial}</Text>
        </View>
      </View>
      <DashboardView
        data={data}
        loading={loading}
        error={error}
        duration={duration}
        onDurationChange={setDuration}
        onRefresh={load}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.paper },
  header: { flexDirection: 'row', alignItems: 'center', paddingHorizontal: 20, paddingTop: 8, paddingBottom: 4, gap: 12 },
  dateLine: { color: colors.muted, fontSize: 13, fontWeight: '600' },
  greeting: { color: colors.ink, fontSize: 20, fontWeight: '800' },
  avatar: {
    width: 44,
    height: 44,
    borderRadius: 22,
    backgroundColor: colors.primarySoft,
    borderWidth: 2,
    borderColor: colors.primary,
    alignItems: 'center',
    justifyContent: 'center',
  },
  avatarText: { color: colors.primary, fontWeight: '800', fontSize: 18 },
});
