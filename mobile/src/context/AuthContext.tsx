import React, { createContext, useContext, useEffect, useMemo, useState } from 'react';
import { AppState } from 'react-native';
import * as SecureStore from 'expo-secure-store';
import { apiLogin, apiLogout } from '../api/auth';
import { setAuthToken, setOnUnauthorized } from '../api/client';
import { runSync, scheduleSync } from '../db/sync';
import type { LoginResponse, User } from '../types';

const TOKEN_KEY = 'pams_token';
const SESSION_KEY = 'pams_session';

type Status = 'loading' | 'signedOut' | 'signedIn';

interface StoredSession {
  user: User;
  permissions: string[];
}

interface AuthContextValue {
  status: Status;
  user: User | null;
  permissions: string[];
  signIn: (username: string, password: string, remember: boolean) => Promise<LoginResponse>;
  signOut: () => Promise<void>;
  updateUser: (patch: Partial<User>) => Promise<void>;
}

const AuthContext = createContext<AuthContextValue | undefined>(undefined);

async function readStoredSession(): Promise<{ token: string; session: StoredSession } | null> {
  const token = await SecureStore.getItemAsync(TOKEN_KEY);
  if (!token) return null;
  const raw = await SecureStore.getItemAsync(SESSION_KEY);
  if (!raw) return null;
  try {
    const session = JSON.parse(raw) as StoredSession;
    if (!session.user?.id) return null;
    if (session.user.role !== 'inspector' || session.user.is_admin) return null;
    return { token, session };
  } catch {
    return null;
  }
}

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [status, setStatus] = useState<Status>('loading');
  const [user, setUser] = useState<User | null>(null);
  const [permissions, setPermissions] = useState<string[]>([]);

  useEffect(() => {
    let mounted = true;
    (async () => {
      const stored = await readStoredSession();
      if (!mounted) return;
      if (stored) {
        setAuthToken(stored.token);
        setUser(stored.session.user);
        setPermissions(stored.session.permissions);
        setStatus('signedIn');
      } else {
        setStatus('signedOut');
      }
    })();

    setOnUnauthorized(() => {
      setAuthToken(null);
      setUser(null);
      setPermissions([]);
      setStatus('signedOut');
      void SecureStore.deleteItemAsync(TOKEN_KEY);
      void SecureStore.deleteItemAsync(SESSION_KEY);
    });

    return () => {
      mounted = false;
      setOnUnauthorized(null);
    };
  }, []);

  /* Auto-sync when signed in, on sign-in/session restore, on app foreground,
     plus periodic polling while the app is active so admin review decisions
     show up on the phone within seconds on the same WiFi network. */
  useEffect(() => {
    if (status !== 'signedIn') return;
    scheduleSync(1500);
    const sub = AppState.addEventListener('change', (s) => {
      if (s === 'active') scheduleSync(800);
    });
    const poll = setInterval(() => {
      if (AppState.currentState !== 'active') return;
      void runSync().catch(() => undefined);
    }, 10000);
    return () => {
      sub.remove();
      clearInterval(poll);
    };
  }, [status]);

  const value = useMemo<AuthContextValue>(
    () => ({
      status,
      user,
      permissions,
      async signIn(username, password, remember) {
        const res = await apiLogin(username, password, remember);
        if (res.user.role !== 'inspector' || res.user.is_admin) {
          throw new Error('Only inspector accounts can sign in to the mobile app.');
        }
        await SecureStore.setItemAsync(TOKEN_KEY, res.token);
        await SecureStore.setItemAsync(
          SESSION_KEY,
          JSON.stringify({ user: res.user, permissions: res.permissions }),
        );
        setAuthToken(res.token);
        setUser(res.user);
        setPermissions(res.permissions);
        setStatus('signedIn');
        return res;
      },
      async signOut() {
        await apiLogout().catch(() => undefined);
        await SecureStore.deleteItemAsync(TOKEN_KEY);
        await SecureStore.deleteItemAsync(SESSION_KEY);
        setAuthToken(null);
        setUser(null);
        setPermissions([]);
        setStatus('signedOut');
      },
      async updateUser(patch) {
        if (!user) return;
        const next = { ...user, ...patch };
        setUser(next);
        try {
          const raw = await SecureStore.getItemAsync(SESSION_KEY);
          if (raw) {
            const session = JSON.parse(raw) as StoredSession;
            session.user = next;
            await SecureStore.setItemAsync(SESSION_KEY, JSON.stringify(session));
          }
        } catch {
          /* ignore */
        }
      },
    }),
    [status, user, permissions],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthContextValue {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth must be used within AuthProvider');
  return ctx;
}
