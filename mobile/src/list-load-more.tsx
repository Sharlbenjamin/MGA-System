import React from 'react';
import { ActivityIndicator, Pressable, StyleSheet, Text, View } from 'react-native';
import { colors } from './theme';

export function ListLoadMore({
  page,
  lastPage,
  loading,
  onLoadMore,
}: {
  page: number;
  lastPage: number;
  loading: boolean;
  onLoadMore: () => void;
}) {
  if (page >= lastPage) {
    return null;
  }

  return (
    <View style={styles.wrap}>
      {loading ? (
        <ActivityIndicator color={colors.teal} />
      ) : (
        <Pressable style={styles.button} onPress={onLoadMore}>
          <Text style={styles.label}>Load more</Text>
        </Pressable>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { paddingVertical: 16, alignItems: 'center' },
  button: {
    paddingHorizontal: 20,
    paddingVertical: 12,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: colors.line,
    backgroundColor: colors.card,
  },
  label: { color: colors.teal, fontWeight: '700', fontSize: 15 },
});
