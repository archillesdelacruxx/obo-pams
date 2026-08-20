import { getApiBaseUrl } from '../config';

/**
 * Resolves local file paths, relative web URLs, and full URLs into a valid
 * URI string for React Native's <Image source={{ uri }} /> component.
 */
export function resolvePhotoUri(path: string | null | undefined): string | null {
  if (!path || typeof path !== 'string' || path.trim() === '') return null;
  const p = path.trim();

  // Already a full HTTP or HTTPS URL
  if (p.startsWith('http://') || p.startsWith('https://')) {
    return p;
  }

  // Already has a valid mobile URI scheme (file://, content://, ph://)
  if (p.startsWith('file://') || p.startsWith('content://') || p.startsWith('ph://')) {
    return p;
  }

  // Relative path from web server (e.g., uploads/inspection_photos/...)
  if (p.startsWith('uploads/') || p.startsWith('/uploads/')) {
    const cleanPath = p.startsWith('/') ? p.slice(1) : p;
    return `${getApiBaseUrl()}/${cleanPath}`;
  }

  // Windows absolute disk path (e.g. C:\wamp64\... or C:/wamp64/...)
  if (/^[a-zA-Z]:[\\/]/.test(p)) {
    const normalized = p.replace(/\\/g, '/');
    return `file:///${normalized}`;
  }

  // Unix absolute disk path (e.g. /data/user/0/...)
  if (p.startsWith('/')) {
    return `file://${p}`;
  }

  return p;
}
