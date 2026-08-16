import React, { useCallback, useEffect, useState } from 'react';
import { ActivityIndicator, FlatList, Modal, StyleSheet, Text, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { SafeAreaView } from 'react-native-safe-area-context';
import { colors, fonts, spacing } from '../theme/tokens';
import {
  apiGetNotifications,
  apiMarkAllNotificationsRead,
  apiMarkNotificationRead,
  type AppNotification,
} from '../api/auth';
import PressableScale from './PressableScale';
import EmptyState from './EmptyState';

function timeAgo(dateStr: string): string {
  const t = new Date(dateStr.replace(' ', 'T') + 'Z').getTime();
  if (Number.isNaN(t)) return '';
  const diff = Date.now() - t;
  const min = Math.floor(diff / 60000);
  if (min < 1) return 'Just now';
  if (min < 60) return `${min}m ago`;
  const hr = Math.floor(min / 60);
  if (hr < 24) return `${hr}h ago`;
  const day = Math.floor(hr / 24);
  if (day < 7) return `${day}d ago`;
  return new Date(t).toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}

export default function NotificationsModal({
  visible,
  onClose,
  onCountChange,
}: {
  visible: boolean;
  onClose: () => void;
  onCountChange: (count: number) => void;
}) {
  const [items, setItems] = useState<AppNotification[]>([]);
  const [loading, setLoading] = useState(false);
  const [markingAll, setMarkingAll] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await apiGetNotifications();
      setItems(res.data ?? []);
    } catch {
      setItems([]);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    if (visible) void load();
  }, [visible, load]);

  const markAll = async () => {
    setMarkingAll(true);
    try {
      await apiMarkAllNotificationsRead();
      setItems((prev) => prev.map((n) => ({ ...n, is_read: 1 })));
      onCountChange(0);
    } catch {
      /* ignore */
    } finally {
      setMarkingAll(false);
    }
  };

  const markOne = async (n: AppNotification) => {
    if (n.is_read) return;
    setItems((prev) => prev.map((x) => (x.id === n.id ? { ...x, is_read: 1 } : x)));
    try {
      await apiMarkNotificationRead(n.id);
    } catch {
      /* ignore */
    }
    onCountChange(Math.max(0, items.filter((x) => !x.is_read && x.id !== n.id).length));
  };

  const unreadCount = items.filter((n) => !n.is_read).length;

  return (
    <Modal visible={visible} animationType="slide" presentationStyle="fullScreen" onRequestClose={onClose}>
      <SafeAreaView style={styles.safe} edges={['top', 'bottom']}>
        <View style={styles.header}>
          <PressableScale onPress={onClose} style={styles.closeBtn}>
            <Ionicons name="chevron-back" size={24} color={colors.gray800} />
          </PressableScale>
          <Text style={styles.title}>Notifications</Text>
          {unreadCount > 0 ? (
            <PressableScale onPress={markAll} disabled={markingAll} style={styles.markAll}>
              {markingAll ? (
                <ActivityIndicator size="small" color={colors.primary} />
              ) : (
                <Text style={styles.markAllText}>Mark all read</Text>
              )}
            </PressableScale>
          ) : (
            <View style={styles.markAll} />
          )}
        </View>

        {loading ? (
          <View style={styles.center}>
            <ActivityIndicator size="large" color={colors.primary} />
          </View>
        ) : items.length === 0 ? (
          <View style={styles.center}>
            <EmptyState
              icon="notifications-outline"
              title="No notifications"
              message="Announcements and system updates will show up here."
            />
          </View>
        ) : (
          <FlatList
            data={items}
            keyExtractor={(n) => String(n.id)}
            contentContainerStyle={styles.list}
            renderItem={({ item }) => (
              <PressableScale style={[styles.row, !item.is_read && styles.rowUnread]} onPress={() => markOne(item)}>
                <View style={[styles.dot, item.is_read ? styles.dotRead : null]} />
                <View style={styles.rowBody}>
                  <Text style={[styles.rowTitle, !item.is_read && styles.rowTitleUnread]} numberOfLines={2}>
                    {item.title}
                  </Text>
                  {item.message ? (
                    <Text style={styles.rowMsg} numberOfLines={2}>
                      {item.message}
                    </Text>
                  ) : null}
                  <Text style={styles.rowTime}>{timeAgo(item.created_at)}</Text>
                </View>
              </PressableScale>
            )}
          />
        )}
      </SafeAreaView>
    </Modal>
  );
}

const styles = StyleSheet.create({
  safe: {
    flex: 1,
    backgroundColor: colors.bg,
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.md,
    borderBottomWidth: 1,
    borderBottomColor: colors.gray200,
    backgroundColor: colors.white,
  },
  closeBtn: {
    width: 38,
    height: 38,
    borderRadius: 19,
    backgroundColor: colors.gray100,
    alignItems: 'center',
    justifyContent: 'center',
  },
  title: {
    fontFamily: fonts.displaySemi,
    fontSize: 17,
    color: colors.gray800,
    flex: 1,
    marginLeft: spacing.md,
  },
  markAll: {
    minWidth: 78,
    alignItems: 'flex-end',
  },
  markAllText: {
    fontFamily: fonts.bodySemi,
    fontSize: 12.5,
    color: colors.primary,
  },
  center: {
    flex: 1,
    justifyContent: 'center',
  },
  list: {
    padding: spacing.lg,
    paddingBottom: 40,
  },
  row: {
    flexDirection: 'row',
    backgroundColor: colors.white,
    borderRadius: 14,
    borderWidth: 1,
    borderColor: colors.gray200,
    padding: spacing.lg,
    marginBottom: 10,
  },
  rowUnread: {
    borderColor: colors.primary,
  },
  dot: {
    width: 8,
    height: 8,
    borderRadius: 4,
    backgroundColor: colors.primary,
    marginTop: 6,
    marginRight: spacing.md,
  },
  dotRead: {
    backgroundColor: colors.gray300,
  },
  rowBody: {
    flex: 1,
  },
  rowTitle: {
    fontFamily: fonts.bodySemi,
    fontSize: 14,
    color: colors.gray700,
    marginBottom: 2,
  },
  rowTitleUnread: {
    color: colors.gray900,
  },
  rowMsg: {
    fontFamily: fonts.body,
    fontSize: 13,
    color: colors.gray500,
    lineHeight: 18,
    marginBottom: 4,
  },
  rowTime: {
    fontFamily: fonts.bodyMedium,
    fontSize: 11.5,
    color: colors.gray400,
  },
});