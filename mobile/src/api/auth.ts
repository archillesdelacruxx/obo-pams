import { apiFetch } from './client';
import type { LoginError, LoginResponse } from '../types';

export async function apiLogin(
  username: string,
  password: string,
  remember: boolean,
): Promise<LoginResponse> {
  const res = await apiFetch<LoginResponse | LoginError>('/api/login.php', {
    method: 'POST',
    skipAuth: true,
    body: JSON.stringify({ username, password, remember }),
  });

  if (!res.success || !('token' in res)) {
    const err = res as LoginError;
    const apiError = new Error(err.error ?? 'Invalid username or password.') as Error & {
      locked?: boolean;
      locked_until?: number | null;
    };
    apiError.name = 'LoginError';
    apiError.locked = err.locked;
    apiError.locked_until = err.locked_until;
    throw apiError;
  }

  return res as LoginResponse;
}

export async function apiLogout(): Promise<void> {
  await apiFetch('/api/logout.php', { method: 'POST' }).catch(() => undefined);
}

export function apiGetUnreadNotifications(): Promise<{ success: boolean; count: number }> {
  return apiFetch('/api/index.php?module=notifications&action=unread-count');
}

export interface AppNotification {
  id: number;
  title: string;
  message: string;
  module_name: string;
  record_id: number | null;
  sender_name: string | null;
  is_read: number;
  created_at: string;
}

export function apiGetNotifications(): Promise<{ success: boolean; data: AppNotification[] }> {
  return apiFetch('/api/index.php?module=notifications&action=list');
}

export function apiMarkAllNotificationsRead(): Promise<{ success: boolean }> {
  return apiFetch('/api/index.php?module=notifications&action=mark-all-read', {
    method: 'POST',
    body: JSON.stringify({}),
  });
}

export function apiMarkNotificationRead(id: number): Promise<{ success: boolean }> {
  return apiFetch('/api/index.php?module=notifications&action=mark-read', {
    method: 'POST',
    body: JSON.stringify({ id }),
  });
}
