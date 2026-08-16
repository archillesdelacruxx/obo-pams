import { File, UploadType } from 'expo-file-system';
import { API_BASE_URL } from '../config';
import { apiFetch, ApiError, getAuthToken } from '../api/client';
import { getDb } from './database';
import { getRecord, upsertTeamLeaders, upsertTemplate } from './inspectionRepo';
import { apiGetTeamLeaders } from '../api/inspection';

/* ---------------------------------------------------------------------------
   INSPECTION SYNC — one-way upload (mobile → web MySQL) + pull of the
   admin's review decisions back to the app (web → mobile).

   Uploaded records always arrive on the web as Draft; if the record was
   submitted locally (status 'Under Review'), it is auto-submitted so the
   Inspector Admin can review it immediately. Deletes are never propagated.
   --------------------------------------------------------------------------- */

export interface SyncSummary {
  pushed: number;
  pulled: number;
  errors: number;
  offline: boolean;
}

let syncRunning = false;
const syncListeners: Array<() => void> = [];

export function subscribeSync(listener: () => void): () => void {
  syncListeners.push(listener);
  return () => {
    const i = syncListeners.indexOf(listener);
    if (i !== -1) syncListeners.splice(i, 1);
  };
}

function emitSync() {
  for (const l of syncListeners) l();
}

export async function pendingCount(): Promise<number> {
  const db = await getDb();
  const { c } = (await db.getFirstAsync<{ c: number }>(
    "SELECT COUNT(*) AS c FROM inspection_records WHERE is_demo = 0 AND sync_status IN ('pending','error')",
  )) ?? { c: 0 };
  return c;
}

export async function lastSyncedAt(): Promise<string | null> {
  const db = await getDb();
  const row = await db.getFirstAsync<{ s: string | null }>(
    'SELECT MAX(synced_at) AS s FROM inspection_records WHERE synced_at IS NOT NULL',
  );
  return row?.s ?? null;
}

let syncTimer: ReturnType<typeof setTimeout> | null = null;

export function scheduleSync(delayMs = 2000): void {
  if (syncTimer) clearTimeout(syncTimer);
  syncTimer = setTimeout(() => {
    syncTimer = null;
    void runSync().catch(() => undefined);
  }, delayMs);
}

function mimeFromPath(path: string): string {
  const ext = path.split('.').pop()?.toLowerCase();
  if (ext === 'png') return 'image/png';
  if (ext === 'webp') return 'image/webp';
  if (ext === 'gif') return 'image/gif';
  return 'image/jpeg';
}

async function pushPhotos(recordId: number, webId: number): Promise<void> {
  const db = await getDb();
  const photos = await db.getAllAsync<{ id: number; file_path: string; caption: string | null }>(
    "SELECT id, file_path, caption FROM inspection_photos WHERE record_id = ? AND (web_photo_id IS NULL OR sync_status IN ('pending','error'))",
    recordId,
  );
  const token = getAuthToken();
  for (const photo of photos) {
    try {
      const file = new File(photo.file_path);
      if (!file.exists) {
        await db.runAsync("UPDATE inspection_photos SET sync_status = 'error' WHERE id = ?", photo.id);
        continue;
      }
      const upload = file.createUploadTask(
        `${API_BASE_URL}/api/index.php?module=inspection&action=photos/upload`,
        {
          headers: { Accept: 'application/json', ...(token ? { Authorization: `Bearer ${token}` } : {}) },
          uploadType: UploadType.MULTIPART,
          fieldName: 'photo',
          mimeType: mimeFromPath(photo.file_path),
          parameters: { inspection_id: String(webId), caption: photo.caption ?? '' },
        },
      );
      const result = await upload.uploadAsync();
      if (result.status < 200 || result.status >= 300) {
        let message = `Photo upload failed (${result.status})`;
        try {
          const parsed = JSON.parse(result.body) as { error?: string };
          if (parsed?.error) message = parsed.error;
        } catch {
          /* keep the generic message */
        }
        throw new ApiError(result.status, message);
      }
      const parsed = JSON.parse(result.body) as { id?: number };
      await db.runAsync(
        "UPDATE inspection_photos SET web_photo_id = ?, sync_status = 'synced' WHERE id = ?",
        parsed.id ?? null,
        photo.id,
      );
    } catch (err) {
      await db.runAsync("UPDATE inspection_photos SET sync_status = 'error' WHERE id = ?", photo.id);
      if (err instanceof ApiError && err.status === 0) throw err;
    }
  }
}

