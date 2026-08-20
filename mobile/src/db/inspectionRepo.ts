import { Directory, File, Paths } from 'expo-file-system';
import { getDb } from './database';
import { SEED_CATEGORIES } from './seedData';
import type {
  ChecklistPayload,
  InspectionPhoto,
  InspectionRecord,
  InspectionRecordDetail,
  InspectionReportRow,
  InspectionResultRow,
  InspectionStats,
  ItemResult,
  TeamLeader,
  TemplateItem,
  TemplateResponse,
} from '../types';

const RECORD_SELECT = `
  SELECT r.*,
    tl1.full_name AS team_leader_1_name, tl1.position AS team_leader_1_position,
    tl2.full_name AS team_leader_2_name, tl2.position AS team_leader_2_position
  FROM inspection_records r
  LEFT JOIN team_leaders tl1 ON tl1.id = r.team_leader_1
  LEFT JOIN team_leaders tl2 ON tl2.id = r.team_leader_2
`;

const RECORD_COLUMNS = [
  'application_no',
  'permit_no',
  'permit_date_issued',
  'project_title',
  'project_location',
  'owner_representative',
  'contact_number',
  'project_contractor',
  'project_engineer',
  'inspection_team',
  'inspection_date',
  'inspection_type',
  'inspection_result',
  'time_started',
  'time_finished',
  'physical_accomplishment',
  'mech_accomplishment',
  'extra_fields',
  'overall_findings',
  'recommendations',
  'completion_percentage',
  'team_leader_1',
  'team_leader_2',
  'date_reinspected',
] as const;

function recordValues(payload: ChecklistPayload): (string | number | null)[] {
  return [
    payload.application_no ?? '',
    payload.permit_no ?? null,
    payload.permit_date_issued ?? null,
    payload.project_title,
    payload.project_location ?? null,
    payload.owner_representative ?? null,
    payload.contact_number ?? null,
    payload.project_contractor ?? null,
    payload.project_engineer ?? null,
    payload.inspection_team ?? null,
    payload.inspection_date ?? null,
    payload.inspection_type ?? null,
    payload.inspection_result ?? null,
    payload.time_started ?? null,
    payload.time_finished ?? null,
    payload.physical_accomplishment ?? null,
    payload.mech_accomplishment ?? null,
    JSON.stringify(payload.extra_fields ?? {}),
    payload.overall_findings ?? null,
    payload.recommendations ?? null,
    payload.completion_percentage ?? null,
    payload.team_leader_1 ?? null,
    payload.team_leader_2 ?? null,
    payload.date_reinspected ?? null,
  ];
}

async function replaceResults(recordId: number, results: ChecklistPayload['results']): Promise<void> {
  const db = await getDb();
  await db.runAsync('DELETE FROM inspection_results WHERE record_id = ?', recordId);
  for (const item of results ?? []) {
    await db.runAsync(
      'INSERT INTO inspection_results (record_id, template_item_id, category, item_text, item_type, result, remarks) VALUES (?,?,?,?,?,?,?)',
      recordId,
      item.template_item_id,
      item.category,
      item.item_text,
      item.item_type,
      item.result,
      item.remarks ?? '',
    );
  }
}

function parseExtra(json: string | null): Record<string, unknown> {
  if (!json) return {};
  try {
    return JSON.parse(json);
  } catch {
    return {};
  }
}

export async function getTemplate(): Promise<TemplateResponse> {
  const db = await getDb();
  const rows = await db.getAllAsync<{ id: number; category: string; item_text: string; item_type: string; sort_order: number }>(
    'SELECT id, category, item_text, item_type, sort_order FROM template_items ORDER BY sort_order, id',
  );
  const data: Record<string, TemplateItem[]> = {};
  for (const cat of SEED_CATEGORIES) data[cat] = [];
  for (const row of rows) {
    const item: TemplateItem = {
      id: row.id,
      category: row.category,
      item_text: row.item_text,
      item_type: row.item_type as TemplateItem['item_type'],
      sort_order: row.sort_order,
    };
    if (data[row.category]) data[row.category].push(item);
    else data[row.category] = [item];
  }
  const categories = SEED_CATEGORIES.filter((c) => (data[c]?.length ?? 0) > 0);
  return { success: true, categories, data };
}

