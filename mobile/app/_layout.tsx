import { router, Stack } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import React, { useEffect } from 'react';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import * as Notifications from 'expo-notifications';
import { AuthProvider } from '../src/auth';
import '../src/background';

export default function RootLayout() {
  useEffect(() => {
    const openFromNotification = (data: { subject_type?: string; subject_id?: string }) => {
      if (data?.subject_type === 'file' && data.subject_id) router.push(`/cases/${data.subject_id}`);
      if (data?.subject_type === 'client' && data.subject_id) router.push(`/crm/clients/${data.subject_id}`);
      if (data?.subject_type === 'provider' && data.subject_id) router.push(`/prm/providers/${data.subject_id}`);
    };

    const subscription = Notifications.addNotificationResponseReceivedListener((response) => {
      openFromNotification(response.notification.request.content.data as { subject_type?: string; subject_id?: string });
    });

    Notifications.getLastNotificationResponseAsync().then((response) => {
      if (response) {
        openFromNotification(response.notification.request.content.data as { subject_type?: string; subject_id?: string });
      }
    });

    return () => subscription.remove();
  }, []);

  return (
    <SafeAreaProvider>
      <AuthProvider>
        <StatusBar style="dark" />
        <Stack screenOptions={{ headerShown: false }} />
      </AuthProvider>
    </SafeAreaProvider>
  );
}
