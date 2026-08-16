import React from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { colors, fonts } from '../theme/tokens';

interface Props {
  icon: keyof typeof Ionicons.glyphMap;
  title: string;
  message: string;
}

export default function EmptyState({ icon, title, message }: Props) {
  return (
    <View style={styles.wrap}>
      <View style={styles.iconCircle}>
        <Ionicons name={icon} size={34} color={colors.primary} />
      </View>
      <Text style={styles.title}>{title}</Text>
      <Text style={styles.message}>{message}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    alignItems: 'center',
    paddingVertical: 32,
    paddingHorizontal: 24,
  },
  iconCircle: {
    width: 72,
    height: 72,
    borderRadius: 36,
    backgroundColor: colors.primary100,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 16,
  },
  title: {
    fontFamily: fonts.displaySemi,
    fontSize: 16,
    color: colors.gray800,
    marginBottom: 6,
  },
  message: {
    fontFamily: fonts.body,
    fontSize: 13,
    color: colors.gray500,
    textAlign: 'center',
    lineHeight: 20,
  },
});
