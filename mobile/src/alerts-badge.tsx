import React, { createContext, useContext, useEffect, useState } from 'react';
import { AppState } from 'react-native';
import { pollNewAlerts, registerPushToken, watchCaseChanges } from './alerts';
import { useAuth } from './auth';
import { registerBackgroundAlerts } from './background';

const BadgeContext = createContext(0);

export function AlertsProvider({ children }: { children: React.ReactNode }) {
  const { user } = useAuth();
  const [unread, setUnread] = useState(0);

  useEffect(() => {
    if (!user) {
      setUnread(0);
      return;
    }

    let active = true;
    const refresh = async () => {
      // Server inbox first — do not wait on heavy /files list scans (cases tab uses those separately).
      const next = await pollNewAlerts(true).catch(() => 0);
      watchCaseChanges().catch(() => false);
      if (active) setUnread(typeof next === 'number' ? next : 0);
    };

    const boot = setTimeout(() => {
      refresh();
      registerPushToken().catch(() => undefined);
      registerBackgroundAlerts().catch(() => undefined);
    }, 2500);
    const timer = setInterval(refresh, 45000);
    const subscription = AppState.addEventListener('change', (state) => {
      if (state === 'active') {
        registerPushToken().catch(() => undefined);
        refresh();
      }
    });

    return () => {
      active = false;
      clearTimeout(boot);
      clearInterval(timer);
      subscription.remove();
    };
  }, [user?.id]);

  return <BadgeContext.Provider value={unread}>{children}</BadgeContext.Provider>;
}

export function useAlertsBadge() {
  return useContext(BadgeContext);
}