async function pushRecord(p: { id: number; web_id: number | null; status: string; review_pending: number }): Promise<void> {
  const db = await getDb();
  const detail = await getRecord(p.id);
  if (!detail) throw new Error('Local record missing.');

  const base = {
    application_no: detail.application_no ?? '',
    permit_no: detail.permit_no,
    permit_date_issued: detail.permit_date_issued,
    project_title: detail.project_title,
    project_location: detail.project_location,
    owner_representative: detail.owner_representative,
    contact_number: detail.contact_number,
    project_contractor: detail.project_contractor,
    project_engineer: detail.project_engineer,
    inspection_team: detail.inspection_team,
    inspection_date: detail.inspection_date,
    inspection_type: detail.inspection_type,
    inspection_result: detail.inspection_result,
    time_started: detail.time_started,
    time_finished: detail.time_finished,
    physical_accomplishment: detail.physical_accomplishment,
    mech_accomplishment: detail.mech_accomplishment,
    extra_fields: detail.extra_fields ?? {},
    overall_findings: detail.overall_findings,
    recommendations: detail.recommendations,
    completion_percentage: detail.completion_percentage,
    team_leader_1: detail.team_leader_1,
    team_leader_2: detail.team_leader_2,
    results: detail.results.map((r) => ({
      template_item_id: r.template_item_id,
      category: r.category,
      item_text: r.item_text,
      item_type: r.item_type,
      result: r.result,
      remarks: r.remarks ?? '',
    })),
  };

  let webId = p.web_id;
  if (webId == null) {
    const created = await apiFetch<{ success: boolean; id: number; inspection_no: string; application_no?: string }>(
      '/api/index.php?module=inspection&action=checklist/create',
      { method: 'POST', body: JSON.stringify(base) },
    );
    webId = created.id;
    await db.runAsync(
      'UPDATE inspection_records SET web_id = ?, inspection_no = ?, application_no = ? WHERE id = ?',
      webId,
      created.inspection_no,
      created.application_no ?? detail.application_no,
      p.id,
    );
  } else {
    await apiFetch<{ success: boolean }>('/api/index.php?module=inspection&action=checklist/update', {
      method: 'POST',
      body: JSON.stringify({ ...base, id: webId }),
    });
  }

  await pushPhotos(p.id, webId);

  if (p.status === 'Under Review') {
    try {
      await apiFetch<{ success: boolean }>('/api/index.php?module=inspection&action=checklist/submit', {
        method: 'POST',
        body: JSON.stringify({ id: webId }),
      });
    } catch (err) {
      if (!(err instanceof ApiError) || err.status !== 422) throw err;
    }
  }

  if (p.review_pending === 1 && (detail.status === 'Approved' || detail.status === 'Rejected')) {
    try {
      await apiFetch<{ success: boolean }>('/api/index.php?module=inspection&action=checklist/submit', {
        method: 'POST',
        body: JSON.stringify({ id: webId }),
      });
    } catch (err) {
      if (!(err instanceof ApiError) || err.status !== 422) throw err;
    }
    await apiFetch<{ success: boolean }>(
      `/api/index.php?module=inspection&action=${detail.status === 'Approved' ? 'checklist/review' : 'checklist/reject'}`,
      {
        method: 'POST',
        body: JSON.stringify({ id: webId, remarks: detail.review_remarks ?? '' }),
      },
    );
    await db.runAsync('UPDATE inspection_records SET review_pending = 0 WHERE id = ?', p.id);
  }

  await db.runAsync(
    "UPDATE inspection_records SET sync_status = 'synced', synced_at = datetime('now','localtime') WHERE id = ?",
    p.id,
  );
}

