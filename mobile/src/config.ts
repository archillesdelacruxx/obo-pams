import Constants from 'expo-constants';

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

const DEV_HOST = getDevHost();
const SERVER_HOST = DEV_HOST ?? '192.168.1.100';

export const API_HOST = SERVER_HOST;
export const API_BASE_URL = `http://${API_HOST}/obo-pams`;

export const APP_VERSION = '1.0.0';
