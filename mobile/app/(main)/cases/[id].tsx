import { router, useLocalSearchParams } from 'expo-router';
import React, { useEffect, useState } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, TextInput, View } from 'react-native';
import { api, asOptions, lookup } from '../../../src/api';
import { can, useAuth } from '../../../src/auth';
import { useCatalog } from '../../../src/catalog';
import { Fields } from '../../../src/components/Fields';
import { ScreenHeader, StatusBadge, useFullScreen } from '../../../src/components/ui';
import { colors, labelize, prettyDate, statusColor } from '../../../src/theme';

const sections = [
  ['overview', 'Case'],
  ['bills', 'Bills'],
  ['invoices', 'Invoices'],
  ['gops', 'GOPs'],
  ['medicalReports', 'Reports'],
  ['prescriptions', 'Rx'],
  ['comments', 'Notes'],
  ['tasks', 'Tasks'],
  ['appointments', 'Visits'],
  ['fileAssignments', 'Team'],
  ['activityLogs', 'Activity'],
  ['bankAccounts', 'Banks'],
] as const;

const hiddenKeys = new Set(['id', 'password', 'remember_token', 'created_at', 'updated_at', 'deleted_at']);

function facts(row: any) {
  return Object.entries(row || {}).filter(([key, value]) => {
    if (hiddenKeys.has(key) || key.endsWith('_id')) return false;
    if (value === null || value === undefined || value === '') return false;
    return typeof value !== 'object';
  });
}

function rowTitle(row: any) {
  return row.title || row.name || row.content || row.reference || row.invoice_number || row.bill_number || row.mga_reference || `#${row.id}`;
}