interface PullRow {
  id: number;
  status: string;
  reviewed_by_name: string | null;
  review_remarks: string | null;
  review_date: string | null;
  approved_by_name: string | null;
  approval_remarks: string | null;
  approval_date: string | null;
}

async function pullDecisions(): Promise<number> {
  const db = await getDb();
  const rows = await db.getAllAsync<{ web_id: number }>(
    "SELECT web_id FROM inspection_records WHERE is_demo = 0 AND web_id IS NOT NULL AND sync_status = 'synced'",
  );
  if (!rows.length) return 0;
  const ids = rows.map((r) => r.web_id);
  const res = await apiFetch<{ success: boolean; data: PullRow[] }>('/api/index.php?module=inspection&action=sync/pull', {
    method: 'POST',
    body: JSON.stringify({ ids }),
  });
  let applied = 0;
  for (const row of res.data ?? []) {
    await db.runAsync(
      `UPDATE inspection_records SET status = ?, reviewed_by = ?, review_remarks = ?, review_date = ?, approved_by = ?, approval_remarks = ?, approval_date = ?, updated_at = datetime('now','localtime') WHERE web_id = ?`,
      row.status,
      row.reviewed_by_name ?? null,
      row.review_remarks ?? null,
      row.review_date ?? null,
      row.approved_by_name ?? null,
      row.approval_remarks ?? null,
      row.approval_date ?? null,
      row.id,
    );
    applied++;
  }
  return applied;
}

async function pullTeamLeaders(): Promise<number> {
  const res = await apiGetTeamLeaders();
  await upsertTeamLeaders(res.data ?? []);
  return (res.data ?? []).length;
}

interface TemplateRow {
  id: number;
  category: string;
  item_text: string;
  item_type: string;
  sort_order: number;
}

async function pullTemplate(): Promise<number> {
  const res = await apiFetch<{ success: boolean; categories: string[]; data: Record<string, TemplateRow[]> }>(
    '/api/index.php?module=inspection&action=template',
  );
  const rows: TemplateRow[] = [];
  for (const cat of Object.keys(res.data ?? {})) {
    for (const it of res.data[cat] ?? []) {
      if (it?.id && it.category && it.item_text) rows.push(it);
    }
  }
  if (!rows.length) return 0;
  await upsertTemplate(rows);
  return rows.length;
}

export async function runSync(): Promise<SyncSummary> {
  if (syncRunning) return { pushed: 0, pulled: 0, errors: 0, offline: false };
  syncRunning = true;
  try {
    if (!getAuthToken()) return { pushed: 0, pulled: 0, errors: 0, offline: true };
    try {
      await apiFetch<{ success: boolean }>('/api/index.php?module=inspection&action=ai-status');
    } catch {
      return { pushed: 0, pulled: 0, errors: 0, offline: true };
    }

    let pushed = 0;
    let errors = 0;
    const db = await getDb();
    const pendings = await db.getAllAsync<{ id: number; web_id: number | null; status: string; review_pending: number }>(
      "SELECT id, web_id, status, review_pending FROM inspection_records WHERE is_demo = 0 AND sync_status IN ('pending','error') ORDER BY id",
    );
    for (const p of pendings) {
      try {
        await pushRecord(p);
        pushed++;
      } catch (err) {
        if (err instanceof ApiError && err.status === 0) {
          return { pushed, pulled: 0, errors, offline: true };
        }
        errors++;
      }
    }

    let pulled = 0;
    try {
      pulled = await pullDecisions();
    } catch (err) {
      if (err instanceof ApiError && err.status === 0) {
        return { pushed, pulled, errors, offline: true };
      }
      errors++;
    }

    try {
      await pullTeamLeaders();
    } catch (err) {
      if (err instanceof ApiError && err.status === 0) {
        return { pushed, pulled, errors, offline: true };
      }
      errors++;
    }

    try {
      await pullTemplate();
    } catch (err) {
      if (err instanceof ApiError && err.status === 0) {
        return { pushed, pulled, errors, offline: true };
      }
      errors++;
    }

    if (pushed > 0 || pulled > 0 || errors > 0) emitSync();
    return { pushed, pulled, errors, offline: false };
  } finally {
    syncRunning = false;
  }
}
