import { File, UploadType } from 'expo-file-system';
import { getApiBaseUrl } from '../config';
import { apiFetch, getAuthToken } from './client';

export const apiProfileUpdate = (fullName: string, email: string) =>
  apiFetch<{ success: boolean; message: string }>('/api/index.php?module=profile&action=update', {
    method: 'POST',
    body: JSON.stringify({ full_name: fullName, email }),
  });

export async function apiProfileUploadPhoto(asset: {
  uri: string;
  name?: string | null;
  type?: string | null;
}): Promise<{ success: boolean; path: string }> {
  const file = new File(asset.uri);
  const token = getAuthToken();
  const upload = file.createUploadTask(`${getApiBaseUrl()}/api/index.php?module=profile&action=upload-photo`, {
    headers: {
      Accept: 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    uploadType: UploadType.MULTIPART,
    fieldName: 'photo',
    mimeType: asset.type ?? 'image/jpeg',
  });
  const result = await upload.uploadAsync();
  if (result.status < 200 || result.status >= 300) {
    let message = `Upload failed (${result.status})`;
    try {
      const parsed = JSON.parse(result.body) as { error?: string };
      if (parsed?.error) message = parsed.error;
    } catch {
      /* keep the generic message */
    }
    throw new Error(message);
  }
  return JSON.parse(result.body) as { success: boolean; path: string };
}
