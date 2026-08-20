import React, { useCallback, useEffect, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  Image,
  Modal,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import { useFocusEffect } from '@react-navigation/native';
import { Ionicons } from '@expo/vector-icons';
import { SafeAreaView } from 'react-native-safe-area-context';
import { colors, fonts } from '../../theme/tokens';
import StatusPill from '../../components/StatusPill';
import {
  deleteRecord as repoDeleteRecord,
  getRecord as repoGetRecord,
  reviewRecord as repoReviewRecord,
} from '../../db/inspectionRepo';
import { scheduleSync, subscribeSync } from '../../db/sync';
import { useAuth } from '../../context/AuthContext';
import { resolvePhotoUri } from '../../utils/media';
import PhotoViewerModal from '../../components/PhotoViewerModal';
import type { InspectionRecordDetail, ItemResult } from '../../types';
import type { InspectionsStackParamList } from '../../navigation/types';

type Props = NativeStackScreenProps<InspectionsStackParamList, 'InspectionDetail'>;

const RESULT_STYLE: Record<ItemResult, { bg: string; fg: string }> = {
  Pass: { bg: colors.successLight, fg: colors.success },
  Fail: { bg: colors.dangerLight, fg: colors.danger },
  'N/A': { bg: colors.gray100, fg: colors.gray500 },
};

export default function InspectionDetailScreen({ route, navigation }: Props) {
  const { id } = route.params;
  const { permissions, user } = useAuth();
  const [detail, setDetail] = useState<InspectionRecordDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [reviewOpen, setReviewOpen] = useState(false);
  const [reviewAction, setReviewAction] = useState<'review' | 'reject'>('review');
  const [remarks, setRemarks] = useState('');
  const [busy, setBusy] = useState(false);
  const [viewerOpen, setViewerOpen] = useState(false);
  const [viewerIndex, setViewerIndex] = useState(0);

  const canReview = permissions.includes('inspection-edit');
  const canDelete = permissions.includes('inspection-delete');

  const load = useCallback(async () => {
    try {
      const d = await repoGetRecord(id);
      console.log('[DetailScreen] load id=', id, 'photos=', d?.photos?.length, 'photosData=', JSON.stringify(d?.photos));
      setDetail(d);
    } catch (err) {
      console.log('[DetailScreen] load error', err);
    } finally {
      setLoading(false);
    }
  }, [id]);

  useFocusEffect(
    useCallback(() => {
      load();
    }, [load]),
  );

  useEffect(() => {
    const unsub = subscribeSync(() => load());
    return unsub;
  }, [load]);

  const openReview = (action: 'review' | 'reject') => {
    setRemarks('');
    setReviewAction(action);
    setReviewOpen(true);
  };

  const confirmReview = async () => {
    if (reviewAction === 'reject' && !remarks.trim()) {
      Alert.alert('Remarks', 'Remarks are required for rejection.');
      return;
    }
    setBusy(true);
    try {
      const newStatus = await repoReviewRecord(id, reviewAction, remarks.trim(), user?.full_name ?? '');
      setReviewOpen(false);
      Alert.alert('Done', `The inspection has been marked as ${newStatus}.`);
      scheduleSync();
      load();
    } catch {
      Alert.alert('Error', 'Could not perform the review action.');
    } finally {
      setBusy(false);
    }
  };

  const confirmDelete = () => {
    Alert.alert(
      'Delete inspection',
      `Delete "${detail?.project_title}"? This cannot be undone.`,
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Delete',
          style: 'destructive',
          onPress: async () => {
            if (!detail) return;
            try {
              await repoDeleteRecord(detail.id);
              scheduleSync();
              navigation.goBack();
            } catch {
              Alert.alert('Error', 'Could not delete the inspection.');
            }
          },
        },
      ],
    );
  };

  if (loading) {
    return (
      <View style={styles.center}>
        <ActivityIndicator size="large" color={colors.primary} />
      </View>
    );
  }

  if (!detail) {
    return (
      <View style={styles.center}>
        <Text style={{ fontFamily: fonts.body, color: colors.gray500 }}>Unable to read the inspection record.</Text>
      </View>
    );
  }

  const editable = canReview || detail.status === 'Draft' || detail.status === 'Rejected';
  const grouped: Record<string, { item: string; result: ItemResult }[]> = {};
  for (const r of detail.results) {
    if (!grouped[r.category]) grouped[r.category] = [];
    grouped[r.category].push({ item: r.item_text, result: r.result });
  }
  const photos = detail.photos ?? [];

  return (
    <SafeAreaView style={styles.safe} edges={['bottom']}>
      <ScrollView contentContainerStyle={styles.content}>
        <View style={[styles.section, styles.headCard]}>
          <View style={styles.headTop}>
            <StatusPill status={detail.status} />
            <Text style={styles.inspectionNo}>{detail.inspection_no}</Text>
          </View>
          <Text style={styles.title}>{detail.project_title}</Text>
          <View style={styles.metaRow}>
            <Ionicons name="location-outline" size={13} color={colors.gray500} />
            <Text style={styles.metaText}>{detail.project_location || '—'}</Text>
          </View>
          <View style={styles.metaRow}>
            <Ionicons name="calendar-outline" size={13} color={colors.gray500} />
            <Text style={styles.metaText}>{detail.inspection_date || '—'}</Text>
          </View>
        </View>

        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Project Information</Text>
          <Row label="Application No." value={detail.application_no} />
          <Row label="Permit No." value={detail.permit_no} />
          <Row label="Permit Date Issued" value={detail.permit_date_issued} />
          <Row label="Inspection Type" value={detail.inspection_type} />
          <Row label="Inspection Result" value={detail.inspection_result} />
          <Row label="Physical Accomplishment" value={detail.physical_accomplishment != null ? `${detail.physical_accomplishment}%` : '—'} />
          <Row label="Time Started" value={detail.time_started} />
          <Row label="Time Finished" value={detail.time_finished} />
          <Row label="Owner / Rep." value={detail.owner_representative} />
          <Row label="Contractor" value={detail.project_contractor} />
          <Row label="Engineer" value={detail.project_engineer} />
          <Row label="Inspector" value={detail.inspector_name} />
          <Row label="Team Leader 1" value={detail.team_leader_1_name} />
          <Row label="Team Leader 2" value={detail.team_leader_2_name} />
          <Row label="Completion" value={detail.completion_percentage != null ? `${detail.completion_percentage}%` : '—'} />
        </View>

        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Checklist Results</Text>
          {Object.keys(grouped).map((cat) => (
            <View key={cat} style={styles.catBlock}>
              <Text style={styles.catTitle}>{cat}</Text>
              {grouped[cat].map((r, i) => (
                <View key={i} style={styles.itemRow}>
                  <Text style={styles.itemText}>{r.item}</Text>
                  <View style={[styles.badge, { backgroundColor: RESULT_STYLE[r.result].bg }]}>
                    <Text style={[styles.badgeText, { color: RESULT_STYLE[r.result].fg }]}>{r.result}</Text>
                  </View>
                </View>
              ))}
            </View>
          ))}
          {Object.keys(grouped).length === 0 && (
            <Text style={styles.hint}>No checklist items answered yet.</Text>
          )}
        </View>

        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Findings at Recommendations</Text>
          <Row label="Overall Findings" value={detail.overall_findings} multiline />
          <Row label="Recommendations" value={detail.recommendations} multiline />
        </View>

        {(detail.reviewed_by || detail.approved_by) && (
          <View style={styles.section}>
            <Text style={styles.sectionTitle}>Review Decision (Web)</Text>
            {detail.reviewed_by ? (
              <>
                <Row label="Reviewed By" value={detail.reviewed_by} />
                <Row label="Reviewed On" value={detail.review_date} />
                <Row label="Review Remarks" value={detail.review_remarks} multiline />
              </>
            ) : (
              <Row label="Reviewed By" value="—" />
            )}
            {detail.approved_by ? (
              <>
                <Row label="Approved By" value={detail.approved_by} />
                <Row label="Approved On" value={detail.approval_date} />
                <Row label="Approval Remarks" value={detail.approval_remarks} multiline />
              </>
            ) : null}
          </View>
        )}

        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Site Photos ({photos.length})</Text>
          {photos.length ? (
            <View style={styles.photoGrid}>
              {photos.map((p) => {
                const uri = resolvePhotoUri(p.file_path);
                return (
                  <Pressable
                    key={p.id}
                    style={styles.photoCell}
                    onPress={() => {
                      const idx = photos.indexOf(p);
                      setViewerIndex(idx >= 0 ? idx : 0);
                      setViewerOpen(true);
                    }}
                  >
                    <Image
                      source={uri ? { uri } : undefined}
                      style={styles.photo}
                      resizeMode="cover"
                    />
                    {p.caption ? <Text style={styles.photoCaption} numberOfLines={1}>{p.caption}</Text> : null}
                  </Pressable>
                );
              })}
            </View>
          ) : (
            <Text style={styles.hint}>No photos yet.</Text>
          )}
        </View>

        <View style={styles.actions}>
          {editable && (
            <Pressable style={[styles.btn, styles.btnOutline]} onPress={() => navigation.navigate('InspectionForm', { id })}>
              <Ionicons name="create-outline" size={16} color={colors.primary} />
              <Text style={styles.btnOutlineText}>Edit</Text>
            </Pressable>
          )}
          {canDelete && (
            <Pressable style={[styles.btn, styles.btnDanger]} onPress={confirmDelete}>
              <Ionicons name="trash-outline" size={16} color={colors.white} />
              <Text style={styles.btnPrimaryText}>Delete</Text>
            </Pressable>
          )}
          {canReview && detail.status === 'Under Review' && (
            <>
              <Pressable style={[styles.btn, styles.btnDanger]} onPress={() => openReview('reject')}>
                <Ionicons name="close" size={16} color={colors.white} />
                <Text style={styles.btnPrimaryText}>Reject</Text>
              </Pressable>
              <Pressable style={[styles.btn, styles.btnPrimary]} onPress={() => openReview('review')}>
                <Ionicons name="checkmark" size={16} color={colors.white} />
                <Text style={styles.btnPrimaryText}>Approve</Text>
              </Pressable>
            </>
          )}
        </View>

        <Modal visible={reviewOpen} transparent animationType="fade" onRequestClose={() => setReviewOpen(false)}>
          <View style={styles.modalBackdrop}>
            <View style={styles.modalSheet}>
              <Text style={styles.modalTitle}>
                {reviewAction === 'reject'
                  ? 'Reject the Inspection'
                  : 'Approve the Inspection'}
              </Text>
              {reviewAction === 'reject' && (
                <TextInput
                  style={styles.remarksInput}
                  placeholder="Remarks (required for rejection)"
                  placeholderTextColor={colors.gray400}
                  multiline
                  value={remarks}
                  onChangeText={setRemarks}
                />
              )}
              <View style={styles.modalActions}>
                <Pressable
                  style={[styles.btn, styles.btnGhost, { flex: 1 }]}
                  onPress={() => setReviewOpen(false)}
                  disabled={busy}
                >
                  <Text style={styles.btnGhostText}>Cancel</Text>
                </Pressable>
                <Pressable
                  style={[
                    styles.btn,
                    reviewAction === 'reject' ? styles.btnDanger : styles.btnPrimary,
                    { flex: 1 },
                  ]}
                  onPress={confirmReview}
                  disabled={busy}
                >
                  {busy ? (
                    <ActivityIndicator color={colors.white} />
                  ) : (
                    <Text style={styles.btnPrimaryText}>Confirm</Text>
                  )}
                </Pressable>
              </View>
            </View>
          </View>
        </Modal>

        <PhotoViewerModal
          visible={viewerOpen}
          photos={photos}
          initialIndex={viewerIndex}
          title={detail.inspection_no}
          onClose={() => setViewerOpen(false)}
        />
      </ScrollView>
    </SafeAreaView>
  );
}

