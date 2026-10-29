import React, { createContext, useContext, useEffect, useMemo, useState } from 'react';
import { registerPushToken, unregisterPushToken } from './alerts';
import { api, clearSession, getToken, readStoredUser, saveSession, setUnauthorizedHandler } from './api';

export type SessionUser = {
  id: number;
  name: string;
  email: string;
  roles: string[];
  permissions: string[];
  is_admin: boolean;
  signature?: {
    name?: string;
    job_title?: string;
    department?: string;
    work_phone?: string;
  } | null;
};

type AuthContextValue = {
  ready: boolean;
  user: SessionUser | null;
  signIn: (email: string, password: string) => Promise<void>;
  signOut: () => Promise<void>;
  refreshUser: () => Promise<void>;
};

const AuthContext = createContext<AuthContextValue | null>(null);

function roleNames(user: SessionUser): string[] {
  return (user.roles || []).map((role) => (typeof role === 'string' ? role : (role as { name?: string }).name || '')).filter(Boolean);
}

export function normalizeUser(raw: Partial<SessionUser> & { roles?: unknown; permissions?: unknown }): SessionUser {
  const roles = Array.isArray(raw.roles)
    ? raw.roles.map((role) => (typeof role === 'string' ? role : (role as { name?: string })?.name || '')).filter(Boolean)
    : [];
  const permissions = Array.isArray(raw.permissions)
    ? raw.permissions.map((permission) => (typeof permission === 'string' ? permission : (permission as { name?: string })?.name || '')).filter(Boolean)
    : [];
  const isAdmin = Boolean(raw.is_admin) || roles.some((role) => role.toLowerCase() === 'admin');
  return {
    id: Number(raw.id),
    name: raw.name || '',
    email: raw.email || '',
    roles,
    permissions,
    is_admin: isAdmin,
    signature: raw.signature ?? null,
  };
}

function permissionsWereSent(user: SessionUser): boolean {
  return Array.isArray(user.permissions) && user.permissions.length > 0;
}

export function can(user: SessionUser | null, permission: string, adminOnly = false): boolean {
  if (!user) return false;
  if (user.is_admin || roleNames(user).some((role) => role.toLowerCase() === 'admin')) return true;
  // The live login returns only id, name, and email. The API still enforces access.
  if (!permissionsWereSent(user)) return true;
  if (adminOnly) return false;
  return user.permissions.includes(permission);
}

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [ready, setReady] = useState(false);
  const [user, setUser] = useState<SessionUser | null>(null);

  useEffect(() => {
    setUnauthorizedHandler(() => {
      setUser(null);
      clearSession().catch(() => undefined);
    });

    (async () => {
      const token = await getToken();
      const stored = await readStoredUser<SessionUser>();
      if (stored) setUser(stored);
      if (token) {
        try {
          const fresh = normalizeUser(await api<SessionUser>('/user'));
          setUser(fresh);
          await saveSession(token, fresh);
          registerPushToken().catch(() => undefined);
        } catch {
          // Keep the stored user if the network is briefly unavailable.
        }
      }
      setReady(true);
    })();
  }, []);

  const value = useMemo<AuthContextValue>(
    () => ({
      ready,
      user,
      async signIn(email, password) {
        const result = await api<{ token: string; user: SessionUser }>('/login', {
          auth: false,
          json: { email, password, device_name: 'mga-mobile' },
        });
        const sessionUser = normalizeUser(result.user);
        await saveSession(result.token, sessionUser);
        setUser(sessionUser);
        registerPushToken().catch(() => undefined);
      },
      async signOut() {
        try {
          await unregisterPushToken();
        } catch {
          // Continue logout even if push unregister fails.
        }
        try {
          await api('/logout', { method: 'POST', json: {} });
        } catch {
          // The local session still needs to end if the server is unreachable.
        }
        await clearSession();
        setUser(null);
      },
      async refreshUser() {
        const token = await getToken();
        if (!token) return;
        const fresh = normalizeUser(await api<SessionUser>('/user'));
        setUser(fresh);
        await saveSession(token, fresh);
      },
    }),
    [ready, user],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthContextValue {
  const value = useContext(AuthContext);
  if (!value) throw new Error('Auth is unavailable');
  return value;
}
