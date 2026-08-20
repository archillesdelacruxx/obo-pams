import { useCallback, useEffect, useState } from 'react';
import type {
  ChecklistPayload,
  ChecklistResultItem,
  ExtraFields,
  InspectionPhoto,
  InspectionRecordDetail,
  ItemResult,
  TeamLeader,
  TemplateItem,
} from '../../types';
import { apiGetAiStatus, apiRemarkAi } from '../../api/inspection';
import { useAuth } from '../../context/AuthContext';
import { scheduleSync } from '../../db/sync';
import {
  addPhoto as repoAddPhoto,
  createRecord as repoCreateRecord,
  getRecord as repoGetRecord,
  getTeamLeaders as repoGetTeamLeaders,
  getTemplate as repoGetTemplate,
  removePhoto as repoRemovePhoto,
  submitRecord as repoSubmitRecord,
  updateRecord as repoUpdateRecord,
} from '../../db/inspectionRepo';

export interface ProjectInfo {
  teamLeader1: number | null;
  teamLeader2: number | null;
  inspectionDate: string;
  inspectionType: string;
  inspectionTypeOther: string;
  applicationNo: string;
  permitNo: string;
  permitDateIssued: string | null;
  projectTitle: string;
  physical: string;
  owner: string;
  contact: string;
  engineer: string;
  location: string;
  timeStarted: string | null;
  timeFinished: string | null;
  dateReinspected: string | null;
  overallFindings: string;
  recommendations: string;
}

export const EMPTY_INFO: ProjectInfo = {
  teamLeader1: null,
  teamLeader2: null,
  inspectionDate: '',
  inspectionType: '',
  inspectionTypeOther: '',
  applicationNo: '',
  permitNo: '',
  permitDateIssued: null,
  projectTitle: '',
  physical: '',
  owner: '',
  contact: '',
  engineer: '',
  location: '',
  timeStarted: null,
  timeFinished: null,
  dateReinspected: null,
  overallFindings: '',
  recommendations: '',
};

function pad(n: number): string {
  return n < 10 ? `0${n}` : String(n);
}

