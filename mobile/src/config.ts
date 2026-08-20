import Constants from 'expo-constants';
import * as SecureStore from 'expo-secure-store';

const SERVER_HOST_STORAGE_KEY = 'server_host';
const DEFAULT_SERVER_HOST = '172.16.128.15';

function getDevHost(): string | null {
  const uri =
    Constants.expoConfig?.hostUri ??
    (Constants as unknown as { manifest2?: { extra?: { expoGo?: { debuggerHost?: string } } } })
      .manifest2?.extra?.expoGo?.debuggerHost ??
    null;
  if (!uri) return null;
  const host = uri.split(':')[0];
  return host && host.length > 0 ? host : null;
}

let serverHost: string | null = null;

/** Loads the persisted server host (or detects the dev host) once at startup. */
export async function initServerHost(): Promise<void> {
  if (serverHost !== null) return;
  try {
    const stored = await SecureStore.getItemAsync(SERVER_HOST_STORAGE_KEY);
    serverHost = stored || getDevHost() || DEFAULT_SERVER_HOST;
  } catch {
    serverHost = getDevHost() || DEFAULT_SERVER_HOST;
  }
}

export function getServerHost(): string {
  return serverHost ?? getDevHost() ?? DEFAULT_SERVER_HOST;
}

export function getApiBaseUrl(): string {
  return `http://${getServerHost()}/PAMS`;
}

/** Persists a new server host (IP or hostname, with or without port). */
export async function setServerHost(host: string): Promise<void> {
  const clean = host.trim().replace(/^https?:\/\//i, '').replace(/\/.*$/, '').trim();
  if (!clean) return;
  serverHost = clean;
  try {
    await SecureStore.setItemAsync(SERVER_HOST_STORAGE_KEY, clean);
  } catch {
    /* best-effort persistence */
  }
}

export const APP_VERSION = '1.0.0';