import { router, useNavigation } from 'expo-router';
import React, { useEffect } from 'react';
import { KeyboardAvoidingView, Platform, Pressable, StyleSheet, Text, TextInput, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useAlertsBadge } from '../alerts-badge';
import { colors, statusColor } from '../theme';

export function tabBarStyle(bottom: number) {
  return {
    backgroundColor: colors.card,
    borderTopColor: colors.line,
    borderTopWidth: 1,
    height: 58 + bottom,
    paddingTop: 6,
    paddingBottom: Math.max(bottom, 8),
  };
}

export function useFullScreen() {
  const navigation = useNavigation();
  const insets = useSafeAreaInsets();
  useEffect(() => {
    const parent = navigation.getParent();
    parent?.setOptions({ tabBarStyle: { display: 'none' } });
    return () => {
      parent?.setOptions({ tabBarStyle: tabBarStyle(insets.bottom) });
    };
  }, [insets.bottom, navigation]);
}

export function ScreenHeader({ title, back = false }: { title: string; back?: boolean }) {
  const unread = useAlertsBadge();
  const insets = useSafeAreaInsets();
  return (
    <View style={[styles.header, { paddingTop: insets.top + 8 }]}>
      <View style={styles.titleRow}>
        {back ? (
          <Pressable onPress={() => router.back()} hitSlop={10} style={styles.back}>
            <Text style={{ fontSize: 24, color: colors.ink }}>‹</Text>
          </Pressable>
        ) : null}
        <Text style={styles.title} numberOfLines={1}>
          {title}
        </Text>
      </View>
      <Pressable onPress={() => router.push('/alerts')} hitSlop={8} style={styles.bell}>
        <Text style={{ fontSize: 18 }}>🔔</Text>
        {unread > 0 ? (
          <View style={styles.badge}>
            <Text style={styles.badgeText}>{unread > 9 ? '9+' : unread}</Text>
          </View>
        ) : null}
      </Pressable>
    </View>
  );
}

export function StatusBadge({ status }: { status?: string | null }) {
  if (!status) return null;
  const color = statusColor(status);
  return (
    <View style={[styles.badgePill, { backgroundColor: `${color}22` }]}>
      <Text style={[styles.badgePillText, { color }]}>{status}</Text>
    </View>
  );
}

export function SearchField({
  value,
  onChangeText,
  onSubmit,
  placeholder,
}: {
  value: string;
  onChangeText: (text: string) => void;
  onSubmit?: () => void;
  placeholder: string;
}) {
  return (
    <View style={styles.searchWrap}>
      <Text style={styles.searchGlyph}>⌕</Text>
      <TextInput
        value={value}
        onChangeText={onChangeText}
        onSubmitEditing={onSubmit}
        placeholder={placeholder}
        placeholderTextColor={colors.muted}
        style={styles.searchInput}
        returnKeyType="search"
        autoCorrect={false}
        clearButtonMode="while-editing"
      />
    </View>
  );
}

export function FormScreen({ children, footer }: { children: React.ReactNode; footer?: React.ReactNode }) {
  const insets = useSafeAreaInsets();
  return (
    <KeyboardAvoidingView style={styles.flex} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
      <View style={styles.flex}>{children}</View>
      {footer ? <View style={[styles.footer, { paddingBottom: Math.max(insets.bottom, 12) }]}>{footer}</View> : null}
    </KeyboardAvoidingView>
  );
}

export function Pill({ label, active, color, onPress }: { label: string; active?: boolean; color?: string; onPress?: () => void }) {
  return (
    <Pressable onPress={onPress} style={[styles.pill, active && { backgroundColor: colors.teal }]}>
      <View style={[styles.dot, { backgroundColor: color || colors.teal }]} />
      <Text style={[styles.pillText, active && { color: colors.white }]}>{label}</Text>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  header: {
    paddingTop: 8,
    paddingHorizontal: 20,
    paddingBottom: 12,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  titleRow: { flexDirection: 'row', alignItems: 'center', flex: 1, marginRight: 12 },
  back: { marginRight: 4 },
  title: { fontSize: 22, fontWeight: '700', color: colors.ink, letterSpacing: -0.4, flexShrink: 1 },
  flex: { flex: 1 },
  footer: {
    paddingHorizontal: 16,
    paddingTop: 12,
    backgroundColor: colors.card,
    borderTopWidth: 1,
    borderTopColor: colors.line,
  },
  badgePill: { paddingHorizontal: 10, paddingVertical: 4, borderRadius: 999, alignSelf: 'flex-start' },
  badgePillText: { fontSize: 12, fontWeight: '700' },
  searchWrap: {
    marginHorizontal: 16,
    backgroundColor: colors.card,
    borderRadius: 14,
    borderWidth: 1,
    borderColor: colors.line,
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 12,
    minHeight: 48,
    gap: 8,
  },
  searchGlyph: { color: colors.muted, fontSize: 18 },
  searchInput: { flex: 1, color: colors.ink, fontSize: 16, paddingVertical: 10 },
  bell: { width: 40, height: 40, borderRadius: 20, backgroundColor: colors.card, alignItems: 'center', justifyContent: 'center' },
  badge: {
    position: 'absolute',
    top: -2,
    right: -2,
    minWidth: 18,
    height: 18,
    borderRadius: 9,
    backgroundColor: colors.copper,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 4,
  },
  badgeText: { color: colors.white, fontSize: 10, fontWeight: '700' },
  pill: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 999,
    backgroundColor: colors.card,
    borderWidth: 1,
    borderColor: colors.line,
  },
  pillText: { color: colors.ink, fontSize: 13, fontWeight: '600' },
  dot: { width: 7, height: 7, borderRadius: 4 },
});
