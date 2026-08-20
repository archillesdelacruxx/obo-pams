import React, { useCallback, useEffect, useState } from 'react';
import { ActivityIndicator, FlatList, Modal, Pressable, StyleSheet, Text, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
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
    <Modal visible={visible} animationType="slide" transparent onRequestClose={onClose}>
      <Pressable style={styles.backdrop} onPress={onClose}>
        <Pressable style={styles.sheet} onPress={(e) => e.stopPropagation()}>
          <View style={styles.handle} />
          <View style={styles.header}>
            <View style={styles.headerTitleWrap}>
              <View style={styles.bellIcon}>
                <Ionicons name="notifications" size={17} color={colors.white} />
                {unreadCount > 0 && (
                  <View style={styles.badge}>
                    <Text style={styles.badgeText}>{unreadCount > 99 ? '99+' : unreadCount}</Text>
                  </View>
                )}
              </View>
              <Text style={styles.title}>Notifications</Text>
            </View>
            <PressableScale onPress={onClose} style={styles.closeBtn}>
              <Ionicons name="close" size={20} color={colors.gray700} />
            </PressableScale>
          </View>

          <View style={styles.toolbar}>
            <Text style={styles.toolbarHint}>
              {unreadCount > 0 ? `${unreadCount} unread` : 'You are all caught up'}
            </Text>
            {unreadCount > 0 && (
              <PressableScale onPress={markAll} disabled={markingAll} style={styles.markAll}>
                {markingAll ? (
                  <ActivityIndicator size="small" color={colors.primary} />
                ) : (
                  <Ionicons name="checkmark-done" size={15} color={colors.primary} />
                )}
                <Text style={styles.markAllText}>Mark all read</Text>
              </PressableScale>
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
              showsVerticalScrollIndicator={false}
              renderItem={({ item }) => (
                <PressableScale
                  style={[styles.row, !item.is_read && styles.rowUnread]}
                  onPress={() => markOne(item)}
                >
                  <View style={styles.rowIconWrap}>
                    <Ionicons
                      name={item.is_read ? 'mail-open-outline' : 'mail-unread-outline'}
                      size={18}
                      color={item.is_read ? colors.gray400 : colors.primary}
                    />
                  </View>
                  <View style={styles.rowBody}>
                    <Text style={[styles.rowTitle, !item.is_read && styles.rowTitleUnread]} numberOfLines={2}>
                      {item.title}
                    </Text>
                    {item.message ? (
                      <Text style={styles.rowMsg} numberOfLines={2}>
                        {item.message}
                      </Text>
                    ) : null}
                    <View style={styles.rowTimeWrap}>
                      <Ionicons name="time-outline" size={11} color={colors.gray400} />
                      <Text style={styles.rowTime}>{timeAgo(item.created_at)}</Text>
                    </View>
                  </View>
                  {!item.is_read && <View style={styles.unreadDot} />}
                </PressableScale>
              )}
            />
          )}
        </Pressable>
      </Pressable>
    </Modal>
  );
}

const styles = StyleSheet.create({
  backdrop: {
    flex: 1,
    backgroundColor: colors.overlay,
    justifyContent: 'flex-end',
  },
  sheet: {
    backgroundColor: colors.bg,
    borderTopLeftRadius: 24,
    borderTopRightRadius: 24,
    maxHeight: '85%',
    minHeight: '60%',
    paddingTop: 10,
    overflow: 'hidden',
    shadowColor: colors.gray900,
    shadowOffset: { width: 0, height: -4 },
    shadowOpacity: 0.12,
    shadowRadius: 16,
    elevation: 12,
  },
  handle: {
    alignSelf: 'center',
    width: 44,
    height: 5,
    borderRadius: 3,
    backgroundColor: colors.gray300,
    marginBottom: 8,
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: spacing.lg,
    paddingVertical: spacing.sm,
  },
  headerTitleWrap: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
  },
  bellIcon: {
    width: 34,
    height: 34,
    borderRadius: 10,
    backgroundColor: colors.primary,
    alignItems: 'center',
    justifyContent: 'center',
  },
  badge: {
    position: 'absolute',
    top: -4,
    right: -4,
    minWidth: 16,
    height: 16,
    borderRadius: 8,
    backgroundColor: colors.danger,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 3,
  },
  badgeText: {
    color: colors.white,
    fontSize: 9,
    fontFamily: fonts.bodySemi,
  },
  title: {
    fontFamily: fonts.displaySemi,
    fontSize: 18,
    color: colors.gray800,
  },
  closeBtn: {
    width: 34,
    height: 34,
    borderRadius: 17,
    backgroundColor: colors.gray100,
    alignItems: 'center',
    justifyContent: 'center',
  },
  toolbar: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: spacing.lg,
    paddingTop: 10,
    paddingBottom: 6,
  },
  toolbarHint: {
    fontFamily: fonts.bodyMedium,
    fontSize: 12.5,
    color: colors.gray500,
  },
  markAll: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    paddingHorizontal: 10,
    paddingVertical: 6,
    borderRadius: 8,
    backgroundColor: colors.primary50,
  },
  markAllText: {
    fontFamily: fonts.bodySemi,
    fontSize: 12,
    color: colors.primary,
  },
  center: {
    flex: 1,
    justifyContent: 'center',
    paddingBottom: 40,
  },
  list: {
    padding: spacing.lg,
    paddingTop: 8,
    paddingBottom: 32,
  },
  row: {
    flexDirection: 'row',
    backgroundColor: colors.white,
    borderRadius: 14,
    borderWidth: 1,
    borderColor: colors.gray200,
    padding: spacing.md,
    marginBottom: 10,
    alignItems: 'flex-start',
  },
  rowUnread: {
    borderColor: colors.primary,
    backgroundColor: colors.primary50,
  },
  rowIconWrap: {
    width: 34,
    height: 34,
    borderRadius: 10,
    backgroundColor: colors.white,
    borderWidth: 1,
    borderColor: colors.gray200,
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: spacing.md,
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
    marginBottom: 6,
  },
  rowTimeWrap: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 3,
  },
  rowTime: {
    fontFamily: fonts.bodyMedium,
    fontSize: 11.5,
    color: colors.gray400,
  },
  unreadDot: {
    width: 8,
    height: 8,
    borderRadius: 4,
    backgroundColor: colors.primary,
    marginTop: 8,
    marginLeft: 8,
  },
});