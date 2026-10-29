import React from 'react';
import { Pressable, ScrollView, StyleSheet, Text } from 'react-native';
import { colors, statusColor } from '../theme';
import { WidgetCount } from '../widgets';

export function StatWidgets({
  items,
  activeKey,
  onPress,
}: {
  items: WidgetCount[];
  activeKey?: string;
  onPress: (key: string) => void;
}) {
  if (!items.length) return null;
  return (
    <ScrollView horizontal showsHorizontalScrollIndicator={false} style={styles.row} contentContainerStyle={styles.content}>
      {items.map((item) => {
        const active = item.key === activeKey;
        const tint = statusColor(item.key === 'all' || item.key === 'leads' ? '' : item.key);
        return (
          <Pressable key={item.key} onPress={() => onPress(item.key)} style={[styles.card, active && { borderColor: tint, backgroundColor: colors.tealSoft }]}>
            <Text style={[styles.count, { color: tint }]}>{item.count}</Text>
            <Text style={styles.label} numberOfLines={1}>
              {item.label}
            </Text>
            {item.description ? (
              <Text style={styles.description} numberOfLines={2}>
                {item.description}
              </Text>
            ) : null}
          </Pressable>
        );
      })}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  row: { flexGrow: 0, flexShrink: 0, maxHeight: 108 },
  content: { paddingHorizontal: 16, gap: 8, paddingTop: 10, alignItems: 'center' },
  card: {
    width: 128,
    minHeight: 88,
    borderRadius: 16,
    backgroundColor: colors.card,
    borderWidth: 1,
    borderColor: colors.line,
    paddingHorizontal: 12,
    paddingVertical: 10,
    justifyContent: 'center',
  },
  count: { fontSize: 22, fontWeight: '700' },
  label: { color: colors.ink, fontSize: 13, fontWeight: '700', marginTop: 2 },
  description: { color: colors.muted, fontSize: 11, fontWeight: '600', marginTop: 2, lineHeight: 14 },
});