export default function CaseScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const { user } = useAuth();
  const { catalog } = useCatalog();
  useFullScreen();
  const [file, setFile] = useState<any>(null);
  const [section, setSection] = useState<(typeof sections)[number][0]>('overview');
  const [error, setError] = useState('');
  const [comment, setComment] = useState('');
  const [taskTitle, setTaskTitle] = useState('');
  const [taskUser, setTaskUser] = useState('');
  const [taskDepartment, setTaskDepartment] = useState('Operation');
  const [assignee, setAssignee] = useState('');
  const [visit, setVisit] = useState({ provider_branch_id: '', service_date: '', service_time: '' });
  const [visitLabel, setVisitLabel] = useState('');
  const [panel, setPanel] = useState<'' | 'status' | 'assign' | 'visit'>('');
  const [showAll, setShowAll] = useState(false);

  const load = async () => {
    try {
      setFile(await api(`/files/${id}`));
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Could not open this case');
    }
  };

  useEffect(() => {
    load();
  }, [id]);

  const patchStatus = async (status: string) => {
    await api(`/files/${id}`, { method: 'PATCH', json: { status } });
    setPanel('');
    await load();
  };

  if (!file) {
    return (
      <View style={styles.screen}>
        <ScreenHeader title="Case" back />
        <Text style={styles.muted}>{error || 'Loading…'}</Text>
      </View>
    );
  }

  const rows = Array.isArray(file[section]) ? file[section] : [];
  const editable = can(user, 'edit File');
  const summary = [
    ['Client', file.patient?.client?.company_name],
    ['Service', file.serviceType?.name || file.service_type?.name],
    ['City', file.city?.name],
    ['Date', prettyDate(file.service_date)],
    ['Time', file.service_time],
    ['Phone', file.phone],
    ['Email', file.email],
    ['Client ref', file.client_reference],
    ['Provider', [file.providerBranch?.provider?.name || file.provider_branch?.provider?.name, file.providerBranch?.branch_name || file.provider_branch?.branch_name].filter(Boolean).join(' · ')],
    ['Assigned', file.currentAssignment?.user?.name || file.current_assignment?.user?.name],
  ].filter(([, value]) => value);

  return (
    <View style={styles.screen}>
      <ScreenHeader title={file.mga_reference || 'Case'} back />
      <View style={styles.hero}>
        <View style={{ flex: 1, gap: 4 }}>
          <Text style={styles.heroTitle}>{file.patient?.name || 'No patient'}</Text>
          <Text style={styles.heroSub}>{[file.patient?.client?.company_name, file.city?.name].filter(Boolean).join(' · ') || 'Open the case details below'}</Text>
        </View>
        <StatusBadge status={file.status} />
      </View>
      <ScrollView horizontal showsHorizontalScrollIndicator={false} style={styles.row} contentContainerStyle={styles.tabs}>
        {sections.map(([key, label]) => (
          <Pressable key={key} onPress={() => setSection(key)} style={[styles.tab, section === key && styles.tabOn]}>
            <Text style={[styles.tabText, section === key && styles.tabTextOn]}>{label}</Text>
          </Pressable>
        ))}
      </ScrollView>
      <ScrollView style={styles.fill} keyboardShouldPersistTaps="handled" contentContainerStyle={styles.body}>
        {error ? <Text style={styles.error}>{error}</Text> : null}

        {section === 'overview' ? (
          <View style={{ gap: 12 }}>
            <View style={styles.grid}>
              {summary.map(([label, value]) => (
                <View key={label} style={styles.fact}>
                  <Text style={styles.factLabel}>{label}</Text>
                  <Text style={styles.factValue}>{String(value)}</Text>
                </View>
              ))}
            </View>
            {file.symptoms ? (
              <View style={styles.card}>
                <Text style={styles.factLabel}>Symptoms</Text>
                <Text style={styles.factValue}>{file.symptoms}</Text>
              </View>
            ) : null}
            {file.diagnosis ? (
              <View style={styles.card}>
                <Text style={styles.factLabel}>Diagnosis</Text>
                <Text style={styles.factValue}>{file.diagnosis}</Text>
              </View>
            ) : null}
            {editable ? (
              <View style={styles.actions}>
                <Pressable style={styles.secondary} onPress={() => router.push(`/cases/edit?id=${file.id}`)}>
                  <Text style={styles.secondaryText}>Edit</Text>
                </Pressable>
                {(['status', 'assign', 'visit'] as const).map((key) => (
                  <Pressable key={key} style={[styles.secondary, panel === key && styles.secondaryOn]} onPress={() => setPanel(panel === key ? '' : key)}>
                    <Text style={[styles.secondaryText, panel === key && { color: colors.white }]}>{key === 'visit' ? 'Visit' : key === 'assign' ? 'Assign' : 'Status'}</Text>
                  </Pressable>
                ))}
              </View>
            ) : null}
            {panel === 'status' ? (
              <View style={styles.wrap}>
                {asOptions(catalog.file_statuses).map((option) => (
                  <Pressable key={option.value} onPress={() => patchStatus(option.value).catch((err) => setError(err.message))} style={[styles.chip, file.status === option.value && { backgroundColor: statusColor(option.value) }]}>
                    <Text style={{ color: file.status === option.value ? colors.white : colors.ink, fontWeight: '700' }}>{option.label}</Text>
                  </Pressable>
                ))}
              </View>
            ) : null}
            {panel === 'assign' ? (
              <View style={{ gap: 10 }}>
                <Fields
                  fields={[{ name: 'user_id', label: 'Staff member', kind: 'select', optionsFrom: (list) => (list.users || []).map((row: any) => ({ label: row.name, value: String(row.id) })) }]}
                  values={{ user_id: assignee }}
                  labels={{}}
                  catalog={catalog}
                  onChange={(_, value) => setAssignee(String(value))}
                  onLabel={() => undefined}
                />
                <Pressable
                  style={styles.primary}
                  onPress={async () => {
                    try {
                      await api(`/files/${id}/assign`, { method: 'POST', json: { user_id: Number(assignee) } });
                      setPanel('');
                      await load();
                    } catch (err) {
                      setError(err instanceof Error ? err.message : 'Could not assign');
                    }
                  }}
                >
                  <Text style={styles.primaryText}>Assign case</Text>
                </Pressable>
              </View>
            ) : null}
            {panel === 'visit' ? (
              <View style={{ gap: 10 }}>
                <Fields
                  fields={[
                    {
                      name: 'provider_branch_id',
                      label: 'Branch',
                      kind: 'search',
                      search: (query) =>
                        lookup(
                          '/provider-branches',
                          (row) => [row.branch_name, row.provider?.name].filter(Boolean).join(' · '),
                          query,
                          `&city_id=${file.city_id || ''}&service_type_id=${file.service_type_id || ''}`,
                        ),
                    },
                    { name: 'service_date', label: 'Date', kind: 'date' },
                    { name: 'service_time', label: 'Time', kind: 'text' },
                  ]}
                  values={visit}
                  labels={{ provider_branch_id: visitLabel }}
                  catalog={catalog}
                  onChange={(name, value) => setVisit((current) => ({ ...current, [name]: String(value) }))}
                  onLabel={(_, label) => setVisitLabel(label)}
                />
                <Pressable
                  style={styles.primary}
                  onPress={async () => {
                    try {
                      await api(`/files/${id}/request-appointment`, { method: 'POST', json: visit });
                      await load();
                      setPanel('');
                      setSection('appointments');
                    } catch (err) {
                      setError(err instanceof Error ? err.message : 'Could not request the appointment');
                    }
                  }}
                >
                  <Text style={styles.primaryText}>Request appointment</Text>
                </Pressable>
              </View>
            ) : null}
            <Pressable onPress={() => setShowAll((current) => !current)}>
              <Text style={styles.link}>{showAll ? 'Hide extra details' : 'All case details'}</Text>
            </Pressable>
            {showAll
              ? facts(file).map(([key, value]) => (
                  <View key={key} style={styles.factLine}>
                    <Text style={styles.factLabel}>{labelize(key)}</Text>
                    <Text style={styles.factValue}>{String(value)}</Text>
                  </View>
                ))
              : null}
          </View>
        ) : null}

        {section === 'comments' && editable ? (
          <View style={{ gap: 8 }}>
            <TextInput value={comment} onChangeText={setComment} placeholder="Add a note" placeholderTextColor={colors.muted} style={styles.input} multiline />
            <Pressable
              style={styles.primary}
              onPress={async () => {
                try {
                  await api(`/files/${id}/comments`, { method: 'POST', json: { content: comment } });
                  setComment('');
                  await load();
                } catch (err) {
                  setError(err instanceof Error ? err.message : 'Could not save the note');
                }
              }}
            >
              <Text style={styles.primaryText}>Add note</Text>
            </Pressable>
          </View>
        ) : null}

        {section === 'tasks' && editable ? (
          <View style={{ gap: 8 }}>
            <TextInput value={taskTitle} onChangeText={setTaskTitle} placeholder="Task title" placeholderTextColor={colors.muted} style={styles.input} />
            <Fields
              fields={[
                { name: 'user_id', label: 'Assign to', kind: 'select', optionsFrom: (list) => (list.users || []).map((row: any) => ({ label: row.name, value: String(row.id) })) },
                { name: 'department', label: 'Department', kind: 'select', optionsKey: 'task_departments' },
              ]}
              values={{ user_id: taskUser, department: taskDepartment }}
              labels={{}}
              catalog={catalog}
              onChange={(name, value) => {
                if (name === 'user_id') setTaskUser(String(value));
                if (name === 'department') setTaskDepartment(String(value));
              }}
              onLabel={() => undefined}
            />
            <Pressable
              style={styles.primary}
              onPress={async () => {
                try {
                  await api(`/files/${id}/tasks`, {
                    method: 'POST',
                    json: { title: taskTitle, user_id: Number(taskUser), department: taskDepartment },
                  });
                  setTaskTitle('');
                  await load();
                } catch (err) {
                  setError(err instanceof Error ? err.message : 'Could not create the task');
                }
              }}
            >
              <Text style={styles.primaryText}>Add task</Text>
            </Pressable>
          </View>
        ) : null}

        {section !== 'overview'
          ? rows.map((row: any) => (
              <Pressable
                key={String(row.id)}
                style={styles.card}
                onPress={() => {
                  if (section === 'bills') router.push(`/finance/bills/${row.id}`);
                  if (section === 'invoices') router.push(`/finance/invoices/${row.id}`);
                  if (section === 'tasks' && editable) {
                    api(`/files/${id}/tasks/${row.id}/toggle`, { method: 'POST', json: {} }).then(load).catch((err) => setError(err.message));
                  }
                }}
              >
                <View style={styles.cardTop}>
                  <Text style={styles.cardTitle} numberOfLines={2}>
                    {rowTitle(row)}
                  </Text>
                  {row.status ? <StatusBadge status={row.status} /> : null}
                </View>
                {facts(row)
                  .slice(0, 3)
                  .map(([key, value]) => (
                    <Text key={key} style={styles.cardMeta} numberOfLines={2}>
                      {labelize(key)}: {String(value)}
                    </Text>
                  ))}
                {row.user?.name ? <Text style={styles.muted}>By {row.user.name}</Text> : null}
                {section === 'tasks' ? <Text style={styles.muted}>{row.is_done ? 'Completed. Tap to reopen.' : 'Tap to complete.'}</Text> : null}
              </Pressable>
            ))
          : null}
        {section !== 'overview' && !rows.length ? <Text style={styles.muted}>Nothing here yet.</Text> : null}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.paper },
  fill: { flex: 1 },
  row: { flexGrow: 0, flexShrink: 0, maxHeight: 52 },
  hero: {
    marginHorizontal: 16,
    marginBottom: 8,
    backgroundColor: colors.card,
    borderRadius: 18,
    padding: 16,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    borderWidth: 1,
    borderColor: colors.line,
  },
  heroTitle: { color: colors.ink, fontSize: 22, fontWeight: '700' },
  heroSub: { color: colors.muted, fontSize: 14 },
  tabs: { paddingHorizontal: 16, gap: 8, alignItems: 'center' },
  tab: { paddingHorizontal: 14, minHeight: 36, justifyContent: 'center', borderRadius: 999, backgroundColor: colors.card, borderWidth: 1, borderColor: colors.line },
  tabOn: { backgroundColor: colors.teal, borderColor: colors.teal },
  tabText: { color: colors.ink, fontWeight: '700' },
  tabTextOn: { color: colors.white },
  body: { padding: 16, gap: 12, paddingBottom: 40 },
  grid: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  fact: { flexGrow: 1, flexBasis: '46%', backgroundColor: colors.card, borderRadius: 14, padding: 12, borderWidth: 1, borderColor: colors.line, gap: 4 },
  factLine: { gap: 2 },
  factLabel: { color: colors.muted, fontSize: 12, fontWeight: '700' },
  factValue: { color: colors.ink, fontSize: 16 },
  actions: { flexDirection: 'row', gap: 8 },
  wrap: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  chip: { paddingHorizontal: 12, minHeight: 40, justifyContent: 'center', borderRadius: 999, backgroundColor: colors.card },
  card: { backgroundColor: colors.card, borderRadius: 16, padding: 14, gap: 6, borderWidth: 1, borderColor: colors.line },
  cardTop: { flexDirection: 'row', justifyContent: 'space-between', gap: 8, alignItems: 'flex-start' },
  cardTitle: { flex: 1, fontWeight: '700', color: colors.ink, fontSize: 16 },
  cardMeta: { color: colors.muted, fontSize: 14 },
  primary: { backgroundColor: colors.teal, borderRadius: 14, minHeight: 50, alignItems: 'center', justifyContent: 'center' },
  primaryText: { color: colors.white, fontWeight: '700', fontSize: 16 },
  secondary: { flex: 1, backgroundColor: colors.card, borderRadius: 14, minHeight: 44, alignItems: 'center', justifyContent: 'center', borderWidth: 1, borderColor: colors.line },
  secondaryOn: { backgroundColor: colors.teal, borderColor: colors.teal },
  secondaryText: { color: colors.ink, fontWeight: '700' },
  input: { backgroundColor: colors.card, borderRadius: 14, borderWidth: 1, borderColor: colors.line, padding: 14, color: colors.ink, minHeight: 52, fontSize: 16 },
  muted: { color: colors.muted, paddingVertical: 8 },
  error: { color: colors.danger },
  link: { color: colors.teal, fontWeight: '700', paddingVertical: 4 },
});
