import * as SQLite from 'expo-sqlite';
import type { SQLiteDatabase } from 'expo-sqlite';
import type { ItemResult } from '../types';
import { SEED_TEMPLATE_ITEMS, SEED_TEAM_LEADERS } from './seedData';

const DATABASE_VERSION = 4;
let dbPromise: Promise<SQLiteDatabase> | null = null;

export function getDb(): Promise<SQLiteDatabase> {
  if (!dbPromise) {
    dbPromise = SQLite.openDatabaseAsync('pams.db').then(async (db) => {
      await migrate(db);
      return db;
    });
  }
  return dbPromise;
}

async function ensureColumn(db: SQLiteDatabase, table: string, column: string, ddl: string): Promise<void> {
  const cols = await db.getAllAsync<{ name: string }>(`PRAGMA table_info(${table})`);
  if (!cols.some((c) => c.name === column)) {
    await db.execAsync(`ALTER TABLE ${table} ADD COLUMN ${ddl}`);
  }
}

async function migrate(db: SQLiteDatabase): Promise<void> {
  const { user_version } = (await db.getFirstAsync<{ user_version: number }>('PRAGMA user_version')) ?? {
    user_version: 0,
  };
  if (user_version >= DATABASE_VERSION) return;

  if (user_version === 0) {
    await db.execAsync(`
      PRAGMA journal_mode = 'wal';
      CREATE TABLE IF NOT EXISTS template_items (
        id INTEGER PRIMARY KEY NOT NULL,
        category TEXT NOT NULL,
        item_text TEXT NOT NULL,
        item_type TEXT NOT NULL DEFAULT 'checkbox',
        sort_order INTEGER NOT NULL DEFAULT 0
      );
      CREATE TABLE IF NOT EXISTS team_leaders (
        id INTEGER PRIMARY KEY NOT NULL,
        full_name TEXT NOT NULL,
        position TEXT,
        team_no INTEGER NOT NULL DEFAULT 0,
        is_active INTEGER NOT NULL DEFAULT 1
      );
      CREATE TABLE IF NOT EXISTS inspection_records (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        inspection_no TEXT NOT NULL,
        application_no TEXT NOT NULL DEFAULT '',
        permit_no TEXT,
        permit_date_issued TEXT,
        project_title TEXT NOT NULL DEFAULT '',
        project_location TEXT,
        owner_representative TEXT,
        contact_number TEXT,
        project_contractor TEXT,
        project_engineer TEXT,
        inspection_team TEXT,
        inspection_date TEXT,
        inspection_type TEXT,
        inspection_result TEXT,
        time_started TEXT,
        time_finished TEXT,
        physical_accomplishment REAL,
        mech_accomplishment REAL,
        extra_fields TEXT,
        overall_findings TEXT,
        recommendations TEXT,
        completion_percentage REAL,
        status TEXT NOT NULL DEFAULT 'Draft',
        inspector_id INTEGER,
        inspector_name TEXT,
        inspector_signature TEXT,
        team_leader_1 INTEGER,
        team_leader_2 INTEGER,
        reviewed_by TEXT,
        review_signature TEXT,
        review_date TEXT,
        review_remarks TEXT,
        approved_by TEXT,
        approval_signature TEXT,
        approval_date TEXT,
        approval_remarks TEXT,
        review_pending INTEGER NOT NULL DEFAULT 0,
        web_id INTEGER,
        sync_status TEXT NOT NULL DEFAULT 'pending',
        synced_at TEXT,
        is_demo INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL DEFAULT (datetime('now','localtime')),
        updated_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
      );
      CREATE TABLE IF NOT EXISTS inspection_results (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        record_id INTEGER NOT NULL,
        template_item_id INTEGER NOT NULL,
        category TEXT NOT NULL,
        item_text TEXT NOT NULL,
        item_type TEXT NOT NULL DEFAULT 'checkbox',
        result TEXT NOT NULL DEFAULT 'N/A',
        remarks TEXT NOT NULL DEFAULT ''
      );
      CREATE INDEX IF NOT EXISTS idx_results_record ON inspection_results(record_id);
      CREATE TABLE IF NOT EXISTS inspection_photos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        record_id INTEGER NOT NULL,
        file_path TEXT NOT NULL,
        caption TEXT,
        web_photo_id INTEGER,
        sync_status TEXT NOT NULL DEFAULT 'pending',
        created_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
      );
      CREATE INDEX IF NOT EXISTS idx_photos_record ON inspection_photos(record_id);
    `);

    for (const item of SEED_TEMPLATE_ITEMS) {
      await db.runAsync(
        'INSERT INTO template_items (id, category, item_text, item_type, sort_order) VALUES (?,?,?,?,?)',
        item.id,
        item.category,
        item.item_text,
        item.item_type,
        item.sort_order,
      );
    }
    for (const tl of SEED_TEAM_LEADERS) {
      await db.runAsync(
        'INSERT INTO team_leaders (id, full_name, position, team_no) VALUES (?,?,?,?)',
        tl.id,
        tl.full_name,
        tl.position,
        tl.team_no,
      );
    }

    await seedDemoRecords(db);
  }

  /* Sync columns (idempotent — applies to any pre-existing install). */
  await ensureColumn(db, 'inspection_records', 'web_id', 'web_id INTEGER');
  await ensureColumn(db, 'inspection_records', 'sync_status', "sync_status TEXT NOT NULL DEFAULT 'pending'");
  await ensureColumn(db, 'inspection_records', 'synced_at', 'synced_at TEXT');
  await ensureColumn(db, 'inspection_records', 'is_demo', 'is_demo INTEGER NOT NULL DEFAULT 0');
  await ensureColumn(db, 'inspection_records', 'review_pending', 'review_pending INTEGER NOT NULL DEFAULT 0');
  await ensureColumn(db, 'inspection_photos', 'web_photo_id', 'web_photo_id INTEGER');
  await ensureColumn(db, 'inspection_photos', 'sync_status', "sync_status TEXT NOT NULL DEFAULT 'pending'");
  await ensureColumn(db, 'team_leaders', 'is_active', 'is_active INTEGER NOT NULL DEFAULT 1');
  await db.runAsync("UPDATE inspection_records SET is_demo = 1 WHERE is_demo = 0 AND inspection_no IN ('INS-2026-0001','INS-2026-0002')");

  await db.execAsync(`PRAGMA user_version = ${DATABASE_VERSION}`);
}