export function todayStr(): string {
  const d = new Date();
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

function hydrateInfo(detail: InspectionRecordDetail): ProjectInfo {
  const it = detail.inspection_type ?? '';
  return {
    teamLeader1: detail.team_leader_1,
    teamLeader2: detail.team_leader_2,
    inspectionDate: detail.inspection_date ?? todayStr(),
    inspectionType: it.startsWith('Others') ? 'Others' : it,
    inspectionTypeOther: it.startsWith('Others') ? it.replace(/^Others\s*-\s*/, '') : '',
    applicationNo: detail.application_no ?? '',
    permitNo: detail.permit_no ?? '',
    permitDateIssued: detail.permit_date_issued,
    projectTitle: detail.project_title ?? '',
    physical: detail.physical_accomplishment != null ? String(detail.physical_accomplishment) : '',
    owner: detail.owner_representative ?? '',
    contact: detail.contact_number ?? '',
    engineer: detail.project_engineer ?? '',
    location: detail.project_location ?? '',
    timeStarted: detail.time_started,
    timeFinished: detail.time_finished,
    dateReinspected: detail.date_reinspected ?? null,
    overallFindings: detail.overall_findings ?? '',
    recommendations: detail.recommendations ?? '',
  };
}

export interface UseInspectionForm {
  loading: boolean;
  saving: boolean;
  submitting: boolean;
  recordId: number | null;
  inspectionNo: string | null;
  status: string;
  editable: boolean;
  canSubmit: boolean;
  categories: string[];
  template: Record<string, TemplateItem[]>;
  teamLeaders: TeamLeader[];
  aiEnabled: boolean;
  aiLoading: Record<string, boolean>;
  info: ProjectInfo;
  updateInfo: (patch: Partial<ProjectInfo>) => void;
  results: Record<number, ItemResult>;
  setResult: (itemId: number, value: ItemResult) => void;
  extra: ExtraFields;
  patchExtra: (patch: Partial<ExtraFields>) => void;
  pct: Record<string, string>;
  setPctFor: (category: string, v: string) => void;
  remarks: Record<string, string>;
  setRemarkFor: (category: string, v: string) => void;
  photos: InspectionPhoto[];
  addPhoto: (asset: { uri: string; fileName?: string | null; mimeType?: string | null }) => Promise<void>;
  removePhoto: (photoId: number) => Promise<void>;
  saveDraft: () => Promise<number | null>;
  submit: () => Promise<boolean>;
  aiFor: (category: string) => Promise<void>;
}

export default function useInspectionForm(id?: number): UseInspectionForm {
  const { user, permissions } = useAuth();
  const canEditAll = permissions.includes('inspection-edit');
  const [loading, setLoading] = useState(id != null);
  const [saving, setSaving] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [recordId, setRecordId] = useState<number | null>(id ?? null);
  const [inspectionNo, setInspectionNo] = useState<string | null>(null);
  const [status, setStatus] = useState('Draft');
  const [categories, setCategories] = useState<string[]>([]);
  const [template, setTemplate] = useState<Record<string, TemplateItem[]>>({});
  const [teamLeaders, setTeamLeaders] = useState<TeamLeader[]>([]);
  const [aiEnabled, setAiEnabled] = useState(false);
  const [aiLoading, setAiLoading] = useState<Record<string, boolean>>({});
  const [info, setInfo] = useState<ProjectInfo>(EMPTY_INFO);
  const [results, setResults] = useState<Record<number, ItemResult>>({});
  const [extra, setExtra] = useState<ExtraFields>({});
  const [pct, setPct] = useState<Record<string, string>>({});
  const [remarks, setRemarks] = useState<Record<string, string>>({});
  const [photos, setPhotos] = useState<InspectionPhoto[]>([]);

  const editable = canEditAll || status === 'Draft' || status === 'Rejected';
  const canSubmit = status === 'Draft' || status === 'Rejected';

  useEffect(() => {
    let active = true;
    (async () => {
      try {
        const [tpl, tl] = await Promise.all([repoGetTemplate(), repoGetTeamLeaders()]);
        if (!active) return;
        setCategories(tpl.categories ?? []);
        setTemplate(tpl.data ?? {});
        setTeamLeaders(tl ?? []);
        apiGetAiStatus()
          .then((r) => active && setAiEnabled(!!r.ai_enabled))
          .catch(() => undefined);

        if (id) {
          const d = await repoGetRecord(id);
          if (!active || !d) return;
          setInspectionNo(d.inspection_no);
          setStatus(d.status);
          setInfo(hydrateInfo(d));
          const ef = d.extra_fields ?? {};
          setPct((ef.pct as Record<string, string>) ?? {});
          setRemarks((ef.remarks as Record<string, string>) ?? {});
          setExtra({
            setbacks: (ef.setbacks as Record<string, string> | undefined) ?? undefined,
            floorLevel: (ef.floorLevel as string | undefined) ?? undefined,
            others: (ef.others as string | undefined) ?? undefined,
          });
          const map: Record<number, ItemResult> = {};
          for (const r of d.results) map[r.template_item_id] = r.result;
          setResults(map);
          setPhotos(d.photos ?? []);
        }
      } catch {
        /* screen shows retry via loading reset below */
      } finally {
        if (active) setLoading(false);
      }
    })();
    return () => {
      active = false;
    };
  }, [id]);

  const updateInfo = useCallback((patch: Partial<ProjectInfo>) => {
    setInfo((prev) => ({ ...prev, ...patch }));
  }, []);

  const setResult = useCallback((itemId: number, value: ItemResult) => {
    setResults((prev) => ({ ...prev, [itemId]: value }));
  }, []);

  const patchExtra = useCallback((patch: Partial<ExtraFields>) => {
    setExtra((prev) => ({ ...prev, ...patch }));
  }, []);

  const setPctFor = useCallback((category: string, v: string) => {
    setPct((prev) => ({ ...prev, [category]: v }));
  }, []);

  const setRemarkFor = useCallback((category: string, v: string) => {
    setRemarks((prev) => ({ ...prev, [category]: v }));
  }, []);

  const buildPayload = useCallback((): ChecklistPayload => {
    const items: ChecklistResultItem[] = [];
    for (const cat of categories) {
      for (const it of template[cat] ?? []) {
        items.push({
          template_item_id: it.id,
          category: cat,
          item_text: it.item_text,
          item_type: it.item_type,
          result: results[it.id] ?? 'N/A',
          remarks: '',
        });
      }
    }
    const pctVals = Object.values(pct)
      .map((v) => Number(v))
      .filter((n) => !Number.isNaN(n));
    const completion = pctVals.length ? Math.round(pctVals.reduce((a, b) => a + b, 0) / pctVals.length) : null;
    const inspectionType =
      info.inspectionType === 'Others' && info.inspectionTypeOther.trim()
        ? `${info.inspectionType} - ${info.inspectionTypeOther.trim()}`
        : info.inspectionType;
    return {
      ...(recordId ? { id: recordId } : {}),
      application_no: info.applicationNo,
      permit_no: info.permitNo,
      permit_date_issued: info.permitDateIssued,
      project_title: info.projectTitle,
      project_location: info.location,
      owner_representative: info.owner,
      contact_number: info.contact,
      project_engineer: info.engineer,
      inspection_date: info.inspectionDate || todayStr(),
      inspection_type: inspectionType,
      time_started: info.timeStarted,
      time_finished: info.timeFinished,
      physical_accomplishment: info.physical !== '' ? Number(info.physical) : null,
      team_leader_1: info.teamLeader1,
      team_leader_2: info.teamLeader2,
      date_reinspected: info.dateReinspected,
      overall_findings: info.overallFindings,
      recommendations: info.recommendations,
      completion_percentage: completion,
      extra_fields: { ...extra, pct, remarks },
      results: items,
    };
  }, [categories, template, results, pct, remarks, extra, info, recordId]);

  const saveAll = useCallback(async (): Promise<number> => {
    if (!editable) throw new Error('This inspection can no longer be edited.');
    const payload = buildPayload();
    if (recordId) {
      await repoUpdateRecord({ ...payload, id: recordId });
      scheduleSync();
      return recordId;
    }
    const res = await repoCreateRecord(payload, {
      inspectorId: user?.id ?? 0,
      inspectorName: user?.full_name ?? '',
    });
    setRecordId(res.id);
    setInspectionNo(res.inspection_no);
    scheduleSync();
    return res.id;
  }, [buildPayload, recordId, user?.id, user?.full_name, editable]);

  const saveDraft = useCallback(async (): Promise<number | null> => {
    setSaving(true);
    try {
      return await saveAll();
    } finally {
      setSaving(false);
    }
  }, [saveAll]);

  const submit = useCallback(async (): Promise<boolean> => {
    setSubmitting(true);
    try {
      const targetId = await saveAll();
      await repoSubmitRecord(targetId);
      setStatus('Under Review');
      scheduleSync();
      return true;
    } finally {
      setSubmitting(false);
    }
  }, [saveAll]);

  const aiFor = useCallback(
    async (category: string) => {
      const items = (template[category] ?? []).map((it) => ({
        item_text: it.item_text,
        result: results[it.id] ?? 'N/A',
      }));
      if (!items.length) return;
      setAiLoading((prev) => ({ ...prev, [category]: true }));
      try {
        const res = await apiRemarkAi(category, items);
        if (res.summary) setRemarkFor(category, res.summary);
      } finally {
        setAiLoading((prev) => ({ ...prev, [category]: false }));
      }
    },
    [template, results, setRemarkFor],
  );

  const addPhoto = useCallback(
    async (asset: { uri: string; fileName?: string | null; mimeType?: string | null }) => {
      const targetId = recordId ?? (await saveAll());
      console.log('[useInspectionForm] addPhoto targetId=', targetId, 'recordId=', recordId);
      const photo = await repoAddPhoto(targetId, asset);
      console.log('[useInspectionForm] addPhoto saved photo.id=', photo.id, 'file_path=', photo.file_path);
      setPhotos((prev) => [...prev, photo]);
    },
    [recordId, saveAll],
  );

  const removePhoto = useCallback(async (photoId: number) => {
    await repoRemovePhoto(photoId);
    setPhotos((prev) => prev.filter((p) => p.id !== photoId));
  }, []);

  return {
    loading,
    saving,
    submitting,
    recordId,
    inspectionNo,
    status,
    editable,
    canSubmit,
    categories,
    template,
    teamLeaders,
    aiEnabled,
    aiLoading,
    info,
    updateInfo,
    results,
    setResult,
    extra,
    patchExtra,
    pct,
    setPctFor,
    remarks,
    setRemarkFor,
    photos,
    addPhoto,
    removePhoto,
    saveDraft,
    submit,
    aiFor,
  };
}
