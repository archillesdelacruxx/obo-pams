import React, { useCallback, useEffect, useState } from 'react';
import {
  Alert,
  Modal,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { useNavigation, useFocusEffect } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { Ionicons } from '@expo/vector-icons';
import { SafeAreaView } from 'react-native-safe-area-context';
import { LinearGradient } from 'expo-linear-gradient';
import { colors, fonts } from '../../theme/tokens';
import MonitoringTable from '../../components/MonitoringTable';
import StatusPill from '../../components/StatusPill';
import { deleteRecord, listRecords } from '../../db/inspectionRepo';
import { pendingCount, lastSyncedAt, runSync, subscribeSync } from '../../db/sync';
import { useAuth } from '../../context/AuthContext';
import type { InspectionReportRow } from '../../types';
import type { InspectionsStackParamList } from '../../navigation/types';

const FILTERS: { key: string; label: string; status?: string }[] = [
  { key: 'all', label: 'All' },
  { key: 'Draft', label: 'Draft', status: 'Draft' },
  { key: 'Under Review', label: 'Under Review', status: 'Under Review' },
  { key: 'Approved', label: 'Approved', status: 'Approved' },
  { key: 'Completed', label: 'Completed', status: 'Completed' },
  { key: 'Rejected', label: 'Rejected', status: 'Rejected' },
];

export default function InspectionsScreen() {
  const navigation = useNavigation<NativeStackNavigationProp<InspectionsStackParamList, 'InspectionsList'>>();
  const { permissions } = useAuth();
  const canEditAll = permissions.includes('inspection-edit');
  const canDelete = permissions.includes('inspection-delete');
  const [records, setRecords] = useState<InspectionReportRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState(false);
  const [filter, setFilter] = useState('all');
  const [search, setSearch] = useState('');
  const [selected, setSelected] = useState<InspectionReportRow | null>(null);
  const [pending, setPending] = useState(0);
  const [lastSynced, setLastSynced] = useState<string | null>(null);
  const [syncing, setSyncing] = useState(false);

  const refreshSyncInfo = useCallback(async () => {
    const [p, l] = await Promise.all([pendingCount(), lastSyncedAt()]);
    setPending(p);
    setLastSynced(l);
  }, []);

  const load = useCallback(
    async (extra?: { filter?: string; search?: string }) => {
      const f = extra?.filter ?? filter;
      const s = extra?.search ?? search;
      const rows = await listRecords({
        status: f === 'all' ? undefined : f,
        search: s || undefined,
      });
      setRecords(rows ?? []);
      setError(false);
      return rows;
    },
    [filter, search],
  );

  useFocusEffect(
    useCallback(() => {
      let active = true;
      setLoading(true);
      load()
        .catch(() => {
          if (active) setError(true);
        })
        .finally(() => {
          if (active) setLoading(false);
        });
      return () => {
        active = false;
      };
    }, [load]),
  );

  useEffect(() => {
    void refreshSyncInfo();
  }, [refreshSyncInfo]);

  /* Reload the list automatically whenever a background sync pulls new data. */
  useEffect(() => {
    const unsub = subscribeSync(() => {
      void load();
      void refreshSyncInfo();
    });
    return unsub;
  }, [load, refreshSyncInfo]);

  const handleSync = useCallback(async () => {
    if (syncing) return;
    setSyncing(true);
    try {
      const res = await runSync();
      if (res.offline) {
        Alert.alert('Offline', 'Cannot reach the server. Make sure you are on the same network as the server.');
      } else {
        Alert.alert(
          'Sync complete',
          `Uploaded: ${res.pushed} · Updates pulled: ${res.pulled}${res.errors ? ` · Errors: ${res.errors}` : ''}`,
        );
      }
    } catch {
      Alert.alert('Sync failed', 'An unexpected error occurred during sync.');
    } finally {
      setSyncing(false);
      await refreshSyncInfo();
    }
  }, [syncing, refreshSyncInfo]);

  const onRefresh = useCallback(async () => {
    setRefreshing(true);
    try {
      await load();
    } catch {
      setError(true);
    } finally {
      setRefreshing(false);
    }
  }, [load]);

  const openRecord = useCallback(
    (row: InspectionReportRow) => {
      if (row.status === 'Draft' || row.status === 'Rejected') {
        navigation.navigate('InspectionForm', { id: row.id });
      } else {
        navigation.navigate('InspectionDetail', { id: row.id });
      }
    },
    [navigation],
  );

  const closeModal = useCallback(() => setSelected(null), []);

  const canEditRow = useCallback(
    (row: InspectionReportRow) => canEditAll || row.status === 'Draft' || row.status === 'Rejected',
    [canEditAll],
  );

  const confirmDelete = useCallback(
    (row: InspectionReportRow) => {
      Alert.alert('Delete inspection', `Delete "${row.project_title}"? This cannot be undone.`, [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Delete',
          style: 'destructive',
          onPress: async () => {
            closeModal();
            try {
              await deleteRecord(row.id);
              await load();
            } catch {
              Alert.alert('Error', 'Could not delete the inspection.');
            }
          },
        },
      ]);
    },
    [closeModal, load],
  );

  return (
    <SafeAreaView style={styles.safe} edges={['top']}>
      <LinearGradient
        colors={[colors.navy900, colors.navy700]}
        style={styles.header}
      >
        <View style={styles.headerRow}>
          <View>
            <Text style={styles.headerTitle}>Inspections</Text>
            <Text style={styles.headerSub}>
              {loading ? 'Loading…' : `${records.length} record${records.length === 1 ? '' : 's'}`}
            </Text>
          </View>
          <View style={styles.headerActions}>
            <Pressable style={styles.syncBtn} onPress={handleSync} disabled={syncing}>
              <Ionicons name={syncing ? 'sync' : 'cloud-upload-outline'} size={18} color={colors.white} />
            </Pressable>
            <Pressable style={styles.newBtn} onPress={() => navigation.navigate('InspectionForm', {})}>
              <Ionicons name="add" size={20} color={colors.white} />
              <Text style={styles.newBtnText}>New</Text>
            </Pressable>
          </View>
        </View>
        <View style={styles.searchRow}>
          <Ionicons name="search" size={16} color={colors.gray400} />
          <TextInput
            style={styles.searchInput}
            placeholder="Search project, permit, application..."
            placeholderTextColor={colors.gray400}
            value={search}
            onChangeText={(t) => {
              setSearch(t);
              load({ filter, search: t }).catch(() => setError(true));
            }}
          />
          {search !== '' && (
            <Pressable onPress={() => setSearch('')}>
              <Ionicons name="close-circle" size={16} color={colors.gray400} />
            </Pressable>
          )}
        </View>
        <ScrollView
          horizontal
          showsHorizontalScrollIndicator={false}
          contentContainerStyle={styles.chips}
        >
          {FILTERS.map((f) => {
            const active = filter === f.key;
            return (
              <Pressable
                key={f.key}
                style={[styles.chip, active && styles.chipActive]}
                onPress={() => {
                  setFilter(f.key);
                  load({ filter: f.key, search }).catch(() => setError(true));
                }}
              >
                <Text style={[styles.chipText, active && styles.chipTextActive]}>{f.label}</Text>
              </Pressable>
            );
          })}
        </ScrollView>
      </LinearGradient>

      {pending > 0 || syncing || lastSynced ? (
        <Pressable
          style={styles.syncBanner}
          onPress={handleSync}
          disabled={syncing}
        >
          <Ionicons
            name={syncing ? 'sync' : pending > 0 ? 'cloud-upload-outline' : 'checkmark-circle-outline'}
            size={16}
            color={colors.primary}
          />
          <Text style={styles.syncBannerText}>
            {syncing
              ? 'Syncing…'
              : pending > 0
                ? `${pending} record${pending === 1 ? '' : 's'} pending sync — tap to sync now`
                : `All synced · ${lastSynced ? new Date(lastSynced.replace(' ', 'T')).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }) : ''}`}
          </Text>
          {!syncing ? <Ionicons name="chevron-forward" size={14} color={colors.primary} /> : null}
        </Pressable>
      ) : null}
      <ScrollView
        style={styles.body}
        contentContainerStyle={styles.bodyContent}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor={colors.primary} />}
      >
        <MonitoringTable
          rows={records}
          loading={loading}
          error={error}
          onRowPress={openRecord}
          onRowLongPress={setSelected}
        />
        <Text style={styles.hint}>Tap a row to open · long press to edit or delete</Text>
      </ScrollView>

      <Modal visible={selected !== null} transparent animationType="fade" onRequestClose={closeModal}>
        <View style={styles.modalOverlay}>
          <Pressable style={StyleSheet.absoluteFill} onPress={closeModal} />
          <View style={styles.modalCard}>
            <View style={styles.modalHead}>
              <Text style={styles.modalNo}>{selected?.inspection_no}</Text>
              {selected ? <StatusPill status={selected.status} /> : null}
            </View>
            <Text style={styles.modalTitle} numberOfLines={2}>
              {selected?.project_title}
            </Text>
            {selected?.project_location ? (
              <View style={styles.modalMeta}>
                <Ionicons name="location-outline" size={13} color={colors.gray500} />
                <Text style={styles.modalMetaText} numberOfLines={1}>
                  {selected.project_location}
                </Text>
              </View>
            ) : null}
            <View style={styles.modalActions}>
              {selected && canEditRow(selected) ? (
                <Pressable
                  style={[styles.modalBtn, styles.modalBtnPrimary]}
                  onPress={() => {
                    const id = selected?.id;
                    closeModal();
                    if (id != null) navigation.navigate('InspectionForm', { id });
                  }}
                >
                  <Ionicons name="create-outline" size={18} color={colors.white} />
                  <Text style={styles.modalBtnPrimaryText}>Edit</Text>
                </Pressable>
              ) : null}
              <Pressable
                style={[styles.modalBtn, styles.modalBtnOutline]}
                onPress={() => {
                  const id = selected?.id;
                  closeModal();
                  if (id != null) navigation.navigate('InspectionDetail', { id });
                }}
              >
                <Ionicons name="eye-outline" size={18} color={colors.primary} />
                <Text style={styles.modalBtnOutlineText}>View</Text>
              </Pressable>
              {selected && canDelete ? (
                <Pressable style={[styles.modalBtn, styles.modalBtnDanger]} onPress={() => confirmDelete(selected)}>
                  <Ionicons name="trash-outline" size={18} color={colors.white} />
                  <Text style={styles.modalBtnPrimaryText}>Delete</Text>
                </Pressable>
              ) : null}
              <Pressable style={[styles.modalBtn, styles.modalBtnGhost]} onPress={closeModal}>
                <Text style={styles.modalBtnGhostText}>Cancel</Text>
              </Pressable>
            </View>
          </View>
        </View>
      </Modal>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: {
    flex: 1,
    backgroundColor: colors.bg,
  },
  header: {
    backgroundColor: colors.navy900,
    paddingBottom: 8,
    borderBottomLeftRadius: 22,
    borderBottomRightRadius: 22,
    shadowColor: colors.navy900,
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.18,
    shadowRadius: 12,
    elevation: 6,
  },
  headerRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 20,
    paddingTop: 14,
  },
  headerTitle: {
    fontFamily: fonts.display,
    fontSize: 24,
    color: colors.white,
  },
  headerSub: {
    fontFamily: fonts.body,
    fontSize: 12.5,
    color: 'rgba(255,255,255,0.65)',
    marginTop: 2,
  },
  newBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: colors.primary,
    paddingHorizontal: 14,
    paddingVertical: 9,
    borderRadius: 10,
    gap: 4,
  },
  headerActions: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  syncBtn: {
    width: 38,
    height: 38,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(255,255,255,0.12)',
    borderRadius: 10,
  },
  syncBanner: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    backgroundColor: colors.primary50,
    borderColor: colors.primary100,
    borderWidth: 1,
    borderRadius: 12,
    paddingHorizontal: 14,
    paddingVertical: 10,
    marginHorizontal: 16,
    marginTop: 12,
  },
  syncBannerText: {
    flex: 1,
    fontFamily: fonts.bodyMedium,
    fontSize: 12.5,
    color: colors.primary,
  },
  newBtnText: {
    fontFamily: fonts.bodySemi,
    fontSize: 13.5,
    color: colors.white,
  },
  searchRow: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: colors.white,
    borderRadius: 12,
    paddingHorizontal: 12,
    marginTop: 14,
    marginHorizontal: 20,
    gap: 8,
    borderWidth: 1,
    borderColor: colors.navy900,
  },
  searchInput: {
    flex: 1,
    paddingVertical: 10,
    fontFamily: fonts.body,
    fontSize: 13.5,
    color: colors.gray800,
  },
  chips: {
    paddingHorizontal: 20,
    paddingTop: 12,
    gap: 8,
  },
  chip: {
    paddingHorizontal: 14,
    paddingVertical: 7,
    borderRadius: 999,
    backgroundColor: 'rgba(255,255,255,0.12)',
  },
  chipActive: {
    backgroundColor: colors.primary,
  },
  chipText: {
    fontFamily: fonts.bodyMedium,
    fontSize: 12.5,
    color: 'rgba(255,255,255,0.75)',
  },
  chipTextActive: {
    color: colors.white,
  },
  body: {
    flex: 1,
  },
  bodyContent: {
    padding: 16,
    paddingBottom: 32,
  },
  hint: {
    fontFamily: fonts.body,
    fontSize: 12,
    color: colors.gray500,
    textAlign: 'center',
    marginTop: 14,
  },
  modalOverlay: {
    flex: 1,
    backgroundColor: colors.overlay,
    alignItems: 'center',
    justifyContent: 'center',
    padding: 24,
  },
  modalCard: {
    width: '100%',
    maxWidth: 420,
    backgroundColor: colors.white,
    borderRadius: 16,
    padding: 18,
  },
  modalHead: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 8,
  },
  modalNo: {
    fontFamily: fonts.bodySemi,
    fontSize: 12.5,
    color: colors.gray500,
  },
  modalTitle: {
    fontFamily: fonts.displaySemi,
    fontSize: 16,
    color: colors.gray800,
    lineHeight: 22,
  },
  modalMeta: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 5,
    marginTop: 6,
  },
  modalMetaText: {
    fontFamily: fonts.body,
    fontSize: 12.5,
    color: colors.gray500,
    flex: 1,
  },
  modalActions: {
    flexDirection: 'row',
    gap: 8,
    marginTop: 18,
  },
  modalBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: 10,
    paddingVertical: 10,
    paddingHorizontal: 14,
    gap: 5,
    flex: 1,
  },
  modalBtnPrimary: {
    backgroundColor: colors.primary,
  },
  modalBtnDanger: {
    backgroundColor: colors.danger,
  },
  modalBtnPrimaryText: {
    fontFamily: fonts.bodySemi,
    fontSize: 13.5,
    color: colors.white,
  },
  modalBtnOutline: {
    borderWidth: 1,
    borderColor: colors.primary,
    backgroundColor: colors.primary50,
  },
  modalBtnOutlineText: {
    fontFamily: fonts.bodySemi,
    fontSize: 13.5,
    color: colors.primary,
  },
  modalBtnGhost: {
    borderWidth: 1,
    borderColor: colors.gray200,
    backgroundColor: colors.white,
  },
  modalBtnGhostText: {
    fontFamily: fonts.bodySemi,
    fontSize: 13.5,
    color: colors.gray600,
  },
});
