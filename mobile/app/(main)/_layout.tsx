import { Redirect, Tabs } from 'expo-router';
import React from 'react';
import { ColorValue, Text, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { AlertsProvider } from '../../src/alerts-badge';
import { useAuth } from '../../src/auth';
import { CatalogProvider } from '../../src/catalog';
import { tabBarStyle } from '../../src/components/ui';
import { colors } from '../../src/theme';

function TabIcon({ glyph, color, focused }: { glyph: string; color: ColorValue | null | undefined; focused: boolean }) {
  return (
    <View style={{ minWidth: 36, height: 26, borderRadius: 8, alignItems: 'center', justifyContent: 'center', backgroundColor: focused ? colors.tealSoft : 'transparent' }}>
      <Text style={{ color: color || colors.muted, fontSize: 14, fontWeight: '700' }}>{glyph}</Text>
    </View>
  );
}

export default function MainLayout() {
  const { ready, user } = useAuth();
  const insets = useSafeAreaInsets();
  if (!ready) return null;
  if (!user) return <Redirect href="/login" />;

  return (
    <CatalogProvider>
      <AlertsProvider>
        <Tabs
          screenOptions={{
            headerShown: false,
            tabBarActiveTintColor: colors.teal,
            tabBarInactiveTintColor: colors.muted,
            tabBarStyle: tabBarStyle(insets.bottom),
            tabBarLabelStyle: { fontSize: 11, fontWeight: '700', marginTop: 2 },
            tabBarHideOnKeyboard: true,
          }}
        >
          <Tabs.Screen name="home" options={{ title: 'Home', tabBarIcon: ({ color, focused }) => <TabIcon glyph="⌂" color={color} focused={focused} /> }} />
          <Tabs.Screen name="cases" options={{ title: 'Cases', tabBarIcon: ({ color, focused }) => <TabIcon glyph="▣" color={color} focused={focused} /> }} />
          <Tabs.Screen name="finance" options={{ title: 'Finance', tabBarIcon: ({ color, focused }) => <TabIcon glyph="€" color={color} focused={focused} /> }} />
          <Tabs.Screen name="crm" options={{ title: 'CRM', tabBarIcon: ({ color, focused }) => <TabIcon glyph="◉" color={color} focused={focused} /> }} />
          <Tabs.Screen name="prm" options={{ title: 'PRM', tabBarIcon: ({ color, focused }) => <TabIcon glyph="✚" color={color} focused={focused} /> }} />
          <Tabs.Screen name="team" options={{ title: 'Team', tabBarIcon: ({ color, focused }) => <TabIcon glyph="☺" color={color} focused={focused} /> }} />
          <Tabs.Screen name="profile" options={{ title: 'Profile', tabBarIcon: ({ color, focused }) => <TabIcon glyph="ID" color={color} focused={focused} /> }} />
          <Tabs.Screen name="alerts" options={{ href: null }} />
        </Tabs>
      </AlertsProvider>
    </CatalogProvider>
  );
}
