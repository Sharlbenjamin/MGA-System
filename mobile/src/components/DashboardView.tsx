import { router } from 'expo-router';
import React from 'react';
import { ActivityIndicator, Pressable, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { AreaWithLineChart, DualLineChart } from './DashboardCharts';
import { colors, money, radii, shadow } from '../theme';

type Comparison = { description?: string; trend?: string; percentage?: number };

export type DashboardPayload = {
  filters?: { duration?: string; month?: string; year?: number; date?: string };
  hero?: {
    invoiced_total: number;
    billed_total: number;
    difference: number;
    difference_label: string;
    invoiced_comparison?: Comparison;
    billed_comparison?: Comparison;
    difference_comparison?: Comparison;
  } | null;
  kpis?: Array<{ key: string; label: string; value: number; format?: string; route?: string; filter?: string }>;
  charts?: {
    case_volume?: { labels: string[]; values: number[]; period_total?: number; period_comparison?: Comparison };
    invoiced_vs_billed?: { labels: string[]; invoiced: number[]; billed: number[] };
    case_activity?: { labels: string[]; active: number[]; assisted: number[] };
  };
  progress?: Array<{ key: string; label: string; numerator: number; denominator: number }>;
};

function TrendPill({ comparison }: { comparison?: Comparison }) {
  if (!comparison?.description) return null;
  const trend = comparison.trend || 'neutral';
  const bg = trend === 'up' ? '#DCFCE7' : trend === 'down' ? '#FEE2E2' : '#E5E7EB';
  const fg = trend === 'up' ? colors.ok : trend === 'down' ? colors.danger : colors.muted;
  return (
    <View style={[styles.trendPill, { backgroundColor: bg }]}>
      <Text style={[styles.trendPillText, { color: fg }]}>{comparison.description}</Text>
    </View>
  );
}

function BarChart({ labels, values, highlightIndex }: { labels: string[]; values: number[]; highlightIndex?: number }) {
  const max = Math.max(...values, 1);
  const show = labels.slice(-7);
  const vals = values.slice(-7);
  const offset = labels.length - show.length;

  return (
    <View style={styles.barRow}>
      {show.map((label, index) => {
        const value = vals[index] ?? 0;
        const h = Math.max(8, (value / max) * 120);
        const active = highlightIndex === offset + index;
        return (
          <View key={`${label}-${index}`} style={styles.barCol}>
            <View style={[styles.bar, { height: h }, active && styles.barActive]} />
            <Text style={styles.barLabel} numberOfLines={1}>
              {label}
            </Text>
          </View>
        );
      })}
    </View>
  );
}

function ProgressRing({ numerator, denominator, label }: { numerator: number; denominator: number; label: string }) {
  const pct = Math.round((numerator / Math.max(denominator, 1)) * 100);
  return (
    <Pressable style={styles.progressRow} onPress={() => router.push('/cases?opened=1')}>
      <View style={styles.ring}>
        <Text style={styles.ringText}>{pct}%</Text>
      </View>
      <View style={{ flex: 1 }}>
        <Text style={styles.progressTitle}>{label}</Text>
        <Text style={styles.progressSub}>
          {numerator} / {denominator}
        </Text>
      </View>
    </Pressable>
  );
}

export function DashboardView({
  data,
  loading,
  error,
  duration,
  onDurationChange,
  onRefresh,
}: {
  data: DashboardPayload | null;
  loading: boolean;
  error: string;
  duration: string;
  onDurationChange: (value: string) => void;
  onRefresh: () => void;
}) {
  if (loading && !data) {
    return (
      <View style={styles.center}>
        <ActivityIndicator color={colors.primary} size="large" />
        <Text style={styles.muted}>Loading dashboard…</Text>
      </View>
    );
  }

  if (error && !data) {
    return (
      <View style={styles.center}>
        <Text style={styles.error}>{error}</Text>
        <Pressable style={styles.retry} onPress={onRefresh}>
          <Text style={styles.retryText}>Retry</Text>
        </Pressable>
      </View>
    );
  }

  const hero = data?.hero;
  const kpis = data?.kpis ?? [];
  const volume = data?.charts?.case_volume;
  const finance = data?.charts?.invoiced_vs_billed;
  const activity = data?.charts?.case_activity;
  const progress = data?.progress ?? [];

  return (
    <ScrollView
      style={styles.scroll}
      contentContainerStyle={styles.body}
      refreshControl={<RefreshControl refreshing={loading} onRefresh={onRefresh} tintColor={colors.primary} />}
    >
      <View style={styles.filterRow}>
        {['Day', 'Month', 'Year'].map((item) => (
          <Pressable key={item} style={[styles.filterPill, duration === item && styles.filterPillOn]} onPress={() => onDurationChange(item)}>
            <Text style={[styles.filterText, duration === item && styles.filterTextOn]}>{item}</Text>
          </Pressable>
        ))}
      </View>

      {hero ? (
        <View style={styles.hero}>
          <View style={styles.heroTop}>
            <Text style={styles.heroLabel}>Invoiced (file cohort)</Text>
            <TrendPill comparison={hero.invoiced_comparison} />
          </View>
          <Text style={styles.heroValue}>{money(hero.invoiced_total)}</Text>
          <View style={styles.heroSplit}>
            <View style={styles.heroInset}>
              <Text style={styles.insetLabel}>Billed costs</Text>
              <Text style={styles.insetValue}>{money(hero.billed_total)}</Text>
              <TrendPill comparison={hero.billed_comparison} />
            </View>
            <View style={styles.heroInset}>
              <Text style={styles.insetLabel}>Difference</Text>
              <Text style={styles.insetValue}>{money(hero.difference)}</Text>
              <TrendPill comparison={hero.difference_comparison} />
            </View>
          </View>
          <Text style={styles.heroFoot}>{hero.difference_label}</Text>
        </View>
      ) : null}

      <View style={styles.kpiGrid}>
        {kpis.map((item) => (
          <Pressable
            key={item.key}
            style={styles.kpiCard}
            onPress={() => {
              if (item.route === 'cases') {
                router.push(item.filter === 'assisted' ? '/cases?status=Assisted' : '/cases?opened=1');
                return;
              }
              if (item.route === 'finance') {
                router.push('/finance');
              }
            }}
          >
            <Text style={styles.kpiLabel}>{item.label}</Text>
            <Text style={styles.kpiValue}>{item.format === 'money' ? money(item.value) : item.value}</Text>
          </Pressable>
        ))}
      </View>

      {volume ? (
        <View style={styles.card}>
          <View style={styles.cardHead}>
            <View>
              <Text style={styles.cardTitle}>Case volume</Text>
              {typeof volume.period_total === 'number' ? (
                <Text style={styles.cardSub}>{volume.period_total} cases in period</Text>
              ) : null}
            </View>
            <TrendPill comparison={volume.period_comparison} />
          </View>
          <BarChart labels={volume.labels} values={volume.values} highlightIndex={volume.values.length - 1} />
          <Pressable style={styles.cta} onPress={() => router.push('/cases?opened=1')}>
            <Text style={styles.ctaText}>View cases</Text>
          </Pressable>
        </View>
      ) : null}

      {finance ? (
        <View style={styles.card}>
          <Text style={styles.cardTitle}>Invoiced vs billed</Text>
          <DualLineChart primary={finance.invoiced} secondary={finance.billed} />
          <Text style={styles.legend}>Solid: invoiced · Dashed: billed (file cohort by period)</Text>
          <Pressable style={styles.ctaSecondary} onPress={() => router.push('/finance')}>
            <Text style={styles.ctaSecondaryText}>Open finance</Text>
          </Pressable>
        </View>
      ) : null}

      {activity ? (
        <View style={styles.card}>
          <Text style={styles.cardTitle}>Case activity</Text>
          <AreaWithLineChart area={activity.active} line={activity.assisted} />
          <Text style={styles.legend}>Filled: open-status created · Line: Assisted created</Text>
          <Pressable style={styles.ctaSecondary} onPress={() => router.push('/cases?status=Assisted')}>
            <Text style={styles.ctaSecondaryText}>View Assisted cases</Text>
          </Pressable>
        </View>
      ) : null}

      {progress.length ? (
        <View style={styles.card}>
          <Text style={styles.cardTitle}>Completion</Text>
          {progress.map((item) => (
            <ProgressRing key={item.key} numerator={item.numerator} denominator={item.denominator} label={item.label} />
          ))}
        </View>
      ) : null}

      {error ? <Text style={styles.errorInline}>{error}</Text> : null}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  scroll: { flex: 1, backgroundColor: colors.paper },
  body: { padding: 16, gap: 14, paddingBottom: 120 },
  center: { flex: 1, alignItems: 'center', justifyContent: 'center', gap: 12, padding: 24, backgroundColor: colors.paper },
  muted: { color: colors.muted },
  error: { color: colors.danger, textAlign: 'center' },
  errorInline: { color: colors.danger, textAlign: 'center', marginTop: 8 },
  retry: { backgroundColor: colors.primary, paddingHorizontal: 18, paddingVertical: 12, borderRadius: radii.button },
  retryText: { color: colors.white, fontWeight: '700' },
  filterRow: { flexDirection: 'row', gap: 8 },
  filterPill: { paddingHorizontal: 14, paddingVertical: 8, borderRadius: radii.pill, backgroundColor: colors.card, borderWidth: 1, borderColor: colors.line },
  filterPillOn: { backgroundColor: colors.primary, borderColor: colors.primary },
  filterText: { color: colors.ink, fontWeight: '600' },
  filterTextOn: { color: colors.white },
  hero: { backgroundColor: colors.hero, borderRadius: radii.card, padding: 18, gap: 8, ...shadow.card },
  heroTop: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', gap: 8 },
  heroLabel: { color: '#C7CAFF', fontWeight: '600', flex: 1 },
  heroValue: { color: colors.white, fontSize: 32, fontWeight: '800' },
  heroSplit: { flexDirection: 'row', gap: 10, marginTop: 6 },
  heroInset: { flex: 1, backgroundColor: colors.heroMuted, borderRadius: 14, padding: 12, gap: 4 },
  insetLabel: { color: '#C7CAFF', fontSize: 12 },
  insetValue: { color: colors.white, fontWeight: '700', fontSize: 16 },
  heroFoot: { color: '#D1D5DB', fontSize: 12, marginTop: 4 },
  trendPill: { paddingHorizontal: 8, paddingVertical: 4, borderRadius: radii.pill, alignSelf: 'flex-start' },
  trendPillText: { fontSize: 11, fontWeight: '700' },
  kpiGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: 10 },
  kpiCard: { width: '48%', backgroundColor: colors.card, borderRadius: radii.card, padding: 14, gap: 6, borderWidth: 1, borderColor: colors.line, ...shadow.card },
  kpiLabel: { color: colors.muted, fontSize: 13 },
  kpiValue: { color: colors.ink, fontWeight: '800', fontSize: 20 },
  card: { backgroundColor: colors.card, borderRadius: radii.card, padding: 16, gap: 10, borderWidth: 1, borderColor: colors.line, ...shadow.card },
  cardHead: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-start', gap: 8 },
  cardTitle: { fontSize: 17, fontWeight: '700', color: colors.ink },
  cardSub: { fontSize: 12, color: colors.muted, marginTop: 2 },
  barRow: { flexDirection: 'row', alignItems: 'flex-end', gap: 8, minHeight: 140, paddingTop: 8 },
  barCol: { flex: 1, alignItems: 'center', gap: 6 },
  bar: { width: '100%', borderRadius: 10, backgroundColor: '#D1D5DB' },
  barActive: { backgroundColor: colors.primary },
  barLabel: { fontSize: 10, color: colors.muted },
  legend: { fontSize: 12, color: colors.muted },
  cta: { backgroundColor: colors.primary, borderRadius: radii.button, paddingVertical: 12, alignItems: 'center', marginTop: 4 },
  ctaText: { color: colors.white, fontWeight: '700' },
  ctaSecondary: { borderRadius: radii.button, paddingVertical: 10, alignItems: 'center', borderWidth: 1, borderColor: colors.line },
  ctaSecondaryText: { color: colors.primary, fontWeight: '700' },
  progressRow: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 8 },
  ring: { width: 52, height: 52, borderRadius: 26, borderWidth: 4, borderColor: colors.primary, alignItems: 'center', justifyContent: 'center' },
  ringText: { fontWeight: '800', color: colors.primary, fontSize: 12 },
  progressTitle: { fontWeight: '700', color: colors.ink },
  progressSub: { color: colors.muted, fontSize: 13 },
});
