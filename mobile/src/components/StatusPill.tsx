import React from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { colors, fonts } from '../theme/tokens';

export type StatusTone = 'neutral' | 'warning' | 'success' | 'danger' | 'info';

const TONE_MAP: Record<string, { bg: string; fg: string; label: string; tone: StatusTone }> = {
  Draft: { bg: colors.gray100, fg: colors.gray600, label: 'Draft', tone: 'neutral' },
  Rejected: { bg: colors.dangerLight, fg: colors.danger, label: 'Rejected', tone: 'danger' },
  'Under Review': { bg: colors.warningBg, fg: colors.warning, label: 'Under Review', tone: 'warning' },
  Approved: { bg: colors.successLight, fg: colors.success, label: 'Approved', tone: 'success' },
  Completed: { bg: colors.successLight, fg: colors.success, label: 'Completed', tone: 'success' },
  Passed: { bg: colors.successLight, fg: colors.success, label: 'Passed', tone: 'success' },
  Failed: { bg: colors.dangerLight, fg: colors.danger, label: 'Failed', tone: 'danger' },
  default: { bg: colors.primary100, fg: colors.primary, label: '', tone: 'info' },
};

export default function StatusPill({ status }: { status: string }) {
  const cfg = TONE_MAP[status] ?? TONE_MAP.default;
  return (
    <View style={[styles.pill, { backgroundColor: cfg.bg }]}>
      <View style={[styles.dot, { backgroundColor: cfg.fg }]} />
      <Text style={[styles.text, { color: cfg.fg }]}>{cfg.label || status}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  pill: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 10,
    paddingVertical: 5,
    borderRadius: 999,
    alignSelf: 'flex-start',
  },
  dot: {
    width: 6,
    height: 6,
    borderRadius: 3,
    marginRight: 6,
  },
  text: {
    fontFamily: fonts.bodySemi,
    fontSize: 12,
  },
});
