import React, { useMemo, useState } from 'react';
import { FlatList, Modal, Pressable, StyleSheet, Switch, Text, TextInput, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { asOptions, Option } from '../api';
import { Catalog } from '../catalog';
import { FieldDef, FormValues } from '../resources';
import { colors } from '../theme';

export function emptyValues(fields: FieldDef[], defaults: FormValues = {}): FormValues {
  const values: FormValues = { ...defaults };
  for (const field of fields) {
    if (values[field.name] !== undefined) continue;
    if (field.kind === 'switch') values[field.name] = false;
    else if (field.kind === 'multi') values[field.name] = [];
    else values[field.name] = '';
  }
  return values;
}

export function valuesFromRecord(record: any, fields: FieldDef[], extra: FormValues = {}): FormValues {
  const values = emptyValues(fields);
  for (const field of fields) {
    if (extra[field.name] !== undefined) {
      values[field.name] = extra[field.name];
      continue;
    }
    const raw = record?.[field.name];
    if (field.kind === 'switch') values[field.name] = Boolean(raw);
    else if (field.kind === 'multi') values[field.name] = Array.isArray(raw) ? raw.map(String) : [];
    else if (raw === null || raw === undefined) values[field.name] = '';
    else values[field.name] = String(raw).includes('T') && field.kind === 'date' ? String(raw).slice(0, 10) : String(raw);
  }
  return { ...values, ...extra };
}

export function payloadFromValues(values: FormValues, fields: FieldDef[]): Record<string, unknown> {
  const payload: Record<string, unknown> = {};
  for (const field of fields) {
    if (field.visible && !field.visible(values)) continue;
    const value = values[field.name];
    if (field.kind === 'switch') {
      payload[field.name] = Boolean(value);
      continue;
    }
    if (field.kind === 'multi') {
      payload[field.name] = Array.isArray(value) ? value : [];
      continue;
    }
    if (value === '' || value === undefined) {
      payload[field.name] = null;
      continue;
    }
    payload[field.name] = value;
  }
  return payload;
}

function optionsFor(field: FieldDef, catalog: Catalog, values: FormValues): Option[] {
  if (field.optionsFrom) return field.optionsFrom(catalog, values);
  if (field.optionsKey) return asOptions(catalog[field.optionsKey]);
  return [];
}

export function Fields({
  fields,
  values,
  labels,
  catalog,
  onChange,
  onLabel,
  mode,
}: {
  fields: FieldDef[];
  values: FormValues;
  labels: Record<string, string>;
  catalog: Catalog;
  onChange: (name: string, value: string | boolean | string[]) => void;
  onLabel: (name: string, label: string) => void;
  mode?: 'create' | 'edit';
}) {
  return (
    <View style={{ gap: 14 }}>
      {fields.map((field) => {
        if (field.when && mode && field.when !== mode) return null;
        if (field.visible && !field.visible(values)) return null;
        return (
          <Field
            key={field.name}
            field={field}
            values={values}
            value={values[field.name]}
            label={labels[field.name]}
            options={optionsFor(field, catalog, values)}
            onChange={(value) => onChange(field.name, value)}
            onPickLabel={(label) => onLabel(field.name, label)}
          />
        );
      })}
    </View>
  );
}

function Field({
  field,
  values,
  value,
  label,
  options,
  onChange,
  onPickLabel,
}: {
  field: FieldDef;
  values: FormValues;
  value: string | boolean | string[] | undefined;
  label?: string;
  options: Option[];
  onChange: (value: string | boolean | string[]) => void;
  onPickLabel: (label: string) => void;
}) {
  const insets = useSafeAreaInsets();
  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState('');
  const [remote, setRemote] = useState<Option[]>([]);
  const [searching, setSearching] = useState(false);

  const shown = useMemo(() => {
    const source = field.kind === 'search' ? remote : options;
    const needle = query.trim().toLowerCase();
    if (!needle || field.kind === 'search') return source;
    return source.filter((option) => option.label.toLowerCase().includes(needle) || option.value.toLowerCase().includes(needle));
  }, [field.kind, options, query, remote]);

  const selectedLabel = () => {
    if (field.kind === 'multi') {
      const picked = Array.isArray(value) ? value : [];
      if (!picked.length) return 'Select';
      return picked
        .map((item) => options.find((option) => option.value === item)?.label || item)
        .join(', ');
    }
    if (label) return label;
    const match = options.find((option) => option.value === String(value || ''));
    return match?.label || (value ? String(value) : 'Select');
  };

  const runSearch = async (text: string) => {
    setQuery(text);
    if (field.kind !== 'search' || !field.search) return;
    setSearching(true);
    try {
      setRemote(await field.search(text, values));
    } catch {
      setRemote([]);
    } finally {
      setSearching(false);
    }
  };

  if (field.kind === 'switch') {
    return (
      <View style={styles.switchRow}>
        <Text style={styles.label}>{field.label}</Text>
        <Switch value={Boolean(value)} onValueChange={(next) => onChange(next)} trackColor={{ true: colors.teal }} />
      </View>
    );
  }

  if (field.kind === 'text' || field.kind === 'textarea' || field.kind === 'number' || field.kind === 'date') {
    return (
      <View>
        <Text style={styles.label}>
          {field.label}
          {field.required ? ' *' : ''}
        </Text>
        <TextInput
          value={typeof value === 'string' ? value : ''}
          onChangeText={(text) => onChange(text)}
          placeholder={field.kind === 'date' ? 'YYYY-MM-DD' : field.label}
          placeholderTextColor={colors.muted}
          secureTextEntry={field.secure}
          keyboardType={field.kind === 'number' ? 'decimal-pad' : field.kind === 'date' ? 'numbers-and-punctuation' : 'default'}
          multiline={field.kind === 'textarea'}
          style={[styles.input, field.kind === 'textarea' && styles.area]}
        />
      </View>
    );
  }

  return (
    <View>
      <Text style={styles.label}>
        {field.label}
        {field.required ? ' *' : ''}
      </Text>
      <Pressable
        style={styles.input}
        onPress={() => {
          setOpen(true);
          if (field.kind === 'search') runSearch('');
        }}
      >
        <Text style={{ color: value && (Array.isArray(value) ? value.length : true) ? colors.ink : colors.muted }} numberOfLines={2}>
          {selectedLabel()}
        </Text>
      </Pressable>
      <Modal visible={open} animationType="slide" onRequestClose={() => setOpen(false)}>
        <View style={[styles.modal, { paddingTop: insets.top + 12, paddingBottom: insets.bottom + 12 }]}>
          <View style={styles.modalHead}>
            <Text style={styles.modalTitle}>{field.label}</Text>
            <Pressable onPress={() => setOpen(false)}>
              <Text style={{ fontSize: 22, color: colors.ink }}>×</Text>
            </Pressable>
          </View>
          <TextInput
            value={query}
            onChangeText={runSearch}
            placeholder="Search"
            placeholderTextColor={colors.muted}
            style={styles.input}
            autoFocus
          />
          <FlatList
            data={shown}
            keyExtractor={(item) => item.value}
            ListEmptyComponent={<Text style={styles.empty}>{searching ? 'Searching…' : 'Nothing matches'}</Text>}
            renderItem={({ item }) => {
              const picked = field.kind === 'multi' ? Array.isArray(value) && value.includes(item.value) : String(value || '') === item.value;
              return (
                <Pressable
                  style={styles.option}
                  onPress={() => {
                    if (field.kind === 'multi') {
                      const current = Array.isArray(value) ? value : [];
                      onChange(picked ? current.filter((entry) => entry !== item.value) : [...current, item.value]);
                      return;
                    }
                    onChange(item.value);
                    onPickLabel(item.label);
                    setOpen(false);
                  }}
                >
                  <Text style={styles.optionText}>{item.label}</Text>
                  {picked ? <Text style={{ color: colors.teal, fontWeight: '700' }}>✓</Text> : null}
                </Pressable>
              );
            }}
          />
          {field.kind === 'multi' ? (
            <Pressable style={styles.done} onPress={() => setOpen(false)}>
              <Text style={styles.doneText}>Done</Text>
            </Pressable>
          ) : null}
        </View>
      </Modal>
    </View>
  );
}

const styles = StyleSheet.create({
  label: { fontSize: 13, fontWeight: '600', color: colors.muted, marginBottom: 6 },
  input: {
    backgroundColor: colors.card,
    borderWidth: 1,
    borderColor: colors.line,
    borderRadius: 14,
    paddingHorizontal: 16,
    paddingVertical: 14,
    minHeight: 52,
    color: colors.ink,
    fontSize: 17,
    justifyContent: 'center',
  },
  area: { minHeight: 96, textAlignVertical: 'top' },
  switchRow: {
    backgroundColor: colors.card,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: colors.line,
    paddingHorizontal: 14,
    paddingVertical: 10,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  modal: { flex: 1, backgroundColor: colors.paper, padding: 20, paddingTop: 56 },
  modalHead: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 },
  modalTitle: { fontSize: 22, fontWeight: '700', color: colors.ink },
  option: {
    minHeight: 52,
    paddingVertical: 14,
    borderBottomWidth: 1,
    borderBottomColor: colors.line,
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  optionText: { color: colors.ink, fontSize: 16, flex: 1, paddingRight: 12 },
  empty: { color: colors.muted, paddingVertical: 24 },
  done: { backgroundColor: colors.teal, borderRadius: 12, padding: 14, alignItems: 'center', marginTop: 8 },
  doneText: { color: colors.white, fontWeight: '700' },
});