export async function upsertTemplate(
  items: { id: number; category: string; item_text: string; item_type: string; sort_order: number }[],
): Promise<void> {
  const db = await getDb();
  await db.withExclusiveTransactionAsync(async (txn) => {
    await txn.runAsync('DELETE FROM template_items');
    for (const it of items) {
      await txn.runAsync(
        'INSERT INTO template_items (id, category, item_text, item_type, sort_order) VALUES (?,?,?,?,?)',
        it.id,
        it.category,
        it.item_text,
        it.item_type,
        it.sort_order ?? 0,
      );
    }
  });
}

export async function getTeamLeaders(): Promise<TeamLeader[]> {
  const db = await getDb();
  return db.getAllAsync<TeamLeader>(
    'SELECT id, full_name, position, team_no FROM team_leaders WHERE is_active = 1 ORDER BY team_no, id',
  );
}

export async function upsertTeamLeaders(
  leaders: { id: number; full_name: string; position: string | null; team_no: number }[],
): Promise<void> {
  const db = await getDb();
  await db.withExclusiveTransactionAsync(async (txn) => {
    for (const l of leaders) {
      await txn.runAsync(
        `INSERT INTO team_leaders (id, full_name, position, team_no, is_active) VALUES (?,?,?,?,1)
         ON CONFLICT(id) DO UPDATE SET full_name = excluded.full_name, position = excluded.position, team_no = excluded.team_no, is_active = 1`,
        l.id,
        l.full_name,
        l.position,
        l.team_no,
      );
    }
    const rosterIds = leaders.map((l) => l.id);
    if (rosterIds.length > 0) {
      const placeholders = rosterIds.map(() => '?').join(',');
      await txn.runAsync(`UPDATE team_leaders SET is_active = 0 WHERE id NOT IN (${placeholders})`, rosterIds);
    } else {
      await txn.runAsync('UPDATE team_leaders SET is_active = 0');
    }
  });
}

export async function listRecords(params: { search?: string; status?: string } = {}): Promise<InspectionReportRow[]> {
  const db = await getDb();
  const where: string[] = [];
  const args: (string | number)[] = [];
  if (params.status) {
    where.push('r.status = ?');
    args.push(params.status);
  }
  if (params.search) {
    const like = `%${params.search}%`;
    where.push('(r.project_title LIKE ? OR r.application_no LIKE ? OR r.permit_no LIKE ? OR r.inspection_no LIKE ? OR r.project_location LIKE ?)');
    args.push(like, like, like, like, like);
  }
  const sql = `${RECORD_SELECT}${where.length ? ' WHERE ' + where.join(' AND ') : ''} ORDER BY r.inspection_date DESC, r.id DESC`;
  return db.getAllAsync<InspectionReportRow>(sql, args);
}

export async function getRecord(id: number): Promise<InspectionRecordDetail | null> {
  const db = await getDb();
  const row = await db.getFirstAsync<InspectionRecordDetail>(`${RECORD_SELECT} WHERE r.id = ?`, id);
  if (!row) return null;
  const results = await db.getAllAsync<
    { template_item_id: number; category: string; item_text: string; item_type: string; result: ItemResult; remarks: string }
  >('SELECT template_item_id, category, item_text, item_type, result, remarks FROM inspection_results WHERE record_id = ? ORDER BY id', id);
  const photos = await db.getAllAsync<InspectionPhoto>('SELECT id, file_path, caption FROM inspection_photos WHERE record_id = ? ORDER BY id', id);
  console.log('[getRecord] id=', id, 'photos_found=', photos.length, 'photos=', JSON.stringify(photos));
  return {
    ...row,
    schedule_id: null,
    extra_fields: parseExtra((row as unknown as { extra_fields: string }).extra_fields),
    results: results.map((r) => ({
      template_item_id: r.template_item_id,
      category: r.category,
      item_text: r.item_text,
      item_type: r.item_type as InspectionResultRow['item_type'],
      result: r.result,
      remarks: r.remarks,
    })),
    photos,
  };
}

