import React, { useState } from 'react';
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
import { SafeAreaView } from 'react-native-safe-area-context';
import * as ImagePicker from 'expo-image-picker';
import { Ionicons } from '@expo/vector-icons';
import { colors, fonts } from '../../theme/tokens';
import { useAuth } from '../../context/AuthContext';
import { apiProfileUpdate, apiProfileUploadPhoto } from '../../api/profile';
import { photoUrl } from '../../api/inspection';

function initials(name: string): string {
  return name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((p) => p[0]?.toUpperCase() ?? '')
    .join('');
}

const MODULE_LABELS: Record<string, string> = {
  dashboard: 'Dashboard',
  notifications: 'Notifications',
  announcements: 'Announcements',
  profile: 'Profile',
  settings: 'Settings',
  'order-of-payment': 'Order of Payment Encoding',
  'op-records': 'Order of Payment Records',
  'permit-workflow': 'Permit Workflow',
  'workflow-details': 'Permit Workflow Records',
  'permit-approval-encoding': 'Permit Approval Encoding',
  'permit-approval-records': 'Permit Approval Records',
  releasing: 'Releasing Plans',
  'releasing-records': 'Releasing Records',
  'inspection-checklist': 'On-Site Ocular Inspection Checklist',
  'inspection-reports': 'Monitoring Reports',
  'inspection-review': 'Inspection Review',
  'inspection-edit': 'Inspection — Edit Checklists',
  'inspection-delete': 'Inspection — Delete Records',
  'team-leaders': 'Team Leaders',
};

