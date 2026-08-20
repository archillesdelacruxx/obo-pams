import React, { useCallback, useEffect, useState } from 'react';
import {
  AppState,
  Image,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { useFocusEffect } from '@react-navigation/native';
import { LinearGradient } from 'expo-linear-gradient';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Ionicons } from '@expo/vector-icons';
import type { BottomTabScreenProps } from '@react-navigation/bottom-tabs';
import { colors, fonts, radii, shadows, spacing } from '../theme/tokens';
import { useAuth } from '../context/AuthContext';
import { apiGetUnreadNotifications } from '../api/auth';
import { getRecentWithPhotos, getStats } from '../db/inspectionRepo';
import { subscribeSync } from '../db/sync';
import { resolvePhotoUri } from '../utils/media';
import type { InspectionPhoto, InspectionRecord, InspectionStats } from '../types';
import type { MainTabParamList } from '../navigation/types';
import PressableScale from '../components/PressableScale';
import StatusPill from '../components/StatusPill';
import EmptyState from '../components/EmptyState';
import Skeleton from '../components/Skeleton';
import NotificationsModal from '../components/NotificationsModal';
import PhotoViewerModal from '../components/PhotoViewerModal';

type Props = BottomTabScreenProps<MainTabParamList, 'Home'>;

type RecentRecord = InspectionRecord & { photos: InspectionPhoto[] };

const REFRESH_INTERVAL_MS = 5000;

function getGreeting(): string {
  const h = new Date().getHours();
  if (h < 12) return 'Good morning';
  if (h < 17) return 'Good afternoon';
  return 'Good evening';
}

function formatShortDate(): string {
  return new Date().toLocaleDateString('en-US', {
    weekday: 'short',
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  });
}