export async function createRecord(
  payload: ChecklistPayload,
  ctx: { inspectorId: number; inspectorName: string },
): Promise<{ id: number; inspection_no: string }> {
  const db = await getDb();
  const inspectionNo = await nextInspectionNo();
  const values = recordValues(payload);
  let recordId = 0;
  await db.withExclusiveTransactionAsync(async (txn) => {
    const res = await txn.runAsync(
      `INSERT INTO inspection_records (inspection_no, ${RECORD_COLUMNS.join(', ')}, status, inspector_id, inspector_name) VALUES (?, ${RECORD_COLUMNS.map(() => '?').join(', ')}, 'Draft', ?, ?)`,
      [inspectionNo, ...values, ctx.inspectorId, ctx.inspectorName],
    );
    recordId = Number(res.lastInsertRowId);
    for (const item of payload.results ?? []) {
      await txn.runAsync(
        'INSERT INTO inspection_results (record_id, template_item_id, category, item_text, item_type, result, remarks) VALUES (?,?,?,?,?,?,?)',
        recordId,
        item.template_item_id,
        item.category,
        item.item_text,
        item.item_type,
        item.result,
        item.remarks ?? '',
      );
    }
  });
  return { id: recordId, inspection_no: inspectionNo };
}

export async function updateRecord(payload: ChecklistPayload & { id: number }): Promise<void> {
  const db = await getDb();
  const values = recordValues(payload);
  await db.withExclusiveTransactionAsync(async (txn) => {
    const sets = RECORD_COLUMNS.map((c) => `${c} = ?`).join(', ');
    await txn.runAsync(
      `UPDATE inspection_records SET ${sets}, sync_status = 'pending', synced_at = NULL, updated_at = datetime('now','localtime') WHERE id = ?`,
      [...values, payload.id],
    );
    await txn.runAsync('DELETE FROM inspection_results WHERE record_id = ?', payload.id);
    for (const item of payload.results ?? []) {
      await txn.runAsync(
        'INSERT INTO inspection_results (record_id, template_item_id, category, item_text, item_type, result, remarks) VALUES (?,?,?,?,?,?,?)',
        payload.id,
        item.template_item_id,
        item.category,
        item.item_text,
        item.item_type,
        item.result,
        item.remarks ?? '',
      );
    }
  });
}

export async function submitRecord(id: number): Promise<void> {
  const db = await getDb();
  await db.runAsync(
    "UPDATE inspection_records SET status = 'Under Review', sync_status = 'pending', synced_at = NULL, updated_at = datetime('now','localtime') WHERE id = ?",
    id,
  );
}

/* ---------------------------------------------------------------------------
   RESTORE — import a server record (with results) into the local DB.
   Skips records already present locally (matched by web_id). Used after a
   reinstall so an inspector's previously synced inspections come back.
   --------------------------------------------------------------------------- */

export interface ServerRecordInput {
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
  results: ChecklistPayload['results'];
}

export async function importServerRecord(rec: ServerRecordInput): Promise<{ imported: boolean; recordId: number | null }> {
  const db = await getDb();
  const existing = await db.getFirstAsync<{ id: number }>('SELECT id FROM inspection_records WHERE web_id = ?', rec.id);
  if (existing) return { imported: false, recordId: existing.id };

  const values = recordValues(rec as ChecklistPayload);
  let recordId = 0;
  await db.withExclusiveTransactionAsync(async (txn) => {
    const res = await txn.runAsync(
      `INSERT INTO inspection_records (
        inspection_no, ${RECORD_COLUMNS.join(', ')}, status, inspector_id, inspector_name,
        reviewed_by, review_date, review_remarks, approved_by, approval_date, approval_remarks,
        web_id, sync_status, synced_at, is_demo
      ) VALUES (?, ${RECORD_COLUMNS.map(() => '?').join(', ')}, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'synced', datetime('now','localtime'), 0)`,
      [
        rec.inspection_no,
        ...values,
        rec.status || 'Draft',
        rec.inspector_id ?? null,
        rec.inspector_name ?? null,
        rec.reviewed_by_name ?? null,
        rec.review_date ?? null,
        rec.review_remarks ?? null,
        rec.approved_by_name ?? null,
        rec.approval_date ?? null,
        rec.approval_remarks ?? null,
        rec.id,
      ],
    );
    recordId = Number(res.lastInsertRowId);
    for (const item of rec.results ?? []) {
      await txn.runAsync(
        'INSERT INTO inspection_results (record_id, template_item_id, category, item_text, item_type, result, remarks) VALUES (?,?,?,?,?,?,?)',
        recordId,
        item.template_item_id,
        item.category,
        item.item_text,
        item.item_type,
        item.result,
        item.remarks ?? '',
      );
    }
  });
  return { imported: true, recordId };
}

