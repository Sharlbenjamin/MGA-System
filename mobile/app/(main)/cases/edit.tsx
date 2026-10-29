import { router, useLocalSearchParams } from 'expo-router';
import React, { useEffect, useState } from 'react';
import { Pressable, ScrollView, StyleSheet, Switch, Text, View } from 'react-native';
import { api, lookup } from '../../../src/api';
import { useCatalog } from '../../../src/catalog';
import { emptyValues, Fields } from '../../../src/components/Fields';
import { FormScreen, ScreenHeader, useFullScreen } from '../../../src/components/ui';
import { FieldDef, FormValues } from '../../../src/resources';
import { colors } from '../../../src/theme';

const fields: FieldDef[] = [
  { name: 'mga_reference', label: 'MGA reference', kind: 'text', required: true },
  { name: 'service_type_id', label: 'Service type', kind: 'select', required: true, optionsKey: 'service_types' },
  { name: 'client_reference', label: 'Client reference', kind: 'text' },
  { name: 'country_id', label: 'Country', kind: 'select', optionsKey: 'countries' },
  { name: 'city_id', label: 'City', kind: 'search', search: (query, values) => lookup('/cities', (row) => row.name, query, values.country_id ? `&country_id=${values.country_id}` : '') },
  {
    name: 'provider_branch_id',
    label: 'Provider branch',
    kind: 'search',
    search: (query, values) =>
      lookup(
        '/provider-branches',
        (row) => [row.branch_name, row.provider?.name].filter(Boolean).join(' · '),
        query,
        `${values.city_id ? `&city_id=${values.city_id}` : ''}${values.service_type_id ? `&service_type_id=${values.service_type_id}` : ''}`,
      ),
  },
  { name: 'status', label: 'Status', kind: 'select', required: true, optionsKey: 'file_statuses' },
  { name: 'service_date', label: 'Service date', kind: 'date' },
  { name: 'service_time', label: 'Service time', kind: 'text' },
  { name: 'email', label: 'Email', kind: 'text' },
  { name: 'phone', label: 'Phone', kind: 'text' },
  { name: 'address', label: 'Address', kind: 'text' },
  { name: 'symptoms', label: 'Symptoms', kind: 'textarea' },
  { name: 'diagnosis', label: 'Diagnosis', kind: 'textarea' },
];