function formatInspectionDate(dateStr: string | null): string {
  if (!dateStr) return '—';
  const d = new Date(dateStr);
  return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function StatCard({
  label,
  value,
  icon,
  color,
  bg,
}: {
  label: string;
  value: number;
  icon: keyof typeof Ionicons.glyphMap;
  color: string;
  bg: string;
}) {
  return (
    <View style={[styles.statCard, shadows.card]}>
      <View style={[styles.statIcon, { backgroundColor: bg }]}>
        <Ionicons name={icon} size={18} color={color} />
      </View>
      <Text style={[styles.statValue, { color }]}>{value}</Text>
      <Text style={styles.statLabel}>{label}</Text>
    </View>
  );
}

export default function DashboardScreen({ navigation }: Props) {
  const { user } = useAuth();
  const firstName = user?.full_name?.split(' ')[0] ?? 'User';

  const [stats, setStats] = useState<InspectionStats | null>(null);
  const [recent, setRecent] = useState<RecentRecord[]>([]);
  const [unread, setUnread] = useState(0);
  const [notifOpen, setNotifOpen] = useState(false);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [viewerOpen, setViewerOpen] = useState(false);
  const [viewerPhotos, setViewerPhotos] = useState<InspectionPhoto[]>([]);
  const [viewerIndex, setViewerIndex] = useState(0);

  const load = useCallback(async () => {
    try {
      const [s, r] = await Promise.all([getStats(), getRecentWithPhotos(5)]);
      setStats(s);
      setRecent((r ?? []).slice(0, 3));
    } catch {
      setStats(null);
      setRecent([]);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
    apiGetUnreadNotifications()
      .then((res) => setUnread(res.count ?? 0))
      .catch(() => undefined);
  }, []);

  useFocusEffect(
    useCallback(() => {
      void load();
    }, [load]),
  );

  /* Reload after every background sync (admin review decisions land here). */
  useEffect(() => {
    const unsub = subscribeSync(() => void load());
    return unsub;
  }, [load]);

  /* Lightweight foreground polling keeps the cards near real-time. */
  useEffect(() => {
    const poll = setInterval(() => {
      if (AppState.currentState !== 'active') return;
      void load();
    }, REFRESH_INTERVAL_MS);
    return () => clearInterval(poll);
  }, [load]);

  const onRefresh = () => {
    setRefreshing(true);
    load();
  };

  const openRecord = useCallback(
    (item: RecentRecord) => {
      if (item.status === 'Draft' || item.status === 'Rejected') {
        navigation.navigate('Inspections', { screen: 'InspectionForm', params: { id: item.id } });
      } else {
        navigation.navigate('Inspections', { screen: 'InspectionDetail', params: { id: item.id } });
      }
    },
    [navigation],
  );

  const openPhotos = useCallback((item: RecentRecord, index: number) => {
    if (!item.photos.length) return;
    setViewerPhotos(item.photos);
    setViewerIndex(index);
    setViewerOpen(true);
  }, []);

  return (
    <SafeAreaView style={styles.safe} edges={['top']}>
      <View style={styles.header}>
        <View style={styles.headerText}>
          <Text style={styles.greeting}>
            {getGreeting()}, {firstName} 👋
          </Text>
          <Text style={styles.date}>{formatShortDate()}</Text>
        </View>
        <PressableScale onPress={() => setNotifOpen(true)} style={styles.bellBtn}>
          <Ionicons name="notifications-outline" size={22} color={colors.white} />
          {unread > 0 ? <View style={styles.bellDot} /> : null}
        </PressableScale>
      </View>

      <ScrollView
        contentContainerStyle={styles.content}
        showsVerticalScrollIndicator={false}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor={colors.primary} />}
      >
        <PressableScale
          onPress={() => navigation.navigate('Inspections', { screen: 'InspectionForm', params: {} })}
          style={styles.heroWrap}
        >
          <LinearGradient colors={[colors.navy900, colors.navy700]} style={styles.hero}>
            <View style={styles.heroIcon}>
              <Ionicons name="add" size={26} color={colors.white} />
            </View>
            <View style={styles.heroTextWrap}>
              <Text style={styles.heroTitle}>New Inspection</Text>
              <Text style={styles.heroSub}>Record an on-site ocular checklist</Text>
            </View>
            <Ionicons name="chevron-forward" size={20} color="rgba(255,255,255,0.7)" />
          </LinearGradient>
        </PressableScale>

        <Text style={styles.sectionLabel}>My workload</Text>
        {loading ? (
          <View style={styles.statsRow}>
            <Skeleton height={104} />
            <Skeleton height={104} />
            <Skeleton height={104} />
          </View>
        ) : stats ? (
          <View style={styles.statsRow}>
            <StatCard label="Done" value={stats.done} icon="checkmark-circle" color={colors.success} bg={colors.successLight} />
            <StatCard label="Review" value={stats.under_review} icon="time" color={colors.warning} bg={colors.warningBg} />
            <StatCard label="Draft" value={stats.drafts} icon="create" color={colors.gray600} bg={colors.gray100} />
          </View>
        ) : (
          <View style={styles.statsRow}>
            <StatCard label="Done" value={0} icon="checkmark-circle" color={colors.success} bg={colors.successLight} />
            <StatCard label="Review" value={0} icon="time" color={colors.warning} bg={colors.warningBg} />
            <StatCard label="Draft" value={0} icon="create" color={colors.gray600} bg={colors.gray100} />
          </View>
        )}

        <Text style={styles.sectionLabel}>Quick access</Text>
        <View style={styles.quickRow}>
          <PressableScale style={[styles.quickTile, shadows.card]} onPress={() => navigation.navigate('Inspections')}>
            <View style={[styles.quickIcon, { backgroundColor: colors.primary100 }]}>
              <Ionicons name="clipboard-outline" size={24} color={colors.primary} />
            </View>
            <Text style={styles.quickTitle}>Checklists</Text>
            <Text style={styles.quickSub}>Drafts & records</Text>
          </PressableScale>
          <PressableScale
            style={[styles.quickTile, shadows.card]}
            onPress={() => navigation.navigate('Inspections', { screen: 'SitePhotos' })}
          >
            <View style={[styles.quickIcon, { backgroundColor: colors.primary100 }]}>
              <Ionicons name="camera-outline" size={24} color={colors.primary} />
            </View>
            <Text style={styles.quickTitle}>Site Photos</Text>
            <Text style={styles.quickSub}>All attachments</Text>
          </PressableScale>
        </View>

        <View style={styles.recentHead}>
          <Text style={styles.sectionLabel}>Recent checklists</Text>
          <PressableScale onPress={() => navigation.navigate('Inspections')}>
            <Text style={styles.seeAll}>See all ›</Text>
          </PressableScale>
        </View>

        {loading ? (
          <View>
            <Skeleton height={84} style={{ marginBottom: 10 }} />
            <Skeleton height={84} style={{ marginBottom: 10 }} />
          </View>
        ) : recent.length === 0 ? (
          <View style={styles.card}>
            <EmptyState
              icon="clipboard-outline"
              title="No inspection records"
              message="Tap 'New Inspection' above to record an on-site ocular checklist."
            />
          </View>
        ) : (
          recent.map((item) => (
            <PressableScale
              key={item.id}
              style={[styles.recentCard, shadows.card]}
              onPress={() => openRecord(item)}
            >
              <View style={styles.recentRow}>
                <View style={styles.recentBody}>
                  <Text style={styles.recentNo}>{item.inspection_no}</Text>
                  <Text style={styles.recentTitle} numberOfLines={1}>
                    {item.project_title || 'Untitled project'}
                  </Text>
                  <Text style={styles.recentDate}>
                    {formatInspectionDate(item.inspection_date)}
                    {item.inspection_date ? ' · ' : ''}
                    {item.project_location ? item.project_location : ''}
                  </Text>
                </View>
                <View style={styles.recentRight}>
                  <StatusPill status={item.status} />
                  <Ionicons name="chevron-forward" size={16} color={colors.gray400} />
                </View>
              </View>
              {item.photos.length > 0 ? (
                <View style={styles.photoStrip}>
                  <View style={styles.photoIcons}>
                    {item.photos.slice(0, 3).map((p, i) => {
                      const uri = resolvePhotoUri(p.file_path);
                      return (
                        <Pressable
                          key={p.id}
                          style={styles.photoThumbWrap}
                          onPress={() => openPhotos(item, i)}
                          hitSlop={4}
                        >
                          {uri ? (
                            <Image source={{ uri }} style={styles.photoThumb} resizeMode="cover" />
                          ) : (
                            <View style={styles.photoThumbEmpty} />
                          )}
                        </Pressable>
                      );
                    })}
                    {item.photos.length > 3 ? (
                      <Pressable style={styles.photoMore} onPress={() => openPhotos(item, 3)} hitSlop={4}>
                        <Text style={styles.photoMoreText}>+{item.photos.length - 3}</Text>
                      </Pressable>
                    ) : null}
                  </View>
                  <View style={styles.photoCount}>
                    <Ionicons name="camera-outline" size={12} color={colors.gray500} />
                    <Text style={styles.photoCountText}>{item.photos.length}</Text>
                  </View>
                </View>
              ) : null}
            </PressableScale>
          ))
        )}
      </ScrollView>

      <NotificationsModal visible={notifOpen} onClose={() => setNotifOpen(false)} onCountChange={setUnread} />

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
    backgroundColor: colors.navy900,
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: spacing.lg,
    paddingTop: spacing.md,
    paddingBottom: spacing.lg,
  },
  headerText: {
    flex: 1,
    marginRight: spacing.md,
  },
  greeting: {
    fontFamily: fonts.bodySemi,
    fontSize: 17,
    color: colors.white,
    marginBottom: 2,
  },
  date: {
    fontFamily: fonts.body,
    fontSize: 12,
    color: 'rgba(255,255,255,0.7)',
  },
  bellBtn: {
    width: 44,
    height: 44,
    borderRadius: 22,
    backgroundColor: 'rgba(255,255,255,0.12)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  bellDot: {
    position: 'absolute',
    top: 10,
    right: 11,
    width: 9,
    height: 9,
    borderRadius: 5,
    backgroundColor: colors.danger,
    borderWidth: 1.5,
    borderColor: colors.navy900,
  },
  content: {
    backgroundColor: colors.bg,
    borderTopLeftRadius: 22,
    borderTopRightRadius: 22,
    paddingHorizontal: spacing.lg,
    paddingTop: spacing.lg,
    paddingBottom: 28,
    minHeight: '100%',
  },
  heroWrap: {
    marginBottom: spacing.xl,
  },
  hero: {
    flexDirection: 'row',
    alignItems: 'center',
    borderRadius: radii.card,
    padding: spacing.lg,
  },
  heroIcon: {
    width: 48,
    height: 48,
    borderRadius: 14,
    backgroundColor: 'rgba(255,255,255,0.16)',
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: spacing.md,
  },
  heroTextWrap: {
    flex: 1,
  },
  heroTitle: {
    fontFamily: fonts.displaySemi,
    fontSize: 16,
    color: colors.white,
    marginBottom: 2,
  },
  heroSub: {
    fontFamily: fonts.body,
    fontSize: 12,
    color: 'rgba(255,255,255,0.7)',
  },
  sectionLabel: {
    fontFamily: fonts.displaySemi,
    fontSize: 14,
    color: colors.gray700,
    marginBottom: 10,
  },
  statsRow: {
    flexDirection: 'row',
    gap: 10,
    marginBottom: spacing.xl,
  },
  statCard: {
    flex: 1,
    backgroundColor: colors.surface,
    borderRadius: radii.card,
    padding: spacing.md,
    alignItems: 'center',
  },
  statIcon: {
    width: 34,
    height: 34,
    borderRadius: 10,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 8,
  },
  statValue: {
    fontFamily: fonts.displayExtra,
    fontSize: 26,
    lineHeight: 30,
  },
  statLabel: {
    fontFamily: fonts.bodyMedium,
    fontSize: 12,
    color: colors.gray500,
    marginTop: 2,
  },
  quickRow: {
    flexDirection: 'row',
    gap: 10,
    marginBottom: spacing.xl,
  },
  quickTile: {
    flex: 1,
    backgroundColor: colors.surface,
    borderRadius: radii.card,
    padding: spacing.lg,
  },
  quickIcon: {
    width: 44,
    height: 44,
    borderRadius: 12,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 10,
  },
  quickTitle: {
    fontFamily: fonts.bodySemi,
    fontSize: 14,
    color: colors.gray800,
  },
  quickSub: {
    fontFamily: fonts.body,
    fontSize: 12,
    color: colors.gray500,
    marginTop: 2,
  },
  recentHead: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 10,
  },
  seeAll: {
    fontFamily: fonts.bodySemi,
    fontSize: 13,
    color: colors.primary,
  },
  card: {
    backgroundColor: colors.surface,
    borderRadius: radii.card,
    ...shadows.card,
  },
  recentCard: {
    backgroundColor: colors.surface,
    borderRadius: radii.card,
    padding: spacing.lg,
    marginBottom: 10,
  },
  recentRow: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  photoStrip: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 10,
    marginTop: 10,
    paddingTop: 10,
    borderTopWidth: 1,
    borderTopColor: colors.gray100,
  },
  photoIcons: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
  },
  photoThumbWrap: {
    width: 40,
    height: 40,
    borderRadius: 8,
    overflow: 'hidden',
    borderWidth: 1,
    borderColor: colors.gray200,
    backgroundColor: colors.gray50,
  },
  photoThumb: {
    width: '100%',
    height: '100%',
  },
  photoThumbEmpty: {
    width: 40,
    height: 40,
    backgroundColor: colors.gray50,
  },
  photoMore: {
    width: 40,
    height: 40,
    borderRadius: 8,
    backgroundColor: colors.gray100,
    alignItems: 'center',
    justifyContent: 'center',
  },
  photoMoreText: {
    fontFamily: fonts.bodySemi,
    fontSize: 12,
    color: colors.gray600,
  },
  photoCount: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
  },
  photoCountText: {
    fontFamily: fonts.bodyMedium,
    fontSize: 12,
    color: colors.gray500,
  },
  recentBody: {
    flex: 1,
    marginRight: spacing.md,
  },
  recentNo: {
    fontFamily: fonts.bodySemi,
    fontSize: 12,
    color: colors.primary,
    marginBottom: 2,
  },
  recentTitle: {
    fontFamily: fonts.bodySemi,
    fontSize: 14,
    color: colors.gray800,
    marginBottom: 2,
  },
  recentDate: {
    fontFamily: fonts.body,
    fontSize: 12,
    color: colors.gray500,
  },
  recentRight: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
  },
});