export default function ProfileScreen() {
  const { user, permissions, updateUser, signOut } = useAuth();
  const [fullName, setFullName] = useState(user?.full_name ?? '');
  const [email, setEmail] = useState(user?.email ?? '');
  const [saving, setSaving] = useState(false);
  const [uploading, setUploading] = useState(false);
  const [signingOut, setSigningOut] = useState(false);

  if (!user) return null;

  const photo = photoUrl(user.profile_photo);

  const onSave = async () => {
    if (!fullName.trim()) {
      Alert.alert('Missing details', 'Full name is required.');
      return;
    }
    setSaving(true);
    try {
      await apiProfileUpdate(fullName.trim(), email.trim());
      await updateUser({ full_name: fullName.trim(), email: email.trim() });
      Alert.alert('Saved', 'Your profile has been updated.');
    } catch {
      Alert.alert('Error', 'Could not save your profile. Please try again.');
    } finally {
      setSaving(false);
    }
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
      if (res.canceled || !res.assets?.length) return;
      const a = res.assets[0];
      setUploading(true);
      try {
        const r = await apiProfileUploadPhoto({ uri: a.uri, name: a.fileName ?? 'profile.jpg', type: a.mimeType ?? 'image/jpeg' });
        await updateUser({ profile_photo: r.path });
      } catch (err) {
        const message = err instanceof Error && err.message ? err.message : 'Could not upload the photo.';
        Alert.alert('Error', message);
      } finally {
        setUploading(false);
      }
    };
    void run();
  };

  const onChangePhoto = () => {
    Alert.alert('Profile picture', 'Choose a source', [
      { text: 'Cancel', style: 'cancel' },
      { text: 'Camera', onPress: () => pickPhoto('camera') },
      { text: 'Gallery', onPress: () => pickPhoto('gallery') },
    ]);
  };

  const onSignOut = () => {
    Alert.alert('Sign out', 'Are you sure you want to sign out?', [
      { text: 'Cancel', style: 'cancel' },
      { text: 'Sign Out', style: 'destructive', onPress: () => void signOut() },
    ]);
  };

  return (
    <SafeAreaView style={styles.safe} edges={['top']}>
      <View style={styles.header}>
        <Text style={styles.headerTitle}>Profile</Text>
        <Text style={styles.headerSub}>Your account and personal information</Text>
      </View>

      <ScrollView contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
        <View style={styles.hero}>
          <View style={styles.avatarWrap}>
            {photo ? (
              <Image source={{ uri: photo }} style={styles.avatarImg} />
            ) : (
              <View style={styles.avatar}>
                <Text style={styles.avatarText}>{initials(user.full_name)}</Text>
              </View>
            )}
            <Pressable style={styles.camBtn} onPress={onChangePhoto} hitSlop={6}>
              {uploading ? (
                <ActivityIndicator size="small" color={colors.white} />
              ) : (
                <Ionicons name="camera" size={15} color={colors.white} />
              )}
            </Pressable>
          </View>
          <Text style={styles.heroName}>{user.full_name}</Text>
          <Text style={styles.heroUsername}>@{user.username}</Text>
          <Text style={styles.heroRole}>{user.role}</Text>
        </View>

        <View style={styles.card}>
          <Text style={styles.cardTitle}>Personal Information</Text>
          <Text style={styles.label}>Full Name</Text>
          <TextInput
            style={styles.input}
            value={fullName}
            onChangeText={setFullName}
            placeholder="Full name"
            placeholderTextColor={colors.gray400}
          />
          <Text style={styles.label}>Email</Text>
          <TextInput
            style={styles.input}
            value={email}
            onChangeText={setEmail}
            placeholder="Email address"
            placeholderTextColor={colors.gray400}
            autoCapitalize="none"
            keyboardType="email-address"
          />
          <Text style={styles.label}>Username</Text>
          <View style={[styles.input, styles.inputReadOnly]}>
            <Text style={styles.inputReadOnlyText}>@{user.username}</Text>
          </View>
          <Pressable style={[styles.saveBtn, saving && styles.saveBtnDisabled]} onPress={onSave} disabled={saving}>
            {saving ? (
              <ActivityIndicator size="small" color={colors.white} />
            ) : (
              <>
                <Ionicons name="checkmark" size={18} color={colors.white} />
                <Text style={styles.saveBtnText}>Save Changes</Text>
              </>
            )}
          </Pressable>
        </View>

        {permissions.length > 0 ? (
          <View style={styles.card}>
            <Text style={styles.cardTitle}>Modules</Text>
            <View style={styles.badges}>
              {permissions.map((p) => (
                <View key={p} style={styles.badge}>
                  <Text style={styles.badgeText}>{MODULE_LABELS[p] ?? p}</Text>
                </View>
              ))}
            </View>
          </View>
        ) : null}

        <Pressable style={styles.signOutBtn} onPress={onSignOut} disabled={signingOut}>
          {signingOut ? (
            <ActivityIndicator size="small" color={colors.danger} />
          ) : (
            <>
              <Ionicons name="log-out-outline" size={18} color={colors.danger} />
              <Text style={styles.signOutText}>Sign Out</Text>
            </>
          )}
        </Pressable>
      </ScrollView>
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
    paddingHorizontal: 20,
    paddingTop: 14,
    paddingBottom: 18,
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
  content: {
    padding: 16,
    paddingBottom: 40,
  },
  hero: {
    alignItems: 'center',
    paddingVertical: 20,
  },
  avatarWrap: {
    width: 104,
    height: 104,
    marginBottom: 12,
  },
  avatar: {
    width: 104,
    height: 104,
    borderRadius: 52,
    backgroundColor: colors.primary100,
    alignItems: 'center',
    justifyContent: 'center',
  },
  avatarImg: {
    width: 104,
    height: 104,
    borderRadius: 52,
    backgroundColor: colors.gray100,
  },
  avatarText: {
    fontFamily: fonts.display,
    fontSize: 36,
    color: colors.primary,
  },
  camBtn: {
    position: 'absolute',
    right: 0,
    bottom: 0,
    width: 32,
    height: 32,
    borderRadius: 16,
    backgroundColor: colors.primary,
    borderWidth: 3,
    borderColor: colors.white,
    alignItems: 'center',
    justifyContent: 'center',
  },
  heroName: {
    fontFamily: fonts.displaySemi,
    fontSize: 18,
    color: colors.gray800,
  },
  heroUsername: {
    fontFamily: fonts.body,
    fontSize: 13,
    color: colors.gray500,
    marginTop: 2,
  },
  heroRole: {
    fontFamily: fonts.bodySemi,
    fontSize: 12,
    color: colors.primary,
    textTransform: 'capitalize',
    marginTop: 6,
    backgroundColor: colors.primary100,
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 999,
    overflow: 'hidden',
  },
  card: {
    backgroundColor: colors.white,
    borderRadius: 14,
    borderWidth: 1,
    borderColor: colors.gray200,
    padding: 16,
    marginBottom: 12,
  },
  cardTitle: {
    fontFamily: fonts.displaySemi,
    fontSize: 15,
    color: colors.gray800,
    marginBottom: 12,
  },
  label: {
    fontFamily: fonts.bodyMedium,
    fontSize: 12,
    color: colors.gray500,
    marginBottom: 6,
    marginTop: 10,
  },
  input: {
    borderWidth: 1,
    borderColor: colors.gray200,
    borderRadius: 12,
    paddingHorizontal: 14,
    paddingVertical: 11,
    fontFamily: fonts.body,
    fontSize: 14,
    color: colors.gray800,
    backgroundColor: colors.white,
  },
  inputReadOnly: {
    backgroundColor: colors.gray50,
  },
  inputReadOnlyText: {
    fontFamily: fonts.body,
    fontSize: 14,
    color: colors.gray600,
  },
  saveBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.primary,
    borderRadius: 12,
    paddingVertical: 12,
    gap: 6,
    marginTop: 18,
  },
  saveBtnDisabled: {
    opacity: 0.6,
  },
  saveBtnText: {
    fontFamily: fonts.bodySemi,
    fontSize: 14,
    color: colors.white,
  },
  badges: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  badge: {
    backgroundColor: colors.primary100,
    borderWidth: 1,
    borderColor: colors.primary,
    borderRadius: 6,
    paddingHorizontal: 10,
    paddingVertical: 5,
  },
  badgeText: {
    fontFamily: fonts.bodyMedium,
    fontSize: 11.5,
    color: colors.primary,
  },
  signOutBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.white,
    borderWidth: 1,
    borderColor: colors.dangerLight,
    borderRadius: 12,
    paddingVertical: 13,
    gap: 6,
    marginTop: 8,
  },
  signOutText: {
    fontFamily: fonts.bodySemi,
    fontSize: 14,
    color: colors.danger,
  },
});
