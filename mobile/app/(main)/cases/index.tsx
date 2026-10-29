import { router, useFocusEffect, useLocalSearchParams } from 'expo-router';
import React, { useCallback, useEffect, useState } from 'react';
import { FlatList, Pressable, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { api, asOptions, unwrapPage } from '../../../src/api';
import { can, useAuth } from '../../../src/auth';
import { useCatalog } from '../../../src/catalog';
import { Pill, ScreenHeader, SearchField, StatusBadge } from '../../../src/components/ui';
import { colors, prettyDate, statusColor } from '../../../src/theme';
import { StatWidgets } from '../../../src/components/Widgets';
import { filterOpenedCases } from '../../../src/case-filters';
import { appendMobileListParams, MOBILE_LIST_PAGE_SIZE } from '../../../src/list-config';
import { ListLoadMore } from '../../../src/list-load-more';
import { fileWidgets, loadOverviewWidgets, widgetIsActive, WidgetCount } from '../../../src/widgets';

const MOBILE_STATUS_PILLS = ['New', 'Available', 'Confirmed', 'Handling', 'Hold'];

export default function CasesScreen() {
  const { user } = useAuth();
  const { catalog } = useCatalog();
  const routeParams = useLocalSearchParams<{ opened?: string; status?: string }>();
  const [search, setSearch] = useState('');
  const [status, setStatus] = useState('');
  const [extra, setExtra] = useState<Record<string, string>>({});
  const [rows, setRows] = useState<any[]>([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [overviewWidgets, setOverviewWidgets] = useState<WidgetCount[]>([]);
  const allowed = can(user, 'view File');

  useEffect(() => {
    const nextStatus = typeof routeParams.status === 'string' ? routeParams.status : '';
    if (nextStatus) {
      setStatus(nextStatus);
      setExtra({});
      return;
    }
    if (routeParams.opened === '1') {
      setStatus('');
      setExtra({});
    }
  }, [routeParams.opened, routeParams.status]);

  const load = useCallback(
    async (nextPage = 1, append = false) => {
      if (!allowed) return;
      setLoading(true);
      setError('');
      try {
        const params = appendMobileListParams(
          new URLSearchParams({
            page: String(nextPage),
            per_page: String(MOBILE_LIST_PAGE_SIZE),
            sort: 'updated_at',
            direction: 'desc',
          }),
        );
        if (search.trim()) params.set('search', search.trim());
        const defaultOpened = !status && extra.without_invoices !== '1';
        if (status) {
          params.set('status', status);
        } else if (defaultOpened) {
          params.set('opened', '1');
        }
        Object.entries(extra).forEach(([key, value]) => {
          if (value) params.set(key, value);
        });
        const result = unwrapPage<any>(await api(`/files?${params.toString()}`));
        let nextRows = defaultOpened && !status ? filterOpenedCases(result.rows) : result.rows;
        setRows((current) => (append ? [...current, ...nextRows] : nextRows));
        setPage(result.page);
        setLastPage(result.lastPage);
      } catch (err) {
        setError(err instanceof Error ? err.message : 'Could not load cases');
      } finally {
        setLoading(false);
      }
    },
    [allowed, extra, search, status],
  );

  useFocusEffect(
    useCallback(() => {
      load(1, false);
    }, [load]),
  );

  useFocusEffect(
    useCallback(() => {
      if (!allowed) return;
      let active = true;
      const timer = setTimeout(() => {
        loadOverviewWidgets('/files/overview')
          .then((items) => {
            if (active) setOverviewWidgets(items);
          })
          .catch(() => undefined);
      }, 800);
      return () => {
        active = false;
        clearTimeout(timer);
      };
    }, [allowed]),
  );

  const activeWidget = fileWidgets.find((spec) => widgetIsActive(spec, status, extra))?.key;
  const opened = !status && extra.without_invoices !== '1';
  const statusPills = asOptions(catalog.file_statuses).filter((option) => MOBILE_STATUS_PILLS.includes(option.value));

  return (
    <View style={styles.screen}>
      <ScreenHeader title="Cases" />
      <StatWidgets
        items={overviewWidgets}
        activeKey={activeWidget}
        onPress={(key) => {
          const spec = fileWidgets.find((item) => item.key === key);
          if (!spec) return;
          if (widgetIsActive(spec, status, extra)) {
            setStatus('');
            setExtra({});
            return;
          }
          const nextExtra = { ...(spec.extra || {}) };
          const nextStatus = spec.status || nextExtra.status || '';
          delete nextExtra.status;
          setStatus(nextStatus);
          setExtra(nextExtra);
        }}
      />
      <SearchField value={search} onChangeText={setSearch} onSubmit={() => load(1)} placeholder="Reference, patient, or client" />
      <ScrollView horizontal showsHorizontalScrollIndicator={false} style={styles.row} contentContainerStyle={styles.filters}>
        <Pill
          label="Opened"
          active={opened}
          onPress={() => {
            setStatus('');
            setExtra({});
          }}
        />
        {statusPills.map((option) => (
          <Pill
            key={option.value}
            label={option.label}
            color={statusColor(option.value)}
            active={status === option.value && extra.without_invoices !== '1'}
            onPress={() => {
              setExtra({});
              setStatus(status === option.value && extra.without_invoices !== '1' ? '' : option.value);
            }}
          />
        ))}
      </ScrollView>
      {!allowed ? (
        <Text style={styles.message}>Your account cannot view cases.</Text>
      ) : (
        <FlatList
          style={{ flex: 1 }}
          data={rows}
          keyExtractor={(item) => String(item.id)}
          refreshControl={<RefreshControl refreshing={loading} onRefresh={() => load(1)} tintColor={colors.teal} />}
          contentContainerStyle={{ padding: 20, gap: 10, paddingBottom: 120 }}
          ListFooterComponent={
            <ListLoadMore page={page} lastPage={lastPage} loading={loading && rows.length > 0} onLoadMore={() => load(page + 1, true)} />
          }
          ListEmptyComponent={<Text style={styles.message}>{loading ? 'Loading…' : error || 'No cases yet.'}</Text>}
          renderItem={({ item }) => {
            return (
              <Pressable style={styles.card} onPress={() => router.push(`/cases/${item.id}`)}>
                <View style={styles.cardTop}>
                  <Text style={styles.ref}>{item.mga_reference || `Case #${item.id}`}</Text>
                  <StatusBadge status={item.status} />
                </View>
                <Text style={styles.patient}>{item.patient?.name || 'No patient'}</Text>
                <Text style={styles.meta}>
                  {[
                    item.patient?.client?.company_name,
                    item.client_reference ? `Ref ${item.client_reference}` : '',
                    item.service_type?.name || item.serviceType?.name,
                    [item.country?.name, item.city?.name].filter(Boolean).join(', '),
                    prettyDate(item.service_date),
                  ]
                    .filter(Boolean)
                    .join(' · ')}
                </Text>
                <Text style={styles.meta}>
                  {[item.provider_branch?.branch_name || item.providerBranch?.branch_name, item.assigned_name || item.current_assignment?.user?.name || 'Unassigned']
                    .filter(Boolean)
                    .join(' · ')}
                </Text>
              </Pressable>
            );
          }}
        />
      )}
      {can(user, 'create File') ? (
        <Pressable style={styles.fab} onPress={() => router.push('/cases/edit')}>
          <Text style={{ color: colors.white, fontSize: 32, lineHeight: 34 }}>+</Text>
        </Pressable>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.paper },
  row: { flexGrow: 0, flexShrink: 0, maxHeight: 56 },
  filters: { paddingHorizontal: 16, gap: 8, paddingTop: 10, alignItems: 'center' },
  card: { backgroundColor: colors.card, borderRadius: 16, padding: 16, borderWidth: 1, borderColor: colors.line, gap: 4 },
  cardTop: { flexDirection: 'row', justifyContent: 'space-between', gap: 8 },
  ref: { fontWeight: '700', color: colors.ink, fontSize: 16, flex: 1 },
  patient: { color: colors.ink, fontSize: 15 },
  meta: { color: colors.muted },
  comment: { color: colors.ink, marginTop: 4 },
  facts: { flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginTop: 6 },
  fact: { color: colors.teal, fontWeight: '700', fontSize: 12 },
  message: { color: colors.muted, textAlign: 'center', padding: 24 },
  fab: {
    position: 'absolute',
    right: 20,
    bottom: 24,
    width: 58,
    height: 58,
    borderRadius: 29,
    backgroundColor: colors.copper,
    alignItems: 'center',
    justifyContent: 'center',
  },
});
