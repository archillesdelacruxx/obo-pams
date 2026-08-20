import { File, UploadType } from 'expo-file-system';
import { getApiBaseUrl } from '../config';
import { apiFetch, ApiError, getAuthToken } from '../api/client';
import { getDb } from './database';
import { getRecord, importServerRecord, attachServerPhotos, upsertTeamLeaders, upsertTemplate } from './inspectionRepo';
import { apiGetTeamLeaders } from '../api/inspection';
import { notifyStatusChange, type StatusChangeNotification } from '../notifications';

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
        `${getApiBaseUrl()}/api/index.php?module=inspection&action=photos/upload`,
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
    date_reinspected: detail.date_reinspected,
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
  let serverTerminal = false;
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
    try {
      await apiFetch<{ success: boolean }>('/api/index.php?module=inspection&action=checklist/update', {
        method: 'POST',
        body: JSON.stringify({ ...base, id: webId }),
      });
    } catch (err) {
      if (err instanceof ApiError && err.status === 422) {
        serverTerminal = true;
      } else {
        throw err;
      }
    }
  }

  await pushPhotos(p.id, webId);

  if (!serverTerminal && p.status === 'Under Review') {
    try {
      await apiFetch<{ success: boolean }>('/api/index.php?module=inspection&action=checklist/submit', {
        method: 'POST',
        body: JSON.stringify({ id: webId }),
      });
    } catch (err) {
      if (!(err instanceof ApiError) || err.status !== 422) throw err;
    }
  }

  if (!serverTerminal && p.review_pending === 1 && (detail.status === 'Approved' || detail.status === 'Rejected')) {
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

async function pullDecisions(): Promise<{ applied: number; changes: StatusChangeNotification[] }> {
  const db = await getDb();
  const rows = await db.getAllAsync<{ web_id: number; status: string; application_no: string; project_title: string }>(
    "SELECT web_id, status, application_no, project_title FROM inspection_records WHERE is_demo = 0 AND web_id IS NOT NULL AND sync_status != 'error'",
  );
  if (!rows.length) return { applied: 0, changes: [] };
  const ids = rows.map((r) => r.web_id);
  const oldByWeb = new Map<number, { status: string; applicationNo: string; projectTitle: string }>();
  for (const r of rows) {
    oldByWeb.set(r.web_id, { status: r.status, applicationNo: r.application_no, projectTitle: r.project_title });
  }
  const res = await apiFetch<{ success: boolean; data: PullRow[] }>('/api/index.php?module=inspection&action=sync/pull', {
    method: 'POST',
    body: JSON.stringify({ ids }),
  });
  let applied = 0;
  const changes: StatusChangeNotification[] = [];
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
    const old = oldByWeb.get(row.id);
    if (old && (row.status === 'Approved' || row.status === 'Rejected') && old.status !== row.status) {
      changes.push({
        applicationNo: old.applicationNo,
        projectTitle: old.projectTitle,
        status: row.status,
      });
    }
  }
  return { applied, changes };
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

/* ---------------------------------------------------------------------------
   RESTORE — pull the caller's full inspection records back from the server
   (after a reinstall / fresh DB) so previously synced inspections reappear.
   Server-relative photo paths are stored as-is; resolvePhotoUri() serves them
   straight from the web server.
   --------------------------------------------------------------------------- */

interface ServerPhoto {
  id: number;
  file_path: string;
  caption: string | null;
}

interface ServerResult {
  template_item_id: number;
  category: string;
  item_text: string;
  item_type: string;
  result: string;
  remarks: string;
}

interface ServerRecord {
  id: number;
  inspection_no: string;
  application_no: string;
  permit_no: string | null;
  permit_date_issued: string | null;
  project_title: string;
  project_location: string | null;
  owner_representative: string | null;
  contact_number: string | null;
  project_contractor: string | null;
  project_engineer: string | null;
  inspection_team: string | null;
  inspection_date: string | null;
  inspection_type: string | null;
  inspection_result: string | null;
  time_started: string | null;
  time_finished: string | null;
  physical_accomplishment: number | null;
  mech_accomplishment: number | null;
  extra_fields: Record<string, unknown> | null;
  overall_findings: string | null;
  recommendations: string | null;
  completion_percentage: number | null;
  status: string;
  inspector_id: number;
  inspector_name: string | null;
  team_leader_1: number | null;
  team_leader_2: number | null;
  date_reinspected: string | null;
  reviewed_by_name?: string | null;
  review_remarks?: string | null;
  review_date?: string | null;
  approved_by_name?: string | null;
  approval_remarks?: string | null;
  approval_date?: string | null;
  results: ServerResult[];
  photos: ServerPhoto[];
}

async function pullMyRecords(): Promise<number> {
  const res = await apiFetch<{ success: boolean; data: ServerRecord[] }>(
    '/api/index.php?module=inspection&action=sync/pull-records',
  );
  let imported = 0;
  for (const rec of res.data ?? []) {
    const result = await importServerRecord({
      id: rec.id,
      inspection_no: rec.inspection_no,
      application_no: rec.application_no ?? '',
      permit_no: rec.permit_no,
      permit_date_issued: rec.permit_date_issued,
      project_title: rec.project_title,
      project_location: rec.project_location,
      owner_representative: rec.owner_representative,
      contact_number: rec.contact_number,
      project_contractor: rec.project_contractor,
      project_engineer: rec.project_engineer,
      inspection_team: rec.inspection_team,
      inspection_date: rec.inspection_date,
      inspection_type: rec.inspection_type,
      inspection_result: rec.inspection_result,
      time_started: rec.time_started,
      time_finished: rec.time_finished,
      physical_accomplishment: rec.physical_accomplishment != null ? Number(rec.physical_accomplishment) : null,
      mech_accomplishment: rec.mech_accomplishment != null ? Number(rec.mech_accomplishment) : null,
      extra_fields: rec.extra_fields ?? {},
      overall_findings: rec.overall_findings,
      recommendations: rec.recommendations,
      completion_percentage: rec.completion_percentage != null ? Number(rec.completion_percentage) : null,
      status: rec.status,
      inspector_id: rec.inspector_id,
      inspector_name: rec.inspector_name,
      team_leader_1: rec.team_leader_1,
      team_leader_2: rec.team_leader_2,
      date_reinspected: rec.date_reinspected,
      reviewed_by_name: rec.reviewed_by_name,
      review_remarks: rec.review_remarks,
      review_date: rec.review_date,
      approved_by_name: rec.approved_by_name,
      approval_remarks: rec.approval_remarks,
      approval_date: rec.approval_date,
      results: (rec.results ?? []).map((r) => ({
        template_item_id: r.template_item_id,
        category: r.category,
        item_text: r.item_text,
        item_type: r.item_type === 'checkbox' ? 'checkbox' : 'radio',
        result: (['Pass', 'Fail', 'N/A'] as const).includes(r.result as 'Pass' | 'Fail' | 'N/A')
          ? (r.result as 'Pass' | 'Fail' | 'N/A')
          : 'Pass',
        remarks: r.remarks ?? '',
      })),
    });
    if (result.imported && result.recordId != null && rec.photos?.length) {
      await attachServerPhotos(
        result.recordId,
        rec.photos.map((p) => ({ id: p.id, file_path: p.file_path, caption: p.caption })),
      );
    }
    imported += result.imported ? 1 : 0;
  }
  return imported;
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
    const changes: StatusChangeNotification[] = [];
    try {
      const result = await pullDecisions();
      pulled = result.applied;
      changes.push(...result.changes);
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

    /* Restore the inspector's records on a fresh install / empty DB. */
    try {
      const restored = await pullMyRecords();
      pulled += restored;
    } catch (err) {
      if (err instanceof ApiError && err.status === 0) {
        return { pushed, pulled, errors, offline: true };
      }
      errors++;
    }

    if (pushed > 0 || pulled > 0 || errors > 0) emitSync();
    for (const c of changes) {
      void notifyStatusChange(c);
    }
    return { pushed, pulled, errors, offline: false };
  } finally {
    syncRunning = false;
  }
}
