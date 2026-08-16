import React, { useState } from 'react';
import { Pressable, StyleSheet, Text, TextInput, View, ViewStyle } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { colors, fonts, radii, spacing } from '../theme/tokens';

interface Props {
  value: string;
  onChangeText: (text: string) => void;
  placeholder?: string;
  icon?: keyof typeof Ionicons.glyphMap;
  secure?: boolean;
  autoFocus?: boolean;
  editable?: boolean;
  autoCapitalize?: 'none' | 'sentences' | 'words' | 'characters';
  keyboardType?: 'default' | 'email-address' | 'number-pad' | 'phone-pad';
  returnKeyType?: 'next' | 'done' | 'go';
  onSubmitEditing?: () => void;
  error?: string | null;
  containerStyle?: ViewStyle;
  label?: string;
}

export default React.forwardRef<TextInput, Props>(function FormInput(
  {
    value,
    onChangeText,
    placeholder,
    icon,
    secure,
    autoFocus,
    editable,
    autoCapitalize = 'none',
    keyboardType = 'default',
    returnKeyType = 'next',
    onSubmitEditing,
    error,
    containerStyle,
    label,
  }: Props,
  ref,
) {
  const [focused, setFocused] = useState(false);
  const [showSecret, setShowSecret] = useState(false);
  const isSecret = !!secure;

  const borderColor = error ? colors.danger : focused ? colors.primary : colors.gray200;

  return (
    <View style={[styles.wrap, containerStyle]}>
      {label ? <Text style={styles.label}>{label}</Text> : null}
      <View style={[styles.field, { borderColor, borderWidth: focused || error ? 1.5 : 1 }]}>
        {icon ? (
          <Ionicons name={icon} size={18} color={error ? colors.danger : focused ? colors.primary : colors.gray400} style={styles.icon} />
        ) : null}
        <TextInput
          ref={ref}
          value={value}
          onChangeText={onChangeText}
          placeholder={placeholder}
          placeholderTextColor={colors.gray400}
          style={styles.input}
          secureTextEntry={isSecret && !showSecret}
          autoFocus={autoFocus}
          editable={editable}
          autoCapitalize={autoCapitalize}
          keyboardType={keyboardType}
          returnKeyType={returnKeyType}
          onSubmitEditing={onSubmitEditing}
          onFocus={() => setFocused(true)}
          onBlur={() => setFocused(false)}
          blurOnSubmit={returnKeyType === 'done'}
        />
        {isSecret ? (
          <PressableIcon onPress={() => setShowSecret((s) => !s)} name={showSecret ? 'eye-off-outline' : 'eye-outline'} color={colors.gray500} />
        ) : null}
      </View>
      {error ? <Text style={styles.error}>{error}</Text> : null}
    </View>
  );
});

function PressableIcon({
  name,
  color,
  onPress,
}: {
  name: keyof typeof Ionicons.glyphMap;
  color: string;
  onPress: () => void;
}) {
  return (
    <PressableArea onPress={onPress}>
      <Ionicons name={name} size={20} color={color} />
    </PressableArea>
  );
}

function PressableArea({ onPress, children }: { onPress: () => void; children: React.ReactNode }) {
  return (
    <View style={styles.iconBtn}>
      <Pressable onPress={onPress}>{children}</Pressable>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    marginBottom: 14,
  },
  label: {
    fontFamily: fonts.bodyMedium,
    fontSize: 13,
    color: colors.gray700,
    marginBottom: 6,
  },
  field: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: colors.white,
    borderRadius: radii.input,
    paddingHorizontal: spacing.md,
    height: 52,
  },
  icon: {
    marginRight: 10,
  },
  input: {
    flex: 1,
    fontFamily: fonts.body,
    fontSize: 16,
    color: colors.gray900,
    paddingVertical: 0,
  },
  iconBtn: {
    padding: 6,
    marginRight: -6,
  },
  error: {
    fontFamily: fonts.body,
    fontSize: 12,
    color: colors.danger,
    marginTop: 6,
  },
});
