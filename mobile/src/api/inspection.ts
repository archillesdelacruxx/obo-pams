import { apiFetch } from './client';
import { getApiBaseUrl } from '../config';
import type { AiRemarkResponse, AiStatusResponse } from '../types';

export function photoUrl(path: string | null | undefined): string | null {
  if (!path) return null;
  if (path.startsWith('http') || path.startsWith('file://') || path.startsWith('content://')) return path;
  return `${getApiBaseUrl()}/${path}`;
}

export const apiGetAiStatus = () =>
  apiFetch<AiStatusResponse>('/api/index.php?module=inspection&action=ai-status');

export const apiGetTeamLeaders = () =>
  apiFetch<{ success: boolean; data: { id: number; full_name: string; position: string | null; team_no: number }[] }>(
    '/api/index.php?module=teamleaders&action=roster',
  );

export const apiRemarkAi = (category: string, items: { item_text: string; result: string }[]) =>
  apiFetch<AiRemarkResponse>('/api/index.php?module=inspection&action=remark-ai', {
    method: 'POST',
    body: JSON.stringify({ category, items }),
  });
