import React, { useCallback, useEffect, useState } from 'react';
import {
  ActivityIndicator,
  Image,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { useFocusEffect } from '@react-navigation/native';
import { Ionicons } from '@expo/vector-icons';
import { SafeAreaView } from 'react-native-safe-area-context';
import { colors, fonts } from '../theme/tokens';
import { getAllRecordsWithPhotos } from '../db/inspectionRepo';
import { subscribeSync } from '../db/sync';
import { resolvePhotoUri } from '../utils/media';
import PhotoViewerModal from '../components/PhotoViewerModal';
import EmptyState from '../components/EmptyState';
import StatusPill from '../components/StatusPill';
import type { InspectionPhoto, InspectionRecord } from '../types';

type RecordWithPhotos = InspectionRecord & { photos: InspectionPhoto[] };

function formatDate(dateStr: string | null): string {
  if (!dateStr) return '—';
  const d = new Date(dateStr);
  return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

export default function PhotosScreen() {
  const [records, setRecords] = useState<RecordWithPhotos[]>([]);
  const [loading, setLoading] = useState(true);
  const [viewerOpen, setViewerOpen] = useState(false);
  const [viewerPhotos, setViewerPhotos] = useState<InspectionPhoto[]>([]);
  const [viewerIndex, setViewerIndex] = useState(0);

  const load = useCallback(async () => {
    try {
      const rows = await getAllRecordsWithPhotos();
      setRecords((rows ?? []).filter((r) => r.photos.length > 0));
    } catch {
      setRecords([]);
    } finally {
      setLoading(false);
    }
  }, []);

  useFocusEffect(
    useCallback(() => {
      void load();
    }, [load]),
  );

  useEffect(() => {
    const unsub = subscribeSync(() => void load());
    return unsub;
  }, [load]);

  const openPhotos = useCallback((item: RecordWithPhotos, index: number) => {
    setViewerPhotos(item.photos);
    setViewerIndex(index);
    setViewerOpen(true);
  }, []);

  const totalPhotos = records.reduce((n, r) => n + r.photos.length, 0);

  return (
    <SafeAreaView style={styles.safe} edges={['bottom']}>
      <ScrollView contentContainerStyle={styles.content}>
        <View style={styles.headRow}>
          <View style={styles.headIcon}>
            <Ionicons name="images-outline" size={22} color={colors.primary} />
          </View>
          <View style={styles.headTextWrap}>
            <Text style={styles.headTitle}>Site Photos</Text>
            <Text style={styles.headSub}>
              {loading ? 'Loading…' : `${totalPhotos} photo${totalPhotos === 1 ? '' : 's'} across ${records.length} inspection${records.length === 1 ? '' : 's'}`}
            </Text>
          </View>
        </View>

        {loading ? (
          <View style={styles.center}>
            <ActivityIndicator size="large" color={colors.primary} />
          </View>
        ) : records.length === 0 ? (
          <View style={styles.emptyCard}>
            <EmptyState
              icon="images-outline"
              title="No site photos yet"
              message="Photos attached to inspections will appear here. Add photos from the inspection form."
            />
          </View>
        ) : (
          records.map((rec) => (
            <View key={rec.id} style={styles.card}>
              <View style={styles.cardHead}>
                <View style={styles.cardHeadText}>
                  <Text style={styles.cardNo}>{rec.inspection_no}</Text>
                  <Text style={styles.cardTitle} numberOfLines={1}>
                    {rec.project_title || 'Untitled project'}
                  </Text>
                  <Text style={styles.cardMeta}>
                    {formatDate(rec.inspection_date)}
                    {rec.project_location ? ` · ${rec.project_location}` : ''}
                  </Text>
                </View>
                <View style={styles.cardHeadRight}>
                  <StatusPill status={rec.status} />
                  <Text style={styles.cardCount}>{rec.photos.length}</Text>
                </View>
              </View>
              <View style={styles.grid}>
                {rec.photos.map((p, i) => {
                  const uri = resolvePhotoUri(p.file_path);
                  return (
                    <Pressable
                      key={p.id}
                      style={styles.cell}
                      onPress={() => openPhotos(rec, i)}
                    >
                      {uri ? (
                        <Image source={{ uri }} style={styles.photo} resizeMode="cover" />
                      ) : (
                        <View style={styles.photoMissing}>
                          <Ionicons name="image-outline" size={22} color={colors.gray400} />
                        </View>
                      )}
                    </Pressable>
                  );
                })}
              </View>
            </View>
          ))
        )}
      </ScrollView>

      <PhotoViewerModal
        visible={viewerOpen}
        photos={viewerPhotos}
        initialIndex={viewerIndex}
        title="Site Photos"
        onClose={() => setViewerOpen(false)}
      />
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: {
    flex: 1,
    backgroundColor: colors.bg,
  },
  content: {
    padding: 16,
    paddingBottom: 40,
  },
  headRow: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 16,
  },
  headIcon: {
    width: 44,
    height: 44,
    borderRadius: 12,
    backgroundColor: colors.primary100,
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: 12,
  },
  headTextWrap: {
    flex: 1,
  },
  headTitle: {
    fontFamily: fonts.displaySemi,
    fontSize: 17,
    color: colors.gray800,
  },
  headSub: {
    fontFamily: fonts.body,
    fontSize: 12.5,
    color: colors.gray500,
    marginTop: 2,
  },
  center: {
    paddingVertical: 60,
    alignItems: 'center',
  },
  emptyCard: {
    backgroundColor: colors.white,
    borderRadius: 14,
    borderWidth: 1,
    borderColor: colors.gray200,
    padding: 18,
  },
  card: {
    backgroundColor: colors.white,
    borderRadius: 14,
    borderWidth: 1,
    borderColor: colors.gray200,
    padding: 12,
    marginBottom: 12,
  },
  cardHead: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 10,
  },
  cardHeadText: {
    flex: 1,
    marginRight: 10,
  },
  cardNo: {
    fontFamily: fonts.bodySemi,
    fontSize: 11.5,
    color: colors.primary,
    marginBottom: 2,
  },
  cardTitle: {
    fontFamily: fonts.displaySemi,
    fontSize: 14,
    color: colors.gray800,
    marginBottom: 2,
  },
  cardMeta: {
    fontFamily: fonts.body,
    fontSize: 12,
    color: colors.gray500,
  },
  cardHeadRight: {
    alignItems: 'center',
    gap: 6,
  },
  cardCount: {
    fontFamily: fonts.bodySemi,
    fontSize: 13,
    color: colors.gray600,
  },
  grid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  cell: {
    width: '23%',
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
  photoMissing: {
    width: '100%',
    height: '100%',
    alignItems: 'center',
    justifyContent: 'center',
  },
});