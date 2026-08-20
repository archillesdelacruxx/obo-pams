export interface User {
  id: number;
  full_name: string;
  username: string;
  email: string | null;
  profile_photo: string | null;
  role: string;
  is_admin: boolean;
}

export interface LoginResponse {
  success: boolean;
  token: string;
  expires_at: string;
  user: User;
  permissions: string[];
}

export interface LoginError {
  success?: boolean;
  error: string;
  locked?: boolean;
  locked_until?: number | null;
}

export interface InspectionStats {
  drafts: number;
  under_review: number;
  done: number;
}

export interface InspectionRecord {
  id: number;
  inspection_no: string;
  application_no: string;
  permit_no: string | null;
  project_title: string;
  project_location: string | null;
  owner_representative: string | null;
  contact_number: string | null;
  inspection_date: string | null;
  status: string;
  inspector_name?: string | null;
  created_at: string;
  reviewed_by?: string | null;
  review_remarks?: string | null;
  review_date?: string | null;
  approved_by?: string | null;
  approval_remarks?: string | null;
  approval_date?: string | null;
  web_id?: number | null;
  sync_status?: string;
  synced_at?: string | null;
  is_demo?: number;
}

export interface ListResponse<T> {
  success: boolean;
  data: T[];
  total?: number;
  page?: number;
  per_page?: number;
}

export interface InspectionReportRow extends InspectionRecord {
  team_leader_1_name?: string | null;
  team_leader_2_name?: string | null;
  inspection_team?: string | null;
}

/* ---------------------------------------------------------------------------
   INSPECTION CHECKLIST
   --------------------------------------------------------------------------- */

export type InspectionStatus =
  | 'Draft'
  | 'Under Review'
  | 'Approved'
  | 'Completed'
  | 'Rejected';

export type ItemResult = 'Pass' | 'Fail' | 'N/A';
export type ItemType = 'checkbox' | 'radio';

export interface TemplateItem {
  id: number;
  category: string;
  item_text: string;
  item_type: ItemType;
  sort_order: number;
}

export interface TemplateResponse {
  success: boolean;
  categories: string[];
  data: Record<string, TemplateItem[]>;
}

export interface InspectionResultRow {
  template_item_id: number;
  category: string;
  item_text: string;
  item_type: ItemType;
  result: ItemResult;
  remarks: string;
}

export interface InspectionPhoto {
  id: number;
  file_path: string;
  caption: string | null;
}

export interface InspectionRecordDetail extends InspectionRecord {
  schedule_id: number | null;
  permit_date_issued: string | null;
  project_contractor: string | null;
  project_engineer: string | null;
  inspection_team: string | null;
  inspection_type: string | null;
  inspection_result: string | null;
  time_started: string | null;
  time_finished: string | null;
  physical_accomplishment: number | null;
  mech_accomplishment: number | null;
  extra_fields: Record<string, unknown>;
  overall_findings: string | null;
  recommendations: string | null;
  completion_percentage: number | null;
  inspector_id: number;
  team_leader_1: number | null;
  team_leader_1_name?: string | null;
  team_leader_1_position?: string | null;
  team_leader_2: number | null;
  team_leader_2_name?: string | null;
  team_leader_2_position?: string | null;
  date_reinspected: string | null;
  results: InspectionResultRow[];
  photos: InspectionPhoto[];
}

export interface ChecklistResultItem {
  template_item_id: number;
  category: string;
  item_text: string;
  item_type: ItemType;
  result: ItemResult;
  remarks: string;
}

export interface ExtraFields {
  setbacks?: Record<string, string>;
  pct?: Record<string, string>;
  remarks?: Record<string, string>;
  others?: string;
  floorLevel?: string;
}

export interface ChecklistPayload {
  id?: number;
  application_no?: string;
  permit_no?: string;
  permit_date_issued?: string | null;
  project_title: string;
  project_location?: string;
  owner_representative?: string;
  contact_number?: string;
  project_contractor?: string;
  project_engineer?: string;
  inspection_team?: string;
  inspection_date?: string;
  inspection_type?: string;
  inspection_result?: string | null;
  time_started?: string | null;
  time_finished?: string | null;
  physical_accomplishment?: number | null;
  mech_accomplishment?: number | null;
  extra_fields?: ExtraFields | null;
  overall_findings?: string;
  recommendations?: string;
  completion_percentage?: number | null;
  team_leader_1?: number | null;
  team_leader_2?: number | null;
  date_reinspected?: string | null;
  results: ChecklistResultItem[];
}

export interface TeamLeader {
  id: number;
  full_name: string;
  position: string | null;
  team_no: number;
}

export interface AiStatusResponse {
  success: boolean;
  ai_enabled: boolean;
}

export interface AiRemarkResponse {
  success: boolean;
  ai_enabled?: boolean;
  summary?: string;
  message?: string;
  error?: string;
}
