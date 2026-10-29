import * as DocumentPicker from 'expo-document-picker';
import { router, useLocalSearchParams } from 'expo-router';
import React, { useEffect, useState } from 'react';
import { Alert, Linking, Pressable, ScrollView, StyleSheet, Text, TextInput, View } from 'react-native';
import { api } from '../api';
import { can, useAuth } from '../auth';
import { useCatalog } from '../catalog';
import { FormValues, ResourceConfig } from '../resources';
import { colors, labelize, money } from '../theme';
import { emptyValues, Fields, payloadFromValues, valuesFromRecord } from './Fields';
import { LeadActions } from './LeadActions';
import { FormScreen, ScreenHeader, useFullScreen } from './ui';

export function Editor({ resources }: { resources: Record<string, ResourceConfig> }) {
  const params = useLocalSearchParams<{ kind: string; id: string }>();
  const resource = resources[String(params.kind)];
  const creating = params.id === 'new';
  const { user } = useAuth();
  const { catalog } = useCatalog();
  useFullScreen();
  const [values, setValues] = useState<FormValues>({});
  const [labels, setLabels] = useState<Record<string, string>>({});
  const [record, setRecord] = useState<any>(null);
  const [files, setFiles] = useState<Record<string, { uri: string; name: string; mimeType?: string }>>({});
  const [item, setItem] = useState({ description: '', amount: '', discount: '', tax: '' });
  const [error, setError] = useState('');
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    if (!resource) return;
    if (creating) {
      setValues(emptyValues(resource.fields, resource.defaults));
      return;
    }
    api<any>(`${resource.endpoint}/${params.id}`)
      .then((row) => {
        setRecord(row);
        const extra = resource.fromRecord?.(row) || {};
        setValues(valuesFromRecord(row, resource.fields, extra));
        setLabels(resource.labels?.(row) || {});
      })
      .catch((err) => setError(err instanceof Error ? err.message : 'Could not open this record'));
  }, [creating, params.id, resource]);

  if (!resource) {
    return (
      <View style={styles.screen}>
        <ScreenHeader title="Missing" back />
      </View>
    );
  }

  const allowed = can(user, creating ? resource.permissionCreate : resource.permissionEdit, resource.adminOnly);

  const save = async () => {
    setSaving(true);
    setError('');
    try {
      const basePayload = payloadFromValues(values, resource.fields);
      const missing = resource.fields.find((field) => {
        if (!field.required) return false;
        if (field.when === 'create' && !creating) return false;
        if (field.when === 'edit' && creating) return false;
        if (field.visible && !field.visible(values)) return false;
        const value = values[field.name];
        return value === '' || value === undefined || (Array.isArray(value) && value.length === 0);
      });
      if (missing) throw new Error(`${missing.label} is required`);
      const extra = resource.toPayload?.(values) || {};
      const payload = { ...basePayload, ...extra };
      if (Object.keys(files).length) {
        const form = new FormData();
        Object.entries(payload).forEach(([key, value]) => {
          if (value === null || value === undefined) return;
          if (typeof value === 'object') form.append(key, JSON.stringify(value));
          else form.append(key, String(value));
        });
        Object.entries(files).forEach(([key, file]) => {
          form.append(key, { uri: file.uri, name: file.name, type: file.mimeType || 'application/octet-stream' } as any);
        });
        if (!creating) form.append('_method', 'PATCH');
        await api(creating ? resource.endpoint : `${resource.endpoint}/${params.id}`, {
          method: 'POST',
          form,
        });
      } else {
        await api(creating ? resource.endpoint : `${resource.endpoint}/${params.id}`, {
          method: creating ? 'POST' : 'PATCH',
          json: payload,
        });
      }
      router.back();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Could not save');
    } finally {
      setSaving(false);
    }
  };

  const remove = () => {
    Alert.alert(`Delete ${resource.singular.toLowerCase()}?`, 'This cannot be undone.', [
      { text: 'Cancel', style: 'cancel' },
      {
        text: 'Delete',
        style: 'destructive',
        onPress: async () => {
          try {
            await api(`${resource.endpoint}/${params.id}`, { method: 'DELETE' });
            router.back();
          } catch (err) {
            setError(err instanceof Error ? err.message : 'Could not delete');
          }
        },
      },
    ]);
  };

  const addItem = async () => {
    try {
      await api(`${resource.endpoint}/${params.id}/items`, {
        method: 'POST',
        json: {
          description: item.description,
          amount: item.amount,
          discount: item.discount || 0,
          tax: item.tax || 0,
        },
      });
      const fresh = await api<any>(`${resource.endpoint}/${params.id}`);
      setRecord(fresh);
      setItem({ description: '', amount: '', discount: '', tax: '' });
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Could not add the line');
    }
  };

  return (
    <View style={styles.screen}>
      <FormScreen
        footer={
          allowed ? (
            <Pressable style={styles.primary} onPress={save} disabled={saving}>
              <Text style={styles.primaryText}>{saving ? 'Saving…' : 'Save'}</Text>
            </Pressable>
          ) : null
        }
      >
      <ScreenHeader title={creating ? `New ${resource.singular.toLowerCase()}` : resource.singular} back />
      <ScrollView style={styles.fill} keyboardShouldPersistTaps="handled" contentContainerStyle={styles.body}>
        {!creating && record ? (
          <View style={styles.summary}>
            <Text style={styles.summaryTitle}>{resource.title(record)}</Text>
            <Text style={styles.summarySub}>{resource.subtitle(record)}</Text>
            {resource.amount?.(record) ? <Text style={styles.summaryAmount}>{resource.amount(record)}</Text> : null}
            {record.cv_url ? (
              <Pressable onPress={() => Linking.openURL(record.cv_url)}>
                <Text style={styles.link}>Open CV</Text>
              </Pressable>
            ) : null}
            {record.photo_id_url ? (
              <Pressable onPress={() => Linking.openURL(record.photo_id_url)}>
                <Text style={styles.link}>Open photo ID</Text>
              </Pressable>
            ) : null}
            {record.signed_contract_url ? (
              <Pressable onPress={() => Linking.openURL(record.signed_contract_url)}>
                <Text style={styles.link}>Open signed contract</Text>
              </Pressable>
            ) : null}
          </View>
        ) : null}
        {allowed ? (
          <>
            <Fields
              fields={resource.fields}
              values={values}
              labels={labels}
              catalog={catalog}
              mode={creating ? 'create' : 'edit'}
              onChange={(name, value) => {
                setValues((current) => {
                  const next = { ...current, [name]: value };
                  if (name === 'new_client_company_name' && typeof value === 'string') {
                    next.new_client_initials = value
                      .trim()
                      .split(/\s+/)
                      .map((word) => word.match(/[\p{L}\p{N}]/u)?.[0] || '')
                      .join('')
                      .toUpperCase()
                      .slice(0, 10);
                  }
                  return next;
                });
              }}
              onLabel={(name, label) => setLabels((current) => ({ ...current, [name]: label }))}
            />
          </>
        ) : (
          <Text style={styles.error}>You can view this record, but your account cannot change it.</Text>
        )}
        {!creating && resource.key === 'leads' && record ? <LeadActions record={record} onChange={setRecord} /> : null}
        {resource.attachments?.map((attachment) => (
          <Pressable
            key={attachment.name}
            style={styles.secondary}
            onPress={async () => {
              const picked = await DocumentPicker.getDocumentAsync({ copyToCacheDirectory: true });
              if (picked.canceled || !picked.assets?.[0]) return;
              const asset = picked.assets[0];
              setFiles((current) => ({
                ...current,
                [attachment.name]: { uri: asset.uri, name: asset.name, mimeType: asset.mimeType },
              }));
            }}
          >
            <Text style={styles.secondaryText}>{files[attachment.name]?.name || attachment.label}</Text>
          </Pressable>
        ))}
        {error ? <Text style={styles.error}>{error}</Text> : null}
        {!creating && can(user, resource.permissionDelete, resource.adminOnly) ? (
          <Pressable onPress={remove}>
            <Text style={styles.delete}>Delete {resource.singular.toLowerCase()}</Text>
          </Pressable>
        ) : null}
        {!creating && resource.lineItems ? (
          <View style={{ gap: 10 }}>
            <Text style={styles.section}>Line items</Text>
            {(record?.items || []).map((line: any) => (
              <View key={line.id} style={styles.line}>
                <View style={{ flex: 1 }}>
                  <Text style={styles.lineTitle}>{line.description}</Text>
                  <Text style={styles.summarySub}>
                    {money(line.amount)} · discount {money(line.discount)} · tax {money(line.tax)}
                  </Text>
                </View>
                <Pressable
                  onPress={async () => {
                    await api(`${resource.endpoint}/${params.id}/items/${line.id}`, { method: 'DELETE' });
                    setRecord(await api(`${resource.endpoint}/${params.id}`));
                  }}
                >
                  <Text style={styles.delete}>Remove</Text>
                </Pressable>
              </View>
            ))}
            {allowed ? (
              <View style={{ gap: 8 }}>
                {(['description', 'amount', 'discount', 'tax'] as const).map((key) => (
                  <TextInput
                    key={key}
                    value={item[key]}
                    onChangeText={(text) => setItem((current) => ({ ...current, [key]: text }))}
                    placeholder={labelize(key)}
                    placeholderTextColor={colors.muted}
                    keyboardType={key === 'description' ? 'default' : 'decimal-pad'}
                    style={styles.input}
                  />
                ))}
                <Pressable style={styles.secondary} onPress={addItem}>
                  <Text style={styles.secondaryText}>Add line</Text>
                </Pressable>
              </View>
            ) : null}
          </View>
        ) : null}
      </ScrollView>
      </FormScreen>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.paper },
  fill: { flex: 1 },
  body: { padding: 16, gap: 16, paddingBottom: 24 },
  summary: { backgroundColor: colors.teal, borderRadius: 18, padding: 18, gap: 4 },
  summaryTitle: { color: colors.white, fontSize: 22, fontWeight: '700' },
  summarySub: { color: '#D5E6E4' },
  summaryAmount: { color: colors.white, fontSize: 20, fontWeight: '700', marginTop: 8 },
  link: { color: colors.white, fontWeight: '700', marginTop: 8 },
  primary: { backgroundColor: colors.copper, borderRadius: 14, padding: 16, alignItems: 'center' },
  primaryText: { color: colors.white, fontWeight: '700', fontSize: 16 },
  secondary: {
    backgroundColor: colors.card,
    borderRadius: 14,
    padding: 14,
    alignItems: 'center',
    borderWidth: 1,
    borderColor: colors.line,
  },
  secondaryText: { color: colors.ink, fontWeight: '700' },
  delete: { color: colors.danger, textAlign: 'center', fontWeight: '700' },
  error: { color: colors.danger },
  section: { fontSize: 18, fontWeight: '700', color: colors.ink, marginTop: 8 },
  line: {
    backgroundColor: colors.card,
    borderRadius: 12,
    padding: 12,
    flexDirection: 'row',
    gap: 8,
    alignItems: 'center',
  },
  lineTitle: { fontWeight: '700', color: colors.ink },
  input: {
    backgroundColor: colors.card,
    borderWidth: 1,
    borderColor: colors.line,
    borderRadius: 12,
    paddingHorizontal: 14,
    paddingVertical: 12,
    color: colors.ink,
  },
});
