import React, { useLayoutEffect, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  Image,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import { Ionicons } from '@expo/vector-icons';
import * as ImagePicker from 'expo-image-picker';
import { colors, fonts } from '../../theme/tokens';
import { SafeAreaView } from 'react-native-safe-area-context';
import StepProgressBar from '../../components/inspection/StepProgressBar';
import CategoryCard from '../../components/inspection/CategoryCard';
import { DateField, Field, InfoRow, PickerField, PillGroup } from '../../components/inspection/FormControls';
import useInspectionForm from './useInspectionForm';
import type { InspectionsStackParamList } from '../../navigation/types';

type Props = NativeStackScreenProps<InspectionsStackParamList, 'InspectionForm'>;

const INSPECTION_TYPES = ['1st', '2nd', '3rd', 'Others'];
const RESULT_OPTIONS = ['Passed', 'Passed with Remarks', 'Ongoing', 'Failed', 'For Re-inspection'];

function Btn({
  label,
  variant = 'primary',
  onPress,
  loading,
  flex,
}: {
  label: string;
  variant?: 'primary' | 'outline' | 'ghost';
  onPress: () => void;
  loading?: boolean;
  flex?: number;
}) {
  return (
    <Pressable
      onPress={onPress}
      disabled={loading}
      style={[
        styles.btn,
        variant === 'primary' && styles.btnPrimary,
        variant === 'outline' && styles.btnOutline,
        variant === 'ghost' && styles.btnGhost,
        { flex },
      ]}
    >
      {loading ? (
        <ActivityIndicator size="small" color={variant === 'primary' ? colors.white : colors.primary} />
      ) : (
        <Text
          style={[
            styles.btnText,
            variant === 'primary' && styles.btnTextPrimary,
            variant === 'outline' && styles.btnTextOutline,
            variant === 'ghost' && styles.btnTextGhost,
          ]}
        >
          {label}
        </Text>
      )}
    </Pressable>
  );
}

export default function InspectionFormScreen({ route, navigation }: Props) {
  const id = route.params?.id;
  const form = useInspectionForm(id);
  const [step, setStep] = useState(0);
  const [flash, setFlash] = useState<string | null>(null);
  const flashTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

  const showFlash = (message = 'Draft saved') => {
    setFlash(message);
    if (flashTimer.current) clearTimeout(flashTimer.current);
    flashTimer.current = setTimeout(() => setFlash(null), 1600);
  };

  const tlOpts1 = form.teamLeaders.filter((t) => t.team_no === 1);
  const tlOpts2 = form.teamLeaders.filter((t) => t.team_no === 2);
  const toOptions = (list: typeof tlOpts1) =>
    (list.length ? list : form.teamLeaders).map((t) => ({ value: t.id, label: t.full_name }));

  const allItems = form.categories.reduce((n, c) => n + (form.template[c]?.length ?? 0), 0);
  const pass = Object.values(form.results).filter((r) => r === 'Pass').length;
  const fail = Object.values(form.results).filter((r) => r === 'Fail').length;
  const na = Object.values(form.results).filter((r) => r === 'N/A').length;
  const answered = pass + fail + na;

  const handleSaveDraft = async () => {
    try {
      await form.saveDraft();
      showFlash(form.canSubmit ? 'Draft saved' : 'Saved');
    } catch {
      Alert.alert('Error', 'Could not save the draft. Please try again.');
    }
  };

  const handleBack = async () => {
    if (form.saving || form.submitting) return;
    if (form.recordId && form.editable) {
      try {
        await form.saveDraft();
      } catch {
        Alert.alert('Error', 'Could not save the draft.');
        return;
      }
    }
    navigation.goBack();
  };

  const handleNext = async () => {
    if (step === 0) {
      if (!form.info.projectTitle.trim() || !form.info.inspectionDate) {
        Alert.alert('Missing details', 'Project Title and Date Inspected are required.');
        return;
      }
      try {
        await form.saveDraft();
      } catch {
        Alert.alert('Error', 'Could not save. Please try again.');
        return;
      }
    }
    setStep((s) => s + 1);
  };

  const handleSubmit = () => {
    if (!form.info.projectTitle.trim() || !form.info.inspectionDate) {
      Alert.alert('Missing details', 'Project Title and Date Inspected are required.');
      return;
    }
    Alert.alert(
      'Submit for Review',
      'This will be submitted to the administrator. It can no longer be edited afterwards. Continue?',
      [
      { text: 'Cancel', style: 'cancel' },
      {
        text: 'Submit',
        onPress: async () => {
          try {
            const ok = await form.submit();
            if (ok) {
              Alert.alert('Submitted', 'The inspection has been submitted for review.');
              navigation.navigate('InspectionsList');
            }
          } catch {
            Alert.alert('Error', 'Could not submit. Please try again.');
          }
        },
      },
    ]);
  };

  const pickPhoto = (source: 'camera' | 'gallery') => {
    const run = async () => {
      const perm =
        source === 'camera'
          ? await ImagePicker.requestCameraPermissionsAsync()
          : await ImagePicker.requestMediaLibraryPermissionsAsync();
      if (!perm.granted) {
        Alert.alert('Permission', (source === 'camera' ? 'Camera' : 'Gallery') + ' access is required.');
        return;
      }
      const opts: ImagePicker.ImagePickerOptions = { mediaTypes: ['images'], quality: 0.7, allowsEditing: true };
      const res =
        source === 'camera' ? await ImagePicker.launchCameraAsync(opts) : await ImagePicker.launchImageLibraryAsync(opts);
      if (!res.canceled && res.assets?.length) {
        const a = res.assets[0];
        try {
          await form.addPhoto({ uri: a.uri, fileName: a.fileName ?? null, mimeType: a.mimeType ?? null });
        } catch {
          Alert.alert('Error', 'Could not save the photo.');
        }
      }
    };
    void run();
  };

  const onAddPhoto = () => {
    Alert.alert('Add a photo', 'Choose a source', [
      { text: 'Cancel', style: 'cancel' },
      { text: 'Camera', onPress: () => pickPhoto('camera') },
      { text: 'Gallery', onPress: () => pickPhoto('gallery') },
    ]);
  };

  const onRemovePhoto = (photoId: number) => {
    Alert.alert('Remove photo?', 'It will be removed from the inspection.', [
      { text: 'Cancel', style: 'cancel' },
      {
        text: 'Remove',
        style: 'destructive',
        onPress: () => void form.removePhoto(photoId).catch(() => Alert.alert('Error', 'Could not remove the photo.')),
      },
    ]);
  };

  useLayoutEffect(() => {
    navigation.setOptions({
      title: id ? 'Edit Inspection' : 'New Inspection',
      gestureEnabled: false,
      headerLeft: () => (
        <Pressable onPress={handleBack} hitSlop={10} style={{ paddingHorizontal: 6 }}>
          <Ionicons name="chevron-back" size={24} color={colors.white} />
        </Pressable>
      ),
    });
  });

  if (form.loading) {
    return (
      <View style={styles.center}>
        <ActivityIndicator size="large" color={colors.primary} />
      </View>
    );
  }

  const disabled = !form.editable;

  return (
    <SafeAreaView style={styles.safe} edges={['bottom']}>
      <StepProgressBar current={step} />
      <ScrollView contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
        {!form.editable && (
          <View style={styles.lockedBanner}>
            <Ionicons name="lock-closed" size={15} color={colors.warning} />
            <Text style={styles.lockedBannerText}>
              This inspection is locked. It can no longer be edited on this device.
            </Text>
          </View>
        )}
        {step === 0 && (
          <View>
            {form.recordId ? (
              <View style={styles.section}>
                <InfoRow label="Inspection No." value={form.inspectionNo ?? '—'} />
              </View>
            ) : (
              <View style={styles.section}>
                <Text style={styles.sectionTitle}>New Inspection</Text>
                <Text style={styles.hint}>Fill in the project information to get started. You can save at any time.</Text>
              </View>
            )}
            <View style={styles.section}>
              <Text style={styles.sectionTitle}>Team</Text>
              <PickerField
                label="Team Leader 1"
                value={form.info.teamLeader1}
                options={toOptions(tlOpts1)}
                onChange={(v) => form.updateInfo({ teamLeader1: Number(v) })}
                placeholder="Select a team leader"
              />
              <PickerField
                label="Team Leader 2 (optional)"
                value={form.info.teamLeader2}
                options={toOptions(tlOpts2)}
                onChange={(v) => form.updateInfo({ teamLeader2: Number(v) })}
                placeholder="Select a team leader"
              />
            </View>
            <View style={styles.section}>
              <Text style={styles.sectionTitle}>Project Information</Text>
              <DateField
                label="Date Inspected"
                required
                mode="date"
                value={form.info.inspectionDate}
                onChange={(v) => form.updateInfo({ inspectionDate: v })}
              />
              <PillGroup
                label="Inspection Type"
                options={INSPECTION_TYPES}
                value={form.info.inspectionType}
                onChange={(v) => form.updateInfo({ inspectionType: v })}
              />
              {form.info.inspectionType === 'Others' && (
                <Field
                  label="Others, specify"
                  required
                  value={form.info.inspectionTypeOther}
                  onChange={(v) => form.updateInfo({ inspectionTypeOther: v })}
                  placeholder="Enter the type"
                />
              )}
              <View style={styles.row2}>
                <Field
                  label="Building Permit No."
                  value={form.info.permitNo}
                  onChange={(v) => form.updateInfo({ permitNo: v })}
                  placeholder="Permit #"
                />
                <DateField
                  label="Date Issued"
                  mode="date"
                  value={form.info.permitDateIssued}
                  onChange={(v) => form.updateInfo({ permitDateIssued: v })}
                />
              </View>
              <Field
                label="Project Title"
                required
                value={form.info.projectTitle}
                onChange={(v) => form.updateInfo({ projectTitle: v })}
                placeholder="Project name"
              />
              <View style={styles.row2}>
                <Field
                  label="Physical Accomplishment (%)"
                  value={form.info.physical}
                  onChange={(v) => form.updateInfo({ physical: v })}
                  keyboardType="decimal-pad"
                  placeholder="%"
                />
                <DateField
                  label="Time Started"
                  mode="time"
                  value={form.info.timeStarted}
                  onChange={(v) => form.updateInfo({ timeStarted: v })}
                />
              </View>
              <Field
                label="Owner / Representative"
                value={form.info.owner}
                onChange={(v) => form.updateInfo({ owner: v })}
                placeholder="Owner name"
              />
              <View style={styles.row2}>
                <Field
                  label="Contact No."
                  value={form.info.contact}
                  onChange={(v) => form.updateInfo({ contact: v })}
                  keyboardType="phone-pad"
                  placeholder="09xx-xxx-xxxx"
                />
                <DateField
                  label="Time Finished"
                  mode="time"
                  value={form.info.timeFinished}
                  onChange={(v) => form.updateInfo({ timeFinished: v })}
                />
              </View>
              <Field
                label="Contractor"
                value={form.info.contractor}
                onChange={(v) => form.updateInfo({ contractor: v })}
                placeholder="Contractor name"
              />
              <Field
                label="Engineer"
                value={form.info.engineer}
                onChange={(v) => form.updateInfo({ engineer: v })}
                placeholder="Engineer name"
              />
              <Field
                label="Project Location"
                value={form.info.location}
                onChange={(v) => form.updateInfo({ location: v })}
                placeholder="Project location"
              />
              <PillGroup
                label="Inspection Result"
                options={RESULT_OPTIONS}
                value={form.info.inspectionResult}
                onChange={(v) => form.updateInfo({ inspectionResult: v })}
              />
            </View>
          </View>
        )}

        {step === 1 && (
          <View>
            {form.categories.map((cat) => (
              <CategoryCard
                key={cat}
                category={cat}
                items={form.template[cat] ?? []}
                results={form.results}
                onResult={form.setResult}
                extra={form.extra}
                onExtra={form.patchExtra}
                pct={form.pct[cat] ?? ''}
                onPctChange={(v) => form.setPctFor(cat, v)}
                remark={form.remarks[cat] ?? ''}
                onRemarkChange={(v) => form.setRemarkFor(cat, v)}
                aiEnabled={form.aiEnabled}
                aiLoading={!!form.aiLoading[cat]}
                onAi={() => void form.aiFor(cat)}
                disabled={disabled}
              />
            ))}
          </View>
        )}

        {step === 2 && (
          <View>
            <View style={styles.section}>
              <Text style={styles.sectionTitle}>Site Photos</Text>
              <Text style={styles.hint}>
                Take photos at the site. Each photo is saved immediately to this device.
              </Text>
            </View>
            <View style={styles.photoGrid}>
              {form.photos.map((p) => {
                const uri = p.file_path;
                return (
                  <View key={p.id} style={styles.photoCell}>
                    <Image source={uri ? { uri } : undefined} style={styles.photo} resizeMode="cover" />
                    {!disabled && (
                      <Pressable style={styles.photoDel} onPress={() => onRemovePhoto(p.id)} hitSlop={6}>
                        <Ionicons name="trash-outline" size={15} color={colors.white} />
                      </Pressable>
                    )}
                  </View>
                );
              })}
              {!disabled && (
                <Pressable style={[styles.photoCell, styles.photoAdd]} onPress={onAddPhoto}>
                  <Ionicons name="camera-outline" size={26} color={colors.primary} />
                  <Text style={styles.photoAddText}>Add</Text>
                </Pressable>
              )}
            </View>
            {form.photos.length === 0 && (
              <Text style={styles.emptyNote}>No photos yet. Tap Add to take a photo.</Text>
            )}
          </View>
        )}

        {step === 3 && (
          <View>
            <View style={styles.section}>
              <Text style={styles.sectionTitle}>Inspection Summary</Text>
              <View style={styles.summaryRow}>
                <View style={styles.summaryCard}>
                  <Text style={[styles.summaryNum, { color: colors.success }]}>{answered}</Text>
                  <Text style={styles.summaryLabel}>Answered</Text>
                </View>
                <View style={styles.summaryCard}>
                  <Text style={[styles.summaryNum, { color: colors.success }]}>{pass}</Text>
                  <Text style={styles.summaryLabel}>Pass</Text>
                </View>
                <View style={styles.summaryCard}>
                  <Text style={[styles.summaryNum, { color: colors.danger }]}>{fail}</Text>
                  <Text style={styles.summaryLabel}>Fail</Text>
                </View>
                <View style={styles.summaryCard}>
                  <Text style={[styles.summaryNum, { color: colors.gray500 }]}>{na}</Text>
                  <Text style={styles.summaryLabel}>N/A</Text>
                </View>
              </View>
              <View style={styles.summaryBar}>
                <View style={[styles.summaryFill, { flex: Math.max(pass, 0.0001) }]} />
                <View style={[styles.summaryFillFail, { flex: Math.max(fail, 0.0001) }]} />
                <View style={[styles.summaryFillNa, { flex: Math.max(na, 0.0001) }]} />
              </View>
              <Text style={styles.summaryPct}>
                {answered ? Math.round((pass / Math.max(answered, 1)) * 100) : 0}% pass of the answered items
              </Text>
              <View style={styles.divider} />
              <InfoRow label="Inspection No." value={form.inspectionNo ?? '—'} />
              <InfoRow label="Project Title" value={form.info.projectTitle} />
              <InfoRow label="Date Inspected" value={form.info.inspectionDate} />
              <InfoRow label="Inspection Type" value={form.info.inspectionType} />
              <InfoRow label="Team Leader 1" value={form.teamLeaders.find((t) => t.id === form.info.teamLeader1)?.full_name ?? '—'} />
              <InfoRow label="Team Leader 2" value={form.teamLeaders.find((t) => t.id === form.info.teamLeader2)?.full_name ?? '—'} />
              <InfoRow label="Photos" value={String(form.photos.length)} />
            </View>
            <View style={styles.section}>
              <Text style={styles.sectionTitle}>Per Category</Text>
              {form.categories.map((cat) => {
                const items = form.template[cat] ?? [];
                const done = items.filter((it) => form.results[it.id]).length;
                const catPass = items.filter((it) => form.results[it.id] === 'Pass').length;
                return (
                  <View key={cat} style={styles.catRow}>
                    <Text style={styles.catRowText} numberOfLines={1}>
                      {cat}
                    </Text>
                    <Text style={styles.catRowMeta}>
                      {done}/{items.length} · {catPass} pass
                      {form.pct[cat] ? ` · ${form.pct[cat]}%` : ''}
                    </Text>
                  </View>
                );
              })}
            </View>
            <View style={styles.section}>
              <Text style={styles.sectionTitle}>Findings at Recommendations</Text>
              <Field
                label="Overall Findings"
                value={form.info.overallFindings}
                onChange={(v) => form.updateInfo({ overallFindings: v })}
                placeholder="Overall observations at the site"
                multiline
              />
              <Field
                label="Recommendations"
                value={form.info.recommendations}
                onChange={(v) => form.updateInfo({ recommendations: v })}
                placeholder="Recommendations"
                multiline
              />
            </View>
          </View>
        )}

        {flash && (
          <View style={styles.flash}>
            <Ionicons name="checkmark-circle" size={16} color={colors.success} />
            <Text style={styles.flashText}>{flash}</Text>
          </View>
        )}

        <View style={styles.actions}>
          {form.editable ? (
            <>
              {step > 0 && <Btn label="Back" variant="ghost" onPress={() => setStep((s) => s - 1)} />}
              <Btn label="Save Draft" variant="outline" onPress={handleSaveDraft} loading={form.saving} />
              {step < 3 ? (
                <Btn label="Next" variant="primary" onPress={handleNext} flex={1.4} />
              ) : form.canSubmit ? (
                <Btn label="Submit for Review" variant="primary" onPress={handleSubmit} loading={form.submitting} flex={1.6} />
              ) : (
                <Btn label="Save" variant="primary" onPress={handleSaveDraft} loading={form.saving} flex={1.6} />
              )}
            </>
          ) : (
            <Btn label="Done" variant="primary" onPress={() => navigation.goBack()} flex={1.6} />
          )}
        </View>
      </ScrollView>
    </SafeAreaView>
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
  lockedBanner: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    backgroundColor: colors.warningBg,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: '#F3D9A4',
    padding: 12,
    marginBottom: 12,
  },
  lockedBannerText: {
    fontFamily: fonts.bodyMedium,
    fontSize: 12.5,
    color: colors.warning,
    flex: 1,
    lineHeight: 18,
  },
  row2: {
    flexDirection: 'row',
    gap: 10,
  },
  actions: {
    flexDirection: 'row',
    gap: 8,
    marginTop: 4,
  },
  btn: {
    borderRadius: 12,
    paddingVertical: 13,
    paddingHorizontal: 14,
    alignItems: 'center',
    justifyContent: 'center',
  },
  btnPrimary: {
    backgroundColor: colors.primary,
  },
  btnOutline: {
    borderWidth: 1.5,
    borderColor: colors.primary,
    backgroundColor: colors.white,
  },
  btnGhost: {
    backgroundColor: colors.gray100,
  },
  btnText: {
    fontFamily: fonts.bodySemi,
    fontSize: 14,
  },
  btnTextPrimary: {
    color: colors.white,
  },
  btnTextOutline: {
    color: colors.primary,
  },
  btnTextGhost: {
    color: colors.gray700,
  },
  flash: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 6,
    paddingVertical: 8,
  },
  flashText: {
    fontFamily: fonts.bodyMedium,
    fontSize: 13,
    color: colors.success,
  },
  photoGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 10,
  },
  photoCell: {
    width: '30.5%',
    aspectRatio: 1,
    borderRadius: 12,
    overflow: 'hidden',
    borderWidth: 1,
    borderColor: colors.gray200,
    backgroundColor: colors.gray50,
  },
  photo: {
    width: '100%',
    height: '100%',
  },
  photoDel: {
    position: 'absolute',
    top: 6,
    right: 6,
    backgroundColor: 'rgba(0,0,0,0.55)',
    borderRadius: 14,
    width: 26,
    height: 26,
    alignItems: 'center',
    justifyContent: 'center',
  },
  photoAdd: {
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1.5,
    borderStyle: 'dashed',
    borderColor: colors.primary,
    backgroundColor: colors.primary50,
  },
  photoAddText: {
    fontFamily: fonts.bodySemi,
    fontSize: 12,
    color: colors.primary,
    marginTop: 4,
  },
  emptyNote: {
    fontFamily: fonts.body,
    fontSize: 13,
    color: colors.gray500,
    textAlign: 'center',
    marginTop: 16,
  },
  summaryRow: {
    flexDirection: 'row',
    gap: 8,
  },
  summaryCard: {
    flex: 1,
    backgroundColor: colors.gray50,
    borderRadius: 10,
    paddingVertical: 10,
    alignItems: 'center',
  },
  summaryNum: {
    fontFamily: fonts.displaySemi,
    fontSize: 20,
  },
  summaryLabel: {
    fontFamily: fonts.bodyMedium,
    fontSize: 11.5,
    color: colors.gray500,
    marginTop: 2,
  },
  summaryBar: {
    flexDirection: 'row',
    height: 8,
    borderRadius: 4,
    overflow: 'hidden',
    marginTop: 12,
    backgroundColor: colors.gray200,
  },
  summaryFill: {
    backgroundColor: colors.success,
  },
  summaryFillFail: {
    backgroundColor: colors.danger,
  },
  summaryFillNa: {
    backgroundColor: colors.gray400,
  },
  summaryPct: {
    fontFamily: fonts.bodyMedium,
    fontSize: 12.5,
    color: colors.gray600,
    marginTop: 8,
  },
  divider: {
    height: 1,
    backgroundColor: colors.gray100,
    marginVertical: 12,
  },
  catRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 10,
    paddingVertical: 7,
    borderBottomWidth: 1,
    borderBottomColor: colors.gray100,
  },
  catRowText: {
    fontFamily: fonts.bodyMedium,
    fontSize: 13,
    color: colors.gray700,
    flex: 1,
  },
  catRowMeta: {
    fontFamily: fonts.body,
    fontSize: 12,
    color: colors.gray500,
  },
});