export async function attachServerPhotos(
  recordId: number,
  photos: { id: number; file_path: string; caption: string | null }[],
): Promise<void> {
  const db = await getDb();
  for (const p of photos) {
    await db.runAsync(
      "INSERT INTO inspection_photos (record_id, file_path, caption, web_photo_id, sync_status) VALUES (?,?,?,?,'synced')",
      recordId,
      p.file_path,
      p.caption ?? null,
      p.id,
    );
  }
}

export async function reviewRecord(
  id: number,
  action: 'review' | 'reject',
  remarks: string,
  actor: string,
): Promise<string> {
  const db = await getDb();
  const now = new Date().toISOString().slice(0, 19).replace('T', ' ');
  if (action === 'reject') {
    await db.runAsync(
      `UPDATE inspection_records SET status = 'Rejected', reviewed_by = ?, review_date = ?, review_remarks = ?, review_pending = 1, sync_status = 'pending', synced_at = NULL, updated_at = datetime('now','localtime') WHERE id = ?`,
      actor,
      now,
      remarks,
      id,
    );
    return 'Rejected';
  }
  await db.runAsync(
    `UPDATE inspection_records SET status = 'Approved', reviewed_by = ?, review_date = ?, review_remarks = ?, review_pending = 1, sync_status = 'pending', synced_at = NULL, updated_at = datetime('now','localtime') WHERE id = ?`,
    actor,
    now,
    remarks,
    id,
  );
  return 'Approved';
}

export async function deleteRecord(id: number): Promise<void> {
  const db = await getDb();
  const photos = await db.getAllAsync<{ file_path: string }>('SELECT file_path FROM inspection_photos WHERE record_id = ?', id);
  for (const p of photos) {
    try {
      const f = new File(p.file_path);
      if (f.exists) f.delete();
    } catch {
      /* best-effort cleanup */
    }
  }
  await db.withExclusiveTransactionAsync(async (txn) => {
    await txn.runAsync('DELETE FROM inspection_photos WHERE record_id = ?', id);
    await txn.runAsync('DELETE FROM inspection_results WHERE record_id = ?', id);
    await txn.runAsync('DELETE FROM inspection_records WHERE id = ?', id);
  });
}

export async function getStats(): Promise<InspectionStats> {
  const db = await getDb();
  const rows = await db.getAllAsync<{ status: string; c: number }>(
    'SELECT status, COUNT(*) AS c FROM inspection_records GROUP BY status',
  );
  let drafts = 0;
  let underReview = 0;
  let done = 0;
  for (const r of rows) {
    if (r.status === 'Draft') drafts = r.c;
    else if (r.status === 'Under Review') underReview = r.c;
    else if (r.status === 'Approved' || r.status === 'Completed') done += r.c;
  }
  return { drafts, under_review: underReview, done };
}

export async function getRecent(limit = 5): Promise<InspectionRecord[]> {
  const db = await getDb();
  return db.getAllAsync<InspectionRecord>(
    `SELECT id, inspection_no, application_no, permit_no, project_title, project_location, owner_representative, contact_number, inspection_date, status, inspector_name, created_at
     FROM inspection_records ORDER BY created_at DESC, id DESC LIMIT ?`,
    limit,
  );
}

type RecordWithPhotos = InspectionRecord & { photos: InspectionPhoto[] };

async function attachPhotos(records: InspectionRecord[]): Promise<RecordWithPhotos[]> {
  if (records.length === 0) return [];
  const db = await getDb();
  const ids = records.map((r) => r.id);
  const placeholders = ids.map(() => '?').join(',');
  const photoRows = await db.getAllAsync<{ record_id: number; id: number; file_path: string; caption: string | null }>(
    `SELECT record_id, id, file_path, caption FROM inspection_photos WHERE record_id IN (${placeholders}) ORDER BY id`,
    ids,
  );
  const byRecord = new Map<number, InspectionPhoto[]>();
  for (const p of photoRows) {
    const list = byRecord.get(p.record_id) ?? [];
    list.push({ id: p.id, file_path: p.file_path, caption: p.caption });
    byRecord.set(p.record_id, list);
  }
  return records.map((r) => ({ ...r, photos: byRecord.get(r.id) ?? [] }));
}

