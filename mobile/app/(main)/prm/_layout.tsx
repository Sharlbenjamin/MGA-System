import { Stack } from 'expo-router';
import React from 'react';
import { colors } from '../../../src/theme';

export default function SectionLayout() {
  return <Stack screenOptions={{ headerShown: false, contentStyle: { backgroundColor: colors.paper } }} />;
}
