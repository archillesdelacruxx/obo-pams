import React from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { colors, fonts } from '../../theme/tokens';
import type { ItemResult } from '../../types';

const OPTIONS: {
  value: ItemResult;
  label: string;
  icon: keyof typeof Ionicons.glyphMap;
  activeBg: string;
}[] = [
  { value: 'Pass', label: 'Pass', icon: 'checkmark', activeBg: colors.success },
  { value: 'Fail', label: 'Fail', icon: 'close', activeBg: colors.danger },
  { value: 'N/A', label: 'N/A', icon: 'remove', activeBg: colors.gray500 },
];

interface Props {
  value: ItemResult | null;
  onChange: (value: ItemResult) => void;
  disabled?: boolean;
}

export default function ResultSegmented({ value, onChange, disabled }: Props) {
  return (
    <View style={styles.row}>
      {OPTIONS.map((opt) => {
        const selected = value === opt.value;
        return (
          <Pressable
            key={opt.value}
            onPress={() => onChange(opt.value)}
            disabled={disabled}
            style={[styles.seg, selected && { backgroundColor: opt.activeBg, borderColor: opt.activeBg }]}
          >
            <Ionicons name={opt.icon} size={13} color={selected ? colors.white : colors.gray500} />
            <Text style={[styles.segText, selected && { color: colors.white }]}>{opt.label}</Text>
          </Pressable>
        );
      })}
    </View>
  );
}

const styles = StyleSheet.create({
  row: {
    flexDirection: 'row',
    gap: 6,
  },
  seg: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 4,
    paddingVertical: 7,
    borderRadius: 9,
    borderWidth: 1,
    borderColor: colors.gray300,
    backgroundColor: colors.white,
  },
  segText: {
    fontFamily: fonts.bodySemi,
    fontSize: 12.5,
    color: colors.gray600,
  },
});
