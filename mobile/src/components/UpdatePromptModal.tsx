import React, { useCallback, useEffect, useRef, useState } from 'react';
import { ActivityIndicator, AppState, Modal, Pressable, StyleSheet, Text, View } from 'react-native';
import * as Updates from 'expo-updates';
import { Ionicons } from '@expo/vector-icons';
import { colors, fonts, radii, shadows } from '../theme/tokens';

export default function UpdatePromptModal() {
  const { isUpdateAvailable, isDownloading, downloadProgress, isUpdatePending } = Updates.useUpdates();
  const [dismissed, setDismissed] = useState(false);
  const [checking, setChecking] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const checkedOnce = useRef(false);

  const doCheck = useCallback(async () => {
    try {
      setChecking(true);
      setError(null);
      const res = await Updates.checkForUpdateAsync();
      if (res.isAvailable) {
        setDismissed(false);
      }
    } catch {
      /* offline / Expo Go / dev — silently ignore */
    } finally {
      setChecking(false);
    }
  }, []);

  useEffect(() => {
    if (checkedOnce.current) return;
    checkedOnce.current = true;
    void doCheck();
  }, [doCheck]);

  useEffect(() => {
    const sub = AppState.addEventListener('change', (state) => {
      if (state === 'active') void doCheck();
    });
    return () => sub.remove();
  }, [doCheck]);

  const onDownload = useCallback(async () => {
    try {
      setError(null);
      await Updates.fetchUpdateAsync();
    } catch {
      setError('Download failed. Check your internet connection and try again.');
    }
  }, []);

  useEffect(() => {
    if (isUpdatePending) {
      void Updates.reloadAsync();
    }
  }, [isUpdatePending]);

  const visible = isUpdateAvailable && !isUpdatePending && !dismissed;
  const progress = downloadProgress != null ? Math.round(downloadProgress * 100) : 0;

  return (
    <Modal visible={visible} transparent animationType="fade" onRequestClose={() => setDismissed(true)}>
      <View style={styles.backdrop}>
        <View style={styles.card}>
          <View style={styles.iconWrap}>
            <Ionicons name="cloud-download" size={30} color={colors.white} />
          </View>
          <Text style={styles.title}>New version available</Text>
          <Text style={styles.body}>
            A new update for PAMS Mobile is ready. Download it now to get the latest improvements and fixes.
          </Text>

          {isDownloading ? (
            <View style={styles.downloadingWrap}>
              <ActivityIndicator color={colors.primary} size="small" />
              <Text style={styles.downloadingText}>Downloading update… {progress}%</Text>
            </View>
          ) : null}

          {error ? <Text style={styles.error}>{error}</Text> : null}

          <Pressable style={styles.primaryBtn} onPress={onDownload} disabled={isDownloading}>
            <Text style={styles.primaryBtnText}>{isDownloading ? 'Downloading…' : 'Download & Restart'}</Text>
          </Pressable>
          <Pressable style={styles.secondaryBtn} onPress={() => setDismissed(true)} disabled={isDownloading}>
            <Text style={styles.secondaryBtnText}>Not Now</Text>
          </Pressable>
        </View>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  backdrop: {
    flex: 1,
    backgroundColor: colors.overlay,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 24,
  },
  card: {
    width: '100%',
    maxWidth: 360,
    backgroundColor: colors.surface,
    borderRadius: radii.card,
    padding: 24,
    alignItems: 'center',
    ...shadows.card,
  },
  iconWrap: {
    width: 56,
    height: 56,
    borderRadius: 28,
    backgroundColor: colors.primary,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 16,
  },
  title: {
    fontFamily: fonts.displaySemi,
    fontSize: 18,
    color: colors.gray900,
    textAlign: 'center',
  },
  body: {
    fontFamily: fonts.body,
    fontSize: 14,
    color: colors.gray600,
    textAlign: 'center',
    marginTop: 8,
    marginBottom: 20,
    lineHeight: 20,
  },
  downloadingWrap: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 16,
  },
  downloadingText: {
    fontFamily: fonts.bodyMedium,
    fontSize: 13,
    color: colors.gray700,
    marginLeft: 8,
  },
  error: {
    fontFamily: fonts.body,
    fontSize: 13,
    color: colors.danger,
    textAlign: 'center',
    marginBottom: 12,
  },
  primaryBtn: {
    width: '100%',
    backgroundColor: colors.primary,
    borderRadius: radii.input,
    paddingVertical: 14,
    alignItems: 'center',
  },
  primaryBtnText: {
    fontFamily: fonts.bodySemi,
    fontSize: 15,
    color: colors.white,
  },
  secondaryBtn: {
    width: '100%',
    paddingVertical: 12,
    alignItems: 'center',
    marginTop: 4,
  },
  secondaryBtnText: {
    fontFamily: fonts.bodyMedium,
    fontSize: 14,
    color: colors.gray600,
  },
});
