import { Platform } from 'react-native';
import Constants from 'expo-constants';

const CHANNEL_ID = 'inspection-status';

/* Remote push was removed from Expo Go in SDK 53 (Android throws at runtime),
   so notifications only work in a development/production build. Detect Expo Go
   and lazily load the native module with try/catch so nothing crashes there. */
const inExpoGo = Constants.executionEnvironment === 'storeClient';

type NotificationsModule = typeof import('expo-notifications');
let moduleRef: NotificationsModule | null | undefined;

async function getNotifications(): Promise<NotificationsModule | null> {
  if (inExpoGo) return null;
  if (moduleRef === undefined) {
    try {
      moduleRef = await import('expo-notifications');
    } catch {
      moduleRef = null;
    }
  }
  return moduleRef;
}

let handlerSet = false;

/* Must be set for the app to show notifications while running in foreground. */
export async function configureNotifications(): Promise<void> {
  const Notifications = await getNotifications();
  if (!Notifications || handlerSet) return;
  handlerSet = true;
  Notifications.setNotificationHandler({
    handleNotification: async () => ({
      shouldShowBanner: true,
      shouldShowList: true,
      shouldPlaySound: true,
      shouldSetBadge: false,
    }),
  });
}

export async function ensureNotificationsPermission(): Promise<boolean> {
  try {
    const Notifications = await getNotifications();
    if (!Notifications) return false;
    if (Platform.OS === 'android') {
      await Notifications.setNotificationChannelAsync(CHANNEL_ID, {
        name: 'Inspection Status',
        importance: Notifications.AndroidImportance.HIGH,
        vibrationPattern: [0, 250, 250, 250],
      });
    }
    const existing = await Notifications.getPermissionsAsync();
    if (existing.granted) return true;
    const req = await Notifications.requestPermissionsAsync();
    return req.granted;
  } catch {
    return false;
  }
}

export interface StatusChangeNotification {
  applicationNo: string;
  projectTitle: string;
  status: 'Approved' | 'Rejected';
}

/* Fires a local push notification when the server marks an inspection
   (by application no.) as Approved or Rejected. */
export async function notifyStatusChange({
  applicationNo,
  projectTitle,
  status,
}: StatusChangeNotification): Promise<void> {
  try {
    const granted = await ensureNotificationsPermission();
    if (!granted) return;
    const Notifications = await getNotifications();
    if (!Notifications) return;
    const appNo = applicationNo && applicationNo.trim() ? `App #${applicationNo.trim()} · ` : '';
    await Notifications.scheduleNotificationAsync({
      content: {
        title: status === 'Approved' ? 'Inspection Approved' : 'Inspection Rejected',
        body: `${appNo}${projectTitle || 'Untitled project'} has been ${
          status === 'Approved' ? 'approved' : 'rejected'
        }.`,
        sound: 'default',
        data: { applicationNo, status },
      },
      trigger: null,
    });
  } catch {
    /* notifications are best-effort — never break sync over them */
  }
}