interface DemoRecord {
  inspection_no: string;
  project_title: string;
  project_location: string;
  inspection_date: string;
  status: string;
  team_leader_1: number | null;
  team_leader_2: number | null;
  inspector_name: string;
  inspection_type: string;
  inspection_result: string;
  physical: number;
  completion: number;
  overall_findings: string;
  recommendations: string;
}

async function seedDemoRecords(db: SQLiteDatabase): Promise<void> {
  const { c } = (await db.getFirstAsync<{ c: number }>('SELECT COUNT(*) AS c FROM inspection_records')) ?? { c: 0 };
  if (c > 0) return;

  const demos: DemoRecord[] = [
    {
      inspection_no: 'INS-2026-0001',
      project_title: 'Sample Commercial Building (Demo 1)',
      project_location: 'Barangay San Isidro',
      inspection_date: '2026-08-10',
      status: 'Completed',
      team_leader_1: 1,
      team_leader_2: 2,
      inspector_name: 'John Dela Cruz',
      inspection_type: '1st',
      inspection_result: 'Passed',
      physical: 85,
      completion: 85,
      overall_findings: 'All structural and finishing works comply with the approved plan.',
      recommendations: 'Maintain cleanliness and housekeeping for the remainder of the project.',
    },
    {
      inspection_no: 'INS-2026-0002',
      project_title: 'Residential Dwelling Unit (Demo 2)',
      project_location: 'Barangay Obo Proper',
      inspection_date: '2026-08-12',
      status: 'Approved',
      team_leader_1: 1,
      team_leader_2: null,
      inspector_name: 'John Dela Cruz',
      inspection_type: '2nd',
      inspection_result: 'Ongoing',
      physical: 60,
      completion: 60,
      overall_findings: 'Most requirements are in place; a few minor items still need correction.',
      recommendations: 'Complete the remaining electrical grounding works before the next inspection.',
    },
  ];

  for (const demo of demos) {
    const results: { template_item_id: number; category: string; item_text: string; item_type: string; result: ItemResult }[] = [];
    for (const [idx, it] of SEED_TEMPLATE_ITEMS.entries()) {
      let result: ItemResult = 'Pass';
      if (idx % 7 === 3) result = 'N/A';
      else if (idx % 7 === 6) result = 'Fail';
      results.push({
        template_item_id: it.id,
        category: it.category,
        item_text: it.item_text,
        item_type: it.item_type,
        result,
      });
    }

    await db.withExclusiveTransactionAsync(async (txn) => {
      const res = await txn.runAsync(
        `INSERT INTO inspection_records (
          inspection_no, application_no, permit_no, permit_date_issued, project_title, project_location,
          owner_representative, contact_number, project_contractor, project_engineer, inspection_team,
          inspection_date, inspection_type, inspection_result, time_started, time_finished,
          physical_accomplishment, mech_accomplishment, extra_fields, overall_findings, recommendations,
          completion_percentage, status, inspector_id, inspector_name, team_leader_1, team_leader_2, is_demo
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)`,
        demo.inspection_no,
        '',
        'OBP-2026-0088',
        '2026-07-20',
        demo.project_title,
        demo.project_location,
        'Maria Santos',
        '0917-555-1234',
        'BuildRight Construction',
        'Eng. Roberto Cruz',
        null,
        demo.inspection_date,
        demo.inspection_type,
        demo.inspection_result,
        '09:00',
        '10:30',
        demo.physical,
        null,
        JSON.stringify({}),
        demo.overall_findings,
        demo.recommendations,
        demo.completion,
        demo.status,
        2,
        demo.inspector_name,
        demo.team_leader_1,
        demo.team_leader_2,
        1,
      );
      const recordId = Number(res.lastInsertRowId);
      for (const item of results) {
        await txn.runAsync(
          'INSERT INTO inspection_results (record_id, template_item_id, category, item_text, item_type, result, remarks) VALUES (?,?,?,?,?,?,?)',
          recordId,
          item.template_item_id,
          item.category,
          item.item_text,
          item.item_type,
          item.result,
          '',
        );
      }
    });
  }
}