function Row({ label, value, multiline }: { label: string; value?: string | number | null; multiline?: boolean }) {
  const v = value == null || value === '' ? '—' : String(value);
  return (
    <View style={[styles.row, multiline && styles.rowMultiline]}>
      <Text style={styles.rowLabel}>{label}</Text>
      <Text style={[styles.rowValue, multiline && styles.rowValueMultiline]}>{v}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  safe: {
    flex: 1,
    backgroundColor: colors.bg,
  },
  center: {
    flex: 1,
    backgroundColor: colors.bg,
    alignItems: 'center',
    justifyContent: 'center',
  },
  content: {
    padding: 16,
    paddingBottom: 40,
  },
  section: {
    backgroundColor: colors.white,
    borderRadius: 14,
    borderWidth: 1,
    borderColor: colors.gray200,
    padding: 14,
    marginBottom: 12,
  },
  headCard: {
    backgroundColor: colors.navy900,
    borderColor: colors.navy900,
  },
  headTop: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 8,
  },
  inspectionNo: {
    fontFamily: fonts.bodySemi,
    fontSize: 12.5,
    color: 'rgba(255,255,255,0.7)',
  },
  title: {
    fontFamily: fonts.displaySemi,
    fontSize: 17,
    color: colors.white,
    lineHeight: 23,
    marginBottom: 8,
  },
  metaRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 5,
    marginTop: 2,
  },
  metaText: {
    fontFamily: fonts.body,
    fontSize: 12.5,
    color: 'rgba(255,255,255,0.75)',
  },
  sectionTitle: {
    fontFamily: fonts.displaySemi,
    fontSize: 15,
    color: colors.gray800,
    marginBottom: 10,
  },
  hint: {
    fontFamily: fonts.body,
    fontSize: 13,
    color: colors.gray500,
    lineHeight: 19,
  },
  row: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    gap: 12,
    paddingVertical: 6,
  },
  rowMultiline: {
    flexDirection: 'column',
    gap: 2,
  },
  rowLabel: {
    fontFamily: fonts.bodyMedium,
    fontSize: 13,
    color: colors.gray500,
  },
  rowValue: {
    fontFamily: fonts.body,
    fontSize: 13,
    color: colors.gray800,
    flex: 1,
    textAlign: 'right',
  },
  rowValueMultiline: {
    fontFamily: fonts.body,
    fontSize: 13.5,
    color: colors.gray800,
    lineHeight: 20,
  },
  catBlock: {
    marginBottom: 10,
  },
  catTitle: {
    fontFamily: fonts.displaySemi,
    fontSize: 13,
    color: colors.gray700,
    marginBottom: 4,
  },
  itemRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 10,
    paddingVertical: 6,
    borderBottomWidth: 1,
    borderBottomColor: colors.gray100,
  },
  itemText: {
    fontFamily: fonts.body,
    fontSize: 13,
    color: colors.gray800,
    flex: 1,
  },
  badge: {
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 7,
  },
  badgeText: {
    fontFamily: fonts.bodySemi,
    fontSize: 11.5,
  },
  photoGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  photoCell: {
    width: '31%',
    aspectRatio: 1,
    borderRadius: 10,
    overflow: 'hidden',
    borderWidth: 1,
    borderColor: colors.gray200,
    backgroundColor: colors.gray50,
  },
  photo: {
    width: '100%',
    height: '100%',
  },
  photoCaption: {
    position: 'absolute',
    bottom: 0,
    left: 0,
    right: 0,
    backgroundColor: 'rgba(0,0,0,0.5)',
    color: colors.white,
    fontFamily: fonts.body,
    fontSize: 10,
    paddingHorizontal: 4,
    paddingVertical: 2,
  },
  actions: {
    flexDirection: 'row',
    gap: 8,
    marginTop: 2,
  },
  btn: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 6,
    borderRadius: 12,
    paddingVertical: 13,
    paddingHorizontal: 12,
  },
  btnPrimary: {
    backgroundColor: colors.primary,
  },
  btnDanger: {
    backgroundColor: colors.danger,
  },
  btnOutline: {
    borderWidth: 1.5,
    borderColor: colors.primary,
    backgroundColor: colors.white,
  },
  btnGhost: {
    backgroundColor: colors.gray100,
  },
  btnPrimaryText: {
    fontFamily: fonts.bodySemi,
    fontSize: 13.5,
    color: colors.white,
  },
  btnOutlineText: {
    fontFamily: fonts.bodySemi,
    fontSize: 13.5,
    color: colors.primary,
  },
  btnGhostText: {
    fontFamily: fonts.bodySemi,
    fontSize: 13.5,
    color: colors.gray700,
  },
  modalBackdrop: {
    flex: 1,
    backgroundColor: colors.overlay,
    justifyContent: 'center',
    padding: 20,
  },
  modalSheet: {
    backgroundColor: colors.white,
    borderRadius: 18,
    padding: 18,
  },
  modalTitle: {
    fontFamily: fonts.displaySemi,
    fontSize: 16,
    color: colors.gray800,
    marginBottom: 12,
  },
  remarksInput: {
    borderWidth: 1,
    borderColor: colors.gray300,
    borderRadius: 10,
    paddingHorizontal: 12,
    paddingVertical: 10,
    fontFamily: fonts.body,
    fontSize: 13.5,
    color: colors.gray800,
    minHeight: 70,
    textAlignVertical: 'top',
    marginBottom: 12,
  },
  modalActions: {
    flexDirection: 'row',
    gap: 8,
    marginTop: 14,
  },
});
