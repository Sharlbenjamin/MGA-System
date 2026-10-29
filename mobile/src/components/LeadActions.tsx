import { router } from 'expo-router';
import React, { useState } from 'react';
import { Alert, Linking, Pressable, StyleSheet, Text, View } from 'react-native';
import { api } from '../api';
import { can, useAuth } from '../auth';
import { useCatalog } from '../catalog';
import { colors, prettyDate } from '../theme';
import { Fields } from './Fields';

export function LeadActions({ record, onChange }: { record: any; onChange: (row: any) => void }) {
  const { user } = useAuth();
  const { catalog } = useCatalog();
  const [clientStatus, setClientStatus] = useState(String(record.client?.status || ''));
  const [busy, setBusy] = useState('');
  const [error, setError] = useState('');
  const linkedIn = record.linked_in
    ? /^https?:\/\//i.test(record.linked_in)
      ? record.linked_in
      : `https://${record.linked_in}`
    : null;
  const canEdit = can(user, 'edit Lead');
  const canEditClient = can(user, 'edit Client');
  const interactions = Array.isArray(record.interactions) ? record.interactions : [];

  return (
    <View style={{ gap: 12 }}>
      <View style={styles.card}>
        <Text style={styles.label}>Follow-up</Text>
        <Text style={styles.value}>{record.follow_up_label || 'No contact date'}</Text>
        {record.client?.status ? (
          <Text style={styles.meta}>
            Client {record.client.company_name} · {record.client.status}
          </Text>
        ) : null}
      </View>
      <View style={styles.row}>
        {record.client_id ? (
          <Pressable style={styles.secondary} onPress={() => router.push(`/crm/clients/${record.client_id}` as any)}>
            <Text style={styles.secondaryText}>View client</Text>
          </Pressable>
        ) : null}
        {linkedIn ? (
          <Pressable style={styles.secondary} onPress={() => Linking.openURL(linkedIn)}>
            <Text style={styles.secondaryText}>LinkedIn</Text>
          </Pressable>
        ) : null}
        {canEdit ? (
          <Pressable
            style={styles.secondary}
            onPress={async () => {
              setBusy('email');
              setError('');
              try {
                onChange(await api(`/leads/${record.id}/send-email`, { method: 'POST' }));
              } catch (err) {
                const message = err instanceof Error ? err.message : 'Could not send email';
                setError(message);
                Alert.alert('Send email', message);
              } finally {
                setBusy('');
              }
            }}
          >
            <Text style={styles.secondaryText}>{busy === 'email' ? 'Sending…' : 'Send email'}</Text>
          </Pressable>
        ) : null}
      </View>
      {canEditClient && record.client_id ? (
        <View style={{ gap: 10 }}>
          <Fields
            fields={[{ name: 'status', label: 'Client status', kind: 'select', optionsKey: 'client_statuses' }]}
            values={{ status: clientStatus }}
            labels={{}}
            catalog={catalog}
            onChange={(_, value) => setClientStatus(String(value))}
            onLabel={() => undefined}
          />
          <Pressable
            style={styles.secondary}
            onPress={async () => {
              setBusy('status');
              setError('');
              try {
                const client = await api<any>(`/clients/${record.client_id}`, { method: 'PATCH', json: { status: clientStatus } });
                onChange({ ...record, client: { ...record.client, ...client } });
              } catch (err) {
                setError(err instanceof Error ? err.message : 'Could not update client status');
              } finally {
                setBusy('');
              }
            }}
          >
            <Text style={styles.secondaryText}>{busy === 'status' ? 'Saving…' : 'Save client status'}</Text>
          </Pressable>
        </View>
      ) : null}
      {error ? <Text style={styles.error}>{error}</Text> : null}
      {interactions.length ? (
        <View style={{ gap: 8 }}>
          <Text style={styles.section}>Interactions</Text>
          {interactions.map((item: any) => (
            <View key={item.id} style={styles.line}>
              <Text style={styles.value}>{[item.method, item.status].filter(Boolean).join(' · ')}</Text>
              <Text style={styles.meta}>
                {[item.user?.name, prettyDate(item.interaction_date || item.created_at)].filter(Boolean).join(' · ')}
              </Text>
            </View>
          ))}
        </View>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  card: { backgroundColor: colors.card, borderRadius: 14, padding: 14, borderWidth: 1, borderColor: colors.line, gap: 4 },
  label: { color: colors.muted, fontSize: 12, fontWeight: '700' },
  value: { color: colors.ink, fontWeight: '700' },
  meta: { color: colors.muted },
  row: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  secondary: {
    backgroundColor: colors.card,
    borderRadius: 14,
    paddingHorizontal: 14,
    paddingVertical: 12,
    borderWidth: 1,
    borderColor: colors.line,
  },
  secondaryText: { color: colors.ink, fontWeight: '700' },
  error: { color: colors.danger },
  section: { fontSize: 18, fontWeight: '700', color: colors.ink },
  line: { backgroundColor: colors.card, borderRadius: 12, padding: 12, borderWidth: 1, borderColor: colors.line, gap: 2 },
});