export async function getRecentWithPhotos(limit = 5): Promise<RecordWithPhotos[]> {
  const db = await getDb();
  const records = await db.getAllAsync<InspectionRecord>(
    `SELECT id, inspection_no, application_no, permit_no, project_title, project_location, owner_representative, contact_number, inspection_date, status, inspector_name, created_at
     FROM inspection_records ORDER BY created_at DESC, id DESC LIMIT ?`,
    limit,
  );
  return attachPhotos(records);
}

export async function getAllRecordsWithPhotos(): Promise<RecordWithPhotos[]> {
  const db = await getDb();
  const records = await db.getAllAsync<InspectionRecord>(
    `SELECT id, inspection_no, application_no, permit_no, project_title, project_location, owner_representative, contact_number, inspection_date, status, inspector_name, created_at
     FROM inspection_records ORDER BY inspection_date DESC, id DESC`,
  );
  return attachPhotos(records);
}

async function nextInspectionNo(): Promise<string> {
  const db = await getDb();
  const year = new Date().getFullYear();
  const prefix = `INS-${year}-`;
  const row = await db.getFirstAsync<{ inspection_no: string }>(
    'SELECT inspection_no FROM inspection_records WHERE inspection_no LIKE ? ORDER BY id DESC LIMIT 1',
    `${prefix}%`,
  );
  let seq = 0;
  if (row?.inspection_no) {
    const m = row.inspection_no.match(/(\d+)$/);
    seq = m ? parseInt(m[1], 10) : 0;
  }
  return `${prefix}${String(seq + 1).padStart(4, '0')}`;
}

/* ---------------------------------------------------------------------------
   PHOTOS (local file storage)
   --------------------------------------------------------------------------- */

function photosDirectory(): Directory {
  return new Directory(Paths.document, 'inspection_photos');
}

async function ensurePhotosDirectory(): Promise<void> {
  const dir = photosDirectory();
  if (!dir.exists) dir.create({ intermediates: true, idempotent: true });
}

function deriveExtension(fileName: string | null | undefined, mimeType: string | null | undefined): string {
  const fromName = fileName?.split('.').pop()?.toLowerCase();
  if (fromName && fromName.length <= 5 && /^[a-z0-9]+$/.test(fromName)) return `.${fromName}`;
  const mimeMap: Record<string, string> = {
    'image/jpeg': '.jpg',
    'image/png': '.png',
    'image/webp': '.webp',
    'image/heic': '.heic',
    'image/heif': '.heif',
  };
  if (mimeType && mimeMap[mimeType]) return mimeMap[mimeType];
  return '.jpg';
}

export async function addPhoto(
  recordId: number,
  asset: { uri: string; fileName?: string | null; mimeType?: string | null },
): Promise<InspectionPhoto> {
  await ensurePhotosDirectory();
  const ext = deriveExtension(asset.fileName, asset.mimeType);
  const name = `photo_${Date.now()}_${Math.round(Math.random() * 1e6)}${ext}`;
  const dest = new File(photosDirectory(), name);
  const src = new File(asset.uri);
  await src.copy(dest);
  const db = await getDb();
  const res = await db.runAsync('INSERT INTO inspection_photos (record_id, file_path, caption) VALUES (?,?,?)', recordId, dest.uri, null);
  return { id: Number(res.lastInsertRowId), file_path: dest.uri, caption: null };
}

export async function removePhoto(photoId: number): Promise<void> {
  const db = await getDb();
  const row = await db.getFirstAsync<{ file_path: string }>('SELECT file_path FROM inspection_photos WHERE id = ?', photoId);
  await db.runAsync('DELETE FROM inspection_photos WHERE id = ?', photoId);
  if (row?.file_path) {
    try {
      const f = new File(row.file_path);
      if (f.exists) f.delete();
    } catch {
      /* best-effort cleanup */
    }
  }
}
