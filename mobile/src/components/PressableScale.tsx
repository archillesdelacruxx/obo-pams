import React from 'react';
import { Pressable, StyleProp, StyleSheet, ViewStyle } from 'react-native';

interface Props {
  onPress?: () => void;
  disabled?: boolean;
  style?: StyleProp<ViewStyle>;
  children: React.ReactNode;
  testID?: string;
}

export default function PressableScale({ onPress, disabled, style, children, testID }: Props) {
  return (
    <Pressable
      onPress={onPress}
      disabled={disabled}
      testID={testID}
      style={({ pressed }) => [
        {
          transform: [{ scale: pressed && !disabled ? 0.98 : 1 }],
          opacity: disabled ? 0.6 : pressed ? 0.9 : 1,
        },
        style,
      ]}
    >
      {children}
    </Pressable>
  );
}

export const styles = StyleSheet.create({});
