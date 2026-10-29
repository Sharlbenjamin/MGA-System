import { router } from 'expo-router';
import React, { useCallback, useState } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { Pill } from '../../src/components/ui';
import { useFocusEffect } from 'expo-router';
import { api } from '../../src/api';
import { AppNotification, fetchNotifications, notificationRoute } from '../../src/alerts';
import { useAuth } from '../../src/auth';
import { ScreenHeader } from '../../src/components/ui';
import { colors, prettyDate } from '../../src/theme';

export default function AlertsScreen() {
  const { signOut } = useAuth();
  const [rows, setRows] = useState<AppNotification[]>([]);
  const [error, setError] = useState('');
  const [unreadOnly, setUnreadOnly] = useState(true);

  const load = useCallback(async () => {
    try {
      const result = await fetchNotifications(unreadOnly);
      setRows(result.rows);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Could not load alerts');
    }
  }, [unreadOnly]);

  useFocusEffect(
    useCallback(() => {
      load();
    }, [load]),
  );

  return (
    <View style={styles.screen}>
      <ScreenHeader title="Alerts" />
      <View style={styles.filters}>
        <Pill label="Unread" active={unreadOnly} onPress={() => setUnreadOnly(true)} />
        <Pill label="Recent" active={!unreadOnly} onPress={() => setUnreadOnly(false)} />
      </View>
      <ScrollView contentContainerStyle={styles.body} keyboardShouldPersistTaps="handled">
        <Pressable
          onPress={async () => {
            await api('/notifications/read-all', { method: 'POST', json: {} });
            await load();
          }}
        >
          <Text style={styles.mark}>Mark all read</Text>
        </Pressable>
        {error ? <Text style={styles.error}>{error}</Text> : null}
        {rows.map((item) => {
          const destination = notificationRoute(item);
          return (
            <Pressable
              key={item.id}
              style={[styles.card, !item.read_at && styles.unread]}
              onPress={async () => {
                await api(`/notifications/${item.id}/read`, { method: 'POST', json: {} });
                if (destination) router.push(destination as any);
                else load();
              }}
            >
              <Text style={styles.title}>{item.data?.title || 'Update'}</Text>
              <Text style={styles.bodyText}>{item.data?.body}</Text>
              <Text style={styles.date}>{prettyDate(item.created_at)}</Text>
            </Pressable>
          );
        })}
        {!rows.length ? <Text style={styles.empty}>No alerts yet. New cases, Assisted cases, and newly active clients or providers show up here.</Text> : null}
        <Pressable
          onPress={async () => {
            await signOut();
            router.replace('/login');
          }}
        >
          <Text style={styles.signOut}>Sign out</Text>
        </Pressable>
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.paper },
  filters: { flexDirection: 'row', gap: 8, paddingHorizontal: 20, paddingBottom: 4 },
  body: { padding: 20, gap: 10, paddingBottom: 40 },
  mark: { color: colors.teal, fontWeight: '700', marginBottom: 6 },
  card: { backgroundColor: colors.card, borderRadius: 14, padding: 14, gap: 4, borderWidth: 1, borderColor: colors.line },
  unread: { borderColor: colors.copper },
  title: { fontWeight: '700', color: colors.ink, fontSize: 16 },
  bodyText: { color: colors.ink },
  date: { color: colors.muted, fontSize: 12 },
  empty: { color: colors.muted, lineHeight: 20 },
  error: { color: colors.danger },
  signOut: { color: colors.danger, fontWeight: '700', textAlign: 'center', marginTop: 24 },
});
