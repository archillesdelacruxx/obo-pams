import React, { useState } from 'react';
import { Modal, Pressable, StyleSheet, Text, TextInput, View } from 'react-native';
import DateTimePicker from '@react-native-community/datetimepicker';
import { Ionicons } from '@expo/vector-icons';
import { colors, fonts } from '../../theme/tokens';

function pad(n: number): string {
  return n < 10 ? `0${n}` : String(n);
}

function toDateStr(d: Date): string {
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

function toTimeStr(d: Date): string {
  return `${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

export function Field({
  label,
  required,
  value,
  onChange,
  placeholder,
  keyboardType,
  multiline,
  autoCapitalize,
}: {
  label: string;
  required?: boolean;
  value: string;
  onChange: (v: string) => void;
  placeholder?: string;
  keyboardType?: 'default' | 'numeric' | 'decimal-pad' | 'phone-pad';
  multiline?: boolean;
  autoCapitalize?: 'none' | 'sentences' | 'words' | 'characters';
}) {
  return (
    <View style={styles.fieldWrap}>
      <Text style={styles.fieldLabel}>
        {label}
        {required ? <Text style={{ color: colors.danger }}> *</Text> : null}
      </Text>
      <TextInput
        style={[styles.input, multiline && styles.inputMultiline]}
        value={value}
        onChangeText={onChange}
        placeholder={placeholder}
        placeholderTextColor={colors.gray400}
        keyboardType={keyboardType}
        multiline={multiline}
        autoCapitalize={autoCapitalize}
      />
    </View>
  );
}

export function PillGroup({
  label,
  options,
  value,
  onChange,
}: {
  label: string;
  options: string[];
  value: string;
  onChange: (v: string) => void;
}) {
  return (
    <View style={styles.fieldWrap}>
      <Text style={styles.fieldLabel}>{label}</Text>
      <View style={styles.pillRow}>
        {options.map((o) => {
          const sel = value === o;
          return (
            <Pressable key={o} style={[styles.pill, sel && styles.pillActive]} onPress={() => onChange(o)}>
              <Text style={[styles.pillText, sel && styles.pillTextActive]}>{o}</Text>
            </Pressable>
          );
        })}
      </View>
    </View>
  );
}

export function PickerField({
  label,
  required,
  value,
  options,
  onChange,
  placeholder,
}: {
  label: string;
  required?: boolean;
  value: number | string | null;
  options: { value: number | string; label: string }[];
  onChange: (v: number | string) => void;
  placeholder?: string;
}) {
  const [open, setOpen] = useState(false);
  const selected = options.find((o) => String(o.value) === String(value));
  return (
    <View style={styles.fieldWrap}>
      <Text style={styles.fieldLabel}>
        {label}
        {required ? <Text style={{ color: colors.danger }}> *</Text> : null}
      </Text>
      <Pressable style={styles.pickerInput} onPress={() => setOpen(true)}>
        <Text style={[styles.inputText, !selected && { color: colors.gray400 }]} numberOfLines={1}>
          {selected ? selected.label : placeholder ?? 'Select...'}
        </Text>
        <Ionicons name="chevron-down" size={16} color={colors.gray500} />
      </Pressable>
      <Modal visible={open} transparent animationType="fade" onRequestClose={() => setOpen(false)}>
        <Pressable style={styles.modalBackdrop} onPress={() => setOpen(false)}>
          <View style={styles.modalSheet}>
            <Text style={styles.modalTitle}>{label}</Text>
            {options.map((o) => {
              const sel = String(o.value) === String(value);
              return (
                <Pressable
                  key={String(o.value)}
                  style={styles.modalOption}
                  onPress={() => {
                    onChange(o.value);
                    setOpen(false);
                  }}
                >
                  <Text style={[styles.modalOptionText, sel && styles.modalOptionTextSel]}>{o.label}</Text>
                  {sel ? <Ionicons name="checkmark" size={18} color={colors.primary} /> : null}
                </Pressable>
              );
            })}
          </View>
        </Pressable>
      </Modal>
    </View>
  );
}

export function DateField({
  label,
  required,
  value,
  onChange,
  mode,
}: {
  label: string;
  required?: boolean;
  value: string | null;
  onChange: (v: string) => void;
  mode: 'date' | 'time';
}) {
  const [show, setShow] = useState(false);
  const fmt = (d: Date) => (mode === 'date' ? toDateStr(d) : toTimeStr(d));
  return (
    <View style={[styles.fieldWrap, { flex: 1, minWidth: 0 }]}>
      <Text style={styles.fieldLabel}>
        {label}
        {required ? <Text style={{ color: colors.danger }}> *</Text> : null}
      </Text>
      <Pressable style={styles.pickerInput} onPress={() => setShow(true)}>
        <Ionicons name={mode === 'date' ? 'calendar-outline' : 'time-outline'} size={15} color={colors.gray500} />
        <Text style={[styles.inputText, !value && { color: colors.gray400 }]}>
          {value ? fmt(new Date(value)) : mode === 'date' ? 'Select a date' : 'Select a time'}
        </Text>
      </Pressable>
      {show && (
        <DateTimePicker
          value={value ? new Date(value) : new Date()}
          mode={mode}
          is24Hour
          display="default"
          onChange={(event, date) => {
            setShow(false);
            if (event.type === 'set' && date) onChange(fmt(date));
          }}
        />
      )}
    </View>
  );
}

export function InfoRow({ label, value }: { label: string; value: string }) {
  return (
    <View style={styles.infoRow}>
      <Text style={styles.infoLabel}>{label}</Text>
      <Text style={styles.infoValue}>{value || '—'}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  fieldWrap: {
    marginBottom: 13,
    minWidth: 0,
  },
  fieldLabel: {
    fontFamily: fonts.bodyMedium,
    fontSize: 12.5,
    color: colors.gray700,
    marginBottom: 6,
  },
  input: {
    borderWidth: 1,
    borderColor: colors.gray300,
    borderRadius: 12,
    paddingHorizontal: 12,
    paddingVertical: 10,
    fontFamily: fonts.body,
    fontSize: 14,
    color: colors.gray800,
    backgroundColor: colors.white,
  },
  inputMultiline: {
    minHeight: 84,
    textAlignVertical: 'top',
  },
  inputText: {
    fontFamily: fonts.body,
    fontSize: 14,
    color: colors.gray800,
    flex: 1,
  },
  pickerInput: {
    borderWidth: 1,
    borderColor: colors.gray300,
    borderRadius: 12,
    paddingHorizontal: 12,
    paddingVertical: 11,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 8,
    backgroundColor: colors.white,
  },
  pillRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  pill: {
    paddingHorizontal: 13,
    paddingVertical: 8,
    borderRadius: 999,
    borderWidth: 1,
    borderColor: colors.gray300,
    backgroundColor: colors.white,
  },
  pillActive: {
    backgroundColor: colors.primary,
    borderColor: colors.primary,
  },
  pillText: {
    fontFamily: fonts.bodyMedium,
    fontSize: 12.5,
    color: colors.gray600,
  },
  pillTextActive: {
    color: colors.white,
  },
  modalBackdrop: {
    flex: 1,
    backgroundColor: colors.overlay,
    justifyContent: 'flex-end',
  },
  modalSheet: {
    backgroundColor: colors.white,
    borderTopLeftRadius: 18,
    borderTopRightRadius: 18,
    padding: 20,
    paddingBottom: 32,
  },
  modalTitle: {
    fontFamily: fonts.displaySemi,
    fontSize: 16,
    color: colors.gray800,
    marginBottom: 12,
  },
  modalOption: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingVertical: 13,
    borderBottomWidth: 1,
    borderBottomColor: colors.gray100,
  },
  modalOptionText: {
    fontFamily: fonts.body,
    fontSize: 14.5,
    color: colors.gray700,
  },
  modalOptionTextSel: {
    color: colors.primary,
    fontFamily: fonts.bodySemi,
  },
  infoRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    gap: 12,
    paddingVertical: 7,
  },
  infoLabel: {
    fontFamily: fonts.bodyMedium,
    fontSize: 13,
    color: colors.gray500,
    flexShrink: 0,
  },
  infoValue: {
    fontFamily: fonts.body,
    fontSize: 13.5,
    color: colors.gray800,
    flex: 1,
    textAlign: 'right',
  },
});
