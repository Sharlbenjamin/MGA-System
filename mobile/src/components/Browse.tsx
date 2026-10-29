import { router, useFocusEffect } from 'expo-router';
import React, { useCallback, useState } from 'react';
import { ResourceConfig } from '../resources';
import { applyListDefaults, listDefaultsActive } from '../list-config';
import { ActivityIndicator, FlatList, Pressable, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { api, asOptions, unwrapPage } from '../api';
import { can, useAuth } from '../auth';
import { useCatalog } from '../catalog';
import { colors, statusColor } from '../theme';
import { Pill, ScreenHeader, SearchField, StatusBadge } from './ui';
import { StatWidgets } from './Widgets';
import { appendMobileListParams, MOBILE_LIST_PAGE_SIZE } from '../list-config';
import { ListLoadMore } from '../list-load-more';
import { loadOverviewWidgets, loadWidgetCounts, widgetIsActive, WidgetCount } from '../widgets';

function initialFilters(config: ResourceConfig) {
  return {
    status: config.listDefaults?.status ?? '',
    extra: { ...(config.listDefaults?.extra ?? {}) },
  };
}

export function Browse({ resources, base }: { resources: ResourceConfig[]; base: string }) {
  const { user } = useAuth();
  const { catalog } = useCatalog();
  const [index, setIndex] = useState(0);
  const resource = resources[index];
  const [search, setSearch] = useState('');
  const [status, setStatus] = useState(() => initialFilters(resources[0]).status);
  const [extra, setExtra] = useState<Record<string, string>>(() => initialFilters(resources[0]).extra);
  const [rows, setRows] = useState<any[]>([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [widgetCounts, setWidgetCounts] = useState<Record<string, number>>({});
  const [overviewWidgets, setOverviewWidgets] = useState<WidgetCount[]>([]);

  const allowed = can(user, resource.permissionView, resource.adminOnly);

  const load = useCallback(
    async (nextPage = 1, append = false) => {
      if (!allowed) {
        setRows([]);
        setLoading(false);
        return;
      }
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
        applyListDefaults(params, resource.listDefaults, status, extra, search);
        const result = unwrapPage<any>(await api(`${resource.endpoint}?${params.toString()}`));
        setRows((current) => (append ? [...current, ...result.rows] : result.rows));
        setPage(result.page);
        setLastPage(result.lastPage);
      } catch (err) {
        setError(err instanceof Error ? err.message : 'Could not load records');
      } finally {
        setLoading(false);
      }
    },
    [allowed, extra, resource.endpoint, resource.listDefaults, search, status],
  );

  useFocusEffect(
    useCallback(() => {
      load(1, false);
    }, [load]),
  );

  useFocusEffect(
    useCallback(() => {
      const specs = resource.widgets;
      if (!allowed || (!specs?.length && !resource.overviewEndpoint)) {
        setWidgetCounts({});
        setOverviewWidgets([]);
        return;
      }
      let active = true;
      const timer = setTimeout(() => {
        if (!active) return;
        if (resource.overviewEndpoint) {
          loadOverviewWidgets(resource.overviewEndpoint)
            .then((items) => {
              if (active) setOverviewWidgets(items);
            })
            .catch(() => undefined);
          return;
        }
        loadWidgetCounts(resource.endpoint, specs || [])
        .then((counts) => {
          if (active) setWidgetCounts(counts);
        })
        .catch(() => undefined);
      }, 800);
      return () => {
        active = false;
        clearTimeout(timer);
      };
    }, [allowed, resource.endpoint, resource.overviewEndpoint, resource.widgets]),
  );

  const statuses = resource.statusesKey ? asOptions(catalog[resource.statusesKey]) : [];
  const canCreate = can(user, resource.permissionCreate, resource.adminOnly);
  const activeWidget = resource.widgets?.find((spec) => widgetIsActive(spec, status, extra))?.key;
  const widgetItems: WidgetCount[] = resource.overviewEndpoint
    ? overviewWidgets
    : (resource.widgets || []).flatMap((spec) =>
        widgetCounts[spec.key] === undefined ? [] : [{ key: spec.key, label: spec.label, count: widgetCounts[spec.key] }],
      );

  return (
    <View style={styles.screen}>
      <ScreenHeader title={resource.plural} />
      <StatWidgets
        items={widgetItems}
        activeKey={activeWidget}
        onPress={(key) => {
          const spec = resource.widgets?.find((item) => item.key === key) || overviewWidgets.find((item) => item.key === key);
          if (!spec) return;
          if (widgetIsActive(spec as any, status, extra)) {
            setStatus('');
            setExtra({});
            return;
          }
          const nextExtra = 'extra' in spec && spec.extra ? { ...spec.extra } : {};
          const nextStatus = ('status' in spec && spec.status) || nextExtra.status || '';
          delete nextExtra.status;
          setStatus(nextStatus);
          setExtra(nextExtra);
        }}
      />
      {resources.length > 1 ? (
        <ScrollView horizontal showsHorizontalScrollIndicator={false} style={styles.row} contentContainerStyle={styles.segments}>
          {resources.map((item, itemIndex) => (
            <Pressable
              key={item.key}
              onPress={() => {
                setIndex(itemIndex);
                const next = initialFilters(resources[itemIndex]);
                setStatus(next.status);
                setExtra(next.extra);
                setSearch('');
              }}
              style={[styles.segment, itemIndex === index && styles.segmentOn]}
            >
              <Text style={[styles.segmentText, itemIndex === index && styles.segmentTextOn]}>{item.plural}</Text>
            </Pressable>
          ))}
        </ScrollView>
      ) : null}
      <SearchField value={search} onChangeText={setSearch} onSubmit={() => load(1, false)} placeholder={`Search ${resource.plural.toLowerCase()}`} />
      {statuses.length || resource.listDefaults ? (
        <ScrollView horizontal showsHorizontalScrollIndicator={false} style={styles.row} contentContainerStyle={styles.filters}>
          <Pill
            label={resource.defaultFilterLabel || 'Default'}
            active={listDefaultsActive(resource.listDefaults, status, extra)}
            onPress={() => {
              const next = initialFilters(resource);
              setStatus(next.status);
              setExtra(next.extra);
            }}
          />
          {statuses.map((option) => (
            <Pill
              key={option.value}
              label={option.label}
              color={statusColor(option.value)}
              active={status === option.value}
              onPress={() => {
                const next = status === option.value ? initialFilters(resource).status : option.value;
                setStatus(next);
                if (next !== option.value) {
                  setExtra({ ...(resource.listDefaults?.extra ?? {}) });
                }
              }}
            />
          ))}
        </ScrollView>
      ) : null}
      {resource.extraFilters
        ?.filter((filter) => filter.param !== 'status')
        .map((filter) => (
          <ScrollView key={filter.param} horizontal showsHorizontalScrollIndicator={false} style={styles.row} contentContainerStyle={styles.filters}>
            <Pill label={`All ${filter.label.toLowerCase()}`} active={!extra[filter.param]} onPress={() => setExtra((current) => ({ ...current, [filter.param]: '' }))} />
            {asOptions(catalog[filter.optionsKey]).map((option) => (
              <Pill
                key={option.value}
                label={option.label}
                active={extra[filter.param] === option.value}
                onPress={() => setExtra((current) => ({ ...current, [filter.param]: option.value }))}
              />
            ))}
          </ScrollView>
        ))}
      {!allowed ? (
        <Text style={styles.message}>Your account cannot view {resource.plural.toLowerCase()}.</Text>
      ) : (
        <FlatList
          style={{ flex: 1 }}
          data={rows}
          keyExtractor={(item) => String(item.id)}
          refreshControl={<RefreshControl refreshing={loading && rows.length > 0} onRefresh={() => load(1, false)} tintColor={colors.teal} />}
          contentContainerStyle={{ padding: 20, paddingBottom: 110, gap: 10 }}
          ListFooterComponent={
            <ListLoadMore page={page} lastPage={lastPage} loading={loading && rows.length > 0} onLoadMore={() => load(page + 1, true)} />
          }
          ListEmptyComponent={<Text style={styles.message}>{loading ? 'Loading…' : error || 'No records yet.'}</Text>}
          renderItem={({ item }) => (
            <Pressable style={styles.card} onPress={() => router.push(`${base}/${resource.key}/${item.id}` as any)}>
              <View style={{ flex: 1, gap: 4 }}>
                <Text style={styles.cardTitle}>{resource.title(item)}</Text>
                <Text style={styles.cardSub}>{resource.subtitle(item)}</Text>
              </View>
              <View style={{ alignItems: 'flex-end', gap: 6 }}>
                <StatusBadge status={item.status} />
                {resource.amount?.(item) ? <Text style={styles.amount}>{resource.amount(item)}</Text> : null}
              </View>
            </Pressable>
          )}
        />
      )}
      {error && rows.length ? <Text style={styles.error}>{error}</Text> : null}
      {canCreate ? (
        <Pressable style={styles.fab} onPress={() => router.push(`${base}/${resource.key}/new` as any)}>
          <Text style={{ color: colors.white, fontSize: 32, lineHeight: 34 }}>+</Text>
        </Pressable>
      ) : null}
      {loading && !rows.length ? <ActivityIndicator color={colors.teal} style={{ marginBottom: 24 }} /> : null}
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.paper },
  row: { flexGrow: 0, flexShrink: 0, maxHeight: 56 },
  segments: { paddingHorizontal: 16, gap: 8, alignItems: 'center' },
  segment: { paddingHorizontal: 14, paddingVertical: 8, borderRadius: 999, backgroundColor: colors.card },
  segmentOn: { backgroundColor: colors.teal },
  segmentText: { color: colors.ink, fontWeight: '600' },
  segmentTextOn: { color: colors.white },
  searchWrap: {
    marginHorizontal: 20,
    marginTop: 12,
    backgroundColor: colors.card,
    borderRadius: 14,
    borderWidth: 1,
    borderColor: colors.line,
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 12,
    gap: 8,
  },
  search: { flex: 1, paddingVertical: 12, color: colors.ink, fontSize: 16 },
  filters: { paddingHorizontal: 16, gap: 8, paddingTop: 10, alignItems: 'center' },
  card: {
    backgroundColor: colors.card,
    borderRadius: 16,
    padding: 16,
    flexDirection: 'row',
    gap: 12,
    borderWidth: 1,
    borderColor: colors.line,
  },
  cardTitle: { fontSize: 16, fontWeight: '700', color: colors.ink },
  cardSub: { color: colors.muted, marginTop: 4 },
  status: { fontSize: 12, fontWeight: '700' },
  amount: { fontWeight: '700', color: colors.ink },
  message: { color: colors.muted, padding: 24, textAlign: 'center' },
  error: { color: colors.danger, paddingHorizontal: 20, paddingBottom: 8 },
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