export default function CaseEditScreen() {
  const params = useLocalSearchParams<{ id?: string }>();
  const editing = Boolean(params.id);
  const { catalog } = useCatalog();
  useFullScreen();
  const [newPatient, setNewPatient] = useState(false);
  const [patientLabel, setPatientLabel] = useState('');
  const [clientLabel, setClientLabel] = useState('');
  const [labels, setLabels] = useState<Record<string, string>>({});
  const [values, setValues] = useState<FormValues>(emptyValues(fields, { status: 'New' }));
  const [patientId, setPatientId] = useState('');
  const [clientId, setClientId] = useState('');
  const [patientName, setPatientName] = useState('');
  const [dob, setDob] = useState('');
  const [gender, setGender] = useState('');
  const [error, setError] = useState('');
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    if (!params.id) return;
    api<any>(`/files/${params.id}`).then((file) => {
      const next = emptyValues(fields, { status: 'New' });
      for (const field of fields) {
        const raw = file[field.name];
        next[field.name] = raw ? (field.kind === 'date' ? String(raw).slice(0, 10) : String(raw)) : '';
      }
      setValues(next);
      setPatientId(String(file.patient_id || ''));
      setPatientLabel(file.patient?.name || '');
      setLabels({
        city_id: file.city?.name || '',
        provider_branch_id: file.provider_branch?.branch_name || file.providerBranch?.branch_name || '',
      });
    });
  }, [params.id]);

  const refreshReference = async (nextPatientId?: string, nextClientId?: string) => {
    if (editing) return;
    const query = nextPatientId ? `patient_id=${nextPatientId}` : nextClientId ? `client_id=${nextClientId}` : '';
    if (!query) return;
    const result = await api<{ mga_reference: string }>(`/files/next-reference?${query}`);
    setValues((current) => ({ ...current, mga_reference: result.mga_reference }));
  };

  const save = async () => {
    setSaving(true);
    setError('');
    try {
      let nextPatientId = patientId;
      if (!editing && newPatient) {
        const patient = await api<any>('/patients', {
          method: 'POST',
          json: {
            name: patientName,
            client_id: clientId,
            dob: dob || null,
            gender: gender || null,
          },
        });
        nextPatientId = String(patient.id);
      }
      if (!editing && !nextPatientId) {
        throw new Error('Choose a patient or create one.');
      }
      const payload: Record<string, unknown> = { patient_id: nextPatientId ? Number(nextPatientId) : undefined };
      for (const field of fields) {
        const value = values[field.name];
        payload[field.name] = value === '' ? null : value;
      }
      if (editing) {
        delete payload.patient_id;
        delete payload.mga_reference;
        await api(`/files/${params.id}`, { method: 'PATCH', json: payload });
      } else {
        await api('/files', { method: 'POST', json: payload });
      }
      router.back();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Could not save the case');
    } finally {
      setSaving(false);
    }
  };

  return (
    <View style={styles.screen}>
      <FormScreen
        footer={
          <Pressable style={styles.save} onPress={save} disabled={saving}>
            <Text style={styles.saveText}>{saving ? 'Saving…' : 'Save case'}</Text>
          </Pressable>
        }
      >
      <ScreenHeader title={editing ? 'Edit case' : 'New case'} back />
      <ScrollView style={styles.fill} keyboardShouldPersistTaps="handled" contentContainerStyle={styles.body}>
        {!editing ? (
          <View style={styles.card}>
            <View style={styles.row}>
              <Text style={styles.label}>Create a new patient</Text>
              <Switch value={newPatient} onValueChange={setNewPatient} trackColor={{ true: colors.teal }} />
            </View>
            {newPatient ? (
              <Fields
                fields={[
                  { name: 'patient_name', label: 'Patient name', kind: 'text', required: true },
                  { name: 'client_id', label: 'Client', kind: 'search', required: true, search: (q) => lookup('/clients', (row) => row.company_name, q) },
                  { name: 'dob', label: 'Date of birth', kind: 'date' },
                  { name: 'gender', label: 'Gender', kind: 'select', optionsKey: 'patient_genders' },
                ]}
                values={{ patient_name: patientName, client_id: clientId, dob, gender }}
                labels={{ client_id: clientLabel }}
                catalog={catalog}
                onChange={(name, value) => {
                  const text = String(value);
                  if (name === 'patient_name') setPatientName(text);
                  if (name === 'client_id') {
                    setClientId(text);
                    refreshReference(undefined, text).catch(() => undefined);
                  }
                  if (name === 'dob') setDob(text);
                  if (name === 'gender') setGender(text);
                }}
                onLabel={(name, label) => {
                  if (name === 'client_id') setClientLabel(label);
                }}
              />
            ) : (
              <Fields
                fields={[{ name: 'patient_id', label: 'Patient', kind: 'search', required: true, search: (q) => lookup('/patients', (row) => [row.name, row.client?.company_name].filter(Boolean).join(' · '), q) }]}
                values={{ patient_id: patientId }}
                labels={{ patient_id: patientLabel }}
                catalog={catalog}
                onChange={(_, value) => {
                  const id = String(value);
                  setPatientId(id);
                  refreshReference(id).catch(() => undefined);
                }}
                onLabel={(_, label) => setPatientLabel(label)}
              />
            )}
          </View>
        ) : null}
        <Fields
          fields={fields.filter((field) => (editing ? field.name !== 'mga_reference' : true))}
          values={values}
          labels={labels}
          catalog={catalog}
          onChange={(name, value) => setValues((current) => ({ ...current, [name]: value }))}
          onLabel={(name, label) => setLabels((current) => ({ ...current, [name]: label }))}
        />
        {error ? <Text style={styles.error}>{error}</Text> : null}
      </ScrollView>
      </FormScreen>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.paper },
  fill: { flex: 1 },
  body: { padding: 16, gap: 16, paddingBottom: 24 },
  card: { backgroundColor: colors.card, borderRadius: 16, padding: 14, gap: 12, borderWidth: 1, borderColor: colors.line },
  row: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  label: { fontWeight: '700', color: colors.ink },
  save: { backgroundColor: colors.copper, borderRadius: 14, padding: 16, alignItems: 'center' },
  saveText: { color: colors.white, fontWeight: '700', fontSize: 16 },
  error: { color: colors.danger },
});
