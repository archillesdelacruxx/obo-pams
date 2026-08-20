import React, { useState } from 'react';
import { Modal, Pressable, StyleSheet, Text, TextInput, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { colors, fonts } from '../../theme/tokens';

function pad(n: number): string {
  return n < 10 ? `0${n}` : String(n);
}

function toDateStr(d: Date): string {
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
const MONTHS_FULL = [
  'January',
  'February',
  'March',
  'April',
  'May',
  'June',
  'July',
  'August',
  'September',
  'October',
  'November',
  'December',
];
const WEEKDAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

function toDisplayDateStr(d: Date): string {
  return `${WEEKDAYS[d.getDay()]}, ${MONTHS[d.getMonth()]} ${d.getDate()}, ${d.getFullYear()}`;
}

function startOfMonth(d: Date): Date {
  return new Date(d.getFullYear(), d.getMonth(), 1);
}

function buildMonthGrid(anchor: Date): (Date | null)[][] {
  const first = startOfMonth(anchor);
  const startWeekday = first.getDay();
  const daysInMonth = new Date(anchor.getFullYear(), anchor.getMonth() + 1, 0).getDate();
  const cells: (Date | null)[][] = [];
  let row: (Date | null)[] = [];
  for (let i = 0; i < startWeekday; i++) row.push(null);
  for (let d = 1; d <= daysInMonth; d++) {
    row.push(new Date(anchor.getFullYear(), anchor.getMonth(), d));
    if (row.length === 7) {
      cells.push(row);
      row = [];
    }
  }
  while (row.length < 7) row.push(null);
  if (row.length) cells.push(row);
  return cells;
}

function sameDay(a: Date, b: Date): boolean {
  return (
    a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate()
  );
}

function toTimeStr(d: Date): string {
  let h = d.getHours();
  const m = pad(d.getMinutes());
  const ap = h >= 12 ? 'PM' : 'AM';
  h = h % 12 || 12;
  return h + ':' + m + ' ' + ap;
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
        onChangeText={(v) => onChange(v.toUpperCase())}
        placeholder={placeholder}
        placeholderTextColor={colors.gray400}
        keyboardType={keyboardType}
        multiline={multiline}
        autoCapitalize="characters"
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

function CalendarModal({
  visible,
  initial,
  onSelect,
  onClose,
}: {
  visible: boolean;
  initial: Date;
  onSelect: (d: Date) => void;
  onClose: () => void;
}) {
  const [anchor, setAnchor] = useState(startOfMonth(initial));
  const [selected, setSelected] = useState<Date | null>(initial);
  const today = new Date();
  const grid = buildMonthGrid(anchor);

  const prevMonth = () => setAnchor(new Date(anchor.getFullYear(), anchor.getMonth() - 1, 1));
  const nextMonth = () => setAnchor(new Date(anchor.getFullYear(), anchor.getMonth() + 1, 1));

  return (
    <Modal visible={visible} transparent animationType="slide" onRequestClose={onClose}>
      <Pressable style={styles.calBackdrop} onPress={onClose}>
        <View style={styles.calSheet}>
          <View style={styles.calHandle} />
          <View style={styles.calHead}>
            <Pressable style={styles.calNavBtn} onPress={prevMonth} hitSlop={8}>
              <Ionicons name="chevron-back" size={18} color={colors.gray700} />
            </Pressable>
            <Text style={styles.calMonth}>
              {MONTHS_FULL[anchor.getMonth()]} {anchor.getFullYear()}
            </Text>
            <Pressable style={styles.calNavBtn} onPress={nextMonth} hitSlop={8}>
              <Ionicons name="chevron-forward" size={18} color={colors.gray700} />
            </Pressable>
          </View>

          <View style={styles.calWeekRow}>
            {WEEKDAYS.map((w, i) => (
              <Text key={w} style={[styles.calWeek, i === 0 || i === 6 ? { color: colors.gray400 } : null]}>
                {w[0]}
              </Text>
            ))}
          </View>

          <View>
            {grid.map((row, ri) => (
              <View key={ri} style={styles.calRow}>
                {row.map((d, ci) => {
                  if (!d) return <View key={ci} style={styles.calCell} />;
                  const isSel = selected ? sameDay(d, selected) : false;
                  const isToday = sameDay(d, today);
                  const weekend = ci === 0 || ci === 6;
                  return (
                    <Pressable
                      key={ci}
                      style={styles.calCell}
                      onPress={() => {
                        setSelected(d);
                        onSelect(d);
                      }}
                    >
                      <View style={[styles.calDay, isSel && styles.calDaySel]}>
                        <Text
                          style={[
                            styles.calDayText,
                            weekend && !isSel && styles.calDayWeekend,
                            isToday && !isSel && styles.calDayToday,
                            isSel && styles.calDayTextSel,
                          ]}
                        >
                          {d.getDate()}
                        </Text>
                      </View>
                    </Pressable>
                  );
                })}
              </View>
            ))}
          </View>

          <Pressable style={styles.calDone} onPress={onClose}>
            <Text style={styles.calDoneText}>Done</Text>
          </Pressable>
        </View>
      </Pressable>
    </Modal>
  );
}

function parseTime(value: string): { hour: number; minute: number; ap: 'AM' | 'PM' } | null {
  const m = value.match(/^(\d{1,2}):(\d{2})\s*(AM|PM)$/i);
  if (!m) return null;
  return { hour: parseInt(m[1], 10), minute: parseInt(m[2], 10), ap: (m[3].toUpperCase() as 'AM' | 'PM') };
}

function TimePickerModal({
  visible,
  initial,
  onSelect,
  onClose,
}: {
  visible: boolean;
  initial: string | null;
  onSelect: (d: Date) => void;
  onClose: () => void;
}) {
  const parsed = initial ? parseTime(initial) : null;
  const base = parsed ? parsed : { hour: 12, minute: 0, ap: 'AM' as const };
  const [hourStr, setHourStr] = useState(String(base.hour));
  const [minuteStr, setMinuteStr] = useState(String(base.minute).padStart(2, '0'));
  const [ap, setAp] = useState<'AM' | 'PM'>(base.ap);

  const parseHour = (s: string): number => {
    const n = parseInt(s, 10);
    if (Number.isNaN(n)) return 12;
    if (n < 1) return 1;
    if (n > 12) return 12;
    return n;
  };
  const parseMinute = (s: string): number => {
    const n = parseInt(s, 10);
    if (Number.isNaN(n)) return 0;
    if (n < 0) return 0;
    if (n > 59) return 59;
    return n;
  };

  const cycleHour = (dir: 1 | -1) => {
    const h = parseHour(hourStr);
    const next = ((h + dir - 1 + 12) % 12) + 1;
    setHourStr(String(next));
  };
  const cycleMinute = (dir: 1 | -1) => {
    const m = parseMinute(minuteStr);
    setMinuteStr(String((m + dir + 60) % 60).padStart(2, '0'));
  };

  const commit = () => {
    const h = parseHour(hourStr);
    const m = parseMinute(minuteStr);
    const d = new Date();
    let h24 = h % 12;
    if (ap === 'PM') h24 += 12;
    d.setHours(h24, m, 0, 0);
    onSelect(d);
    onClose();
  };

  return (
    <Modal visible={visible} transparent animationType="slide" onRequestClose={onClose}>
      <Pressable style={styles.calBackdrop} onPress={onClose}>
        <View style={styles.calSheet}>
          <View style={styles.calHandle} />
          <View style={styles.timeHead}>
            <View style={styles.timeIcon}>
              <Ionicons name="time-outline" size={16} color={colors.white} />
            </View>
            <Text style={styles.timeTitle}>Select Time</Text>
          </View>

          <View style={styles.timeDisplay}>
            <Text style={styles.timeDisplayMain}>
              {parseHour(hourStr)}
              <Text style={styles.timeColon}>:</Text>
              {String(parseMinute(minuteStr)).padStart(2, '0')}
            </Text>
            <Text style={styles.timeDisplayAp}>{ap}</Text>
          </View>

          <View style={styles.timeGrid}>
            <View style={styles.timeCol}>
              <Text style={styles.timeColLabel}>Hour</Text>
              <Pressable style={styles.timeStepBtn} onPress={() => cycleHour(1)} hitSlop={4}>
                <Ionicons name="chevron-up" size={22} color={colors.primary} />
              </Pressable>
              <TextInput
                style={styles.timeValueInput}
                value={hourStr}
                onChangeText={setHourStr}
                keyboardType="number-pad"
                maxLength={2}
                selectTextOnFocus
                textAlign="center"
              />
              <Pressable style={styles.timeStepBtn} onPress={() => cycleHour(-1)} hitSlop={4}>
                <Ionicons name="chevron-down" size={22} color={colors.primary} />
              </Pressable>
            </View>

            <View style={styles.timeCol}>
              <Text style={styles.timeColLabel}>Minute</Text>
              <Pressable style={styles.timeStepBtn} onPress={() => cycleMinute(1)} hitSlop={4}>
                <Ionicons name="chevron-up" size={22} color={colors.primary} />
              </Pressable>
              <TextInput
                style={styles.timeValueInput}
                value={minuteStr}
                onChangeText={(t) => setMinuteStr(t.replace(/[^0-9]/g, ''))}
                keyboardType="number-pad"
                maxLength={2}
                selectTextOnFocus
                textAlign="center"
              />
              <Pressable style={styles.timeStepBtn} onPress={() => cycleMinute(-1)} hitSlop={4}>
                <Ionicons name="chevron-down" size={22} color={colors.primary} />
              </Pressable>
            </View>

            <View style={styles.timeCol}>
              <Text style={styles.timeColLabel}>Period</Text>
              <View style={styles.apBox}>
                <Pressable
                  style={[styles.apBtn, ap === 'AM' && styles.apBtnActive]}
                  onPress={() => setAp('AM')}
                >
                  <Text style={[styles.apText, ap === 'AM' && styles.apTextActive]}>AM</Text>
                </Pressable>
                <Pressable
                  style={[styles.apBtn, ap === 'PM' && styles.apBtnActive]}
                  onPress={() => setAp('PM')}
                >
                  <Text style={[styles.apText, ap === 'PM' && styles.apTextActive]}>PM</Text>
                </Pressable>
              </View>
            </View>
          </View>

          <Pressable style={styles.calDone} onPress={commit}>
            <Ionicons name="checkmark" size={17} color={colors.white} />
            <Text style={styles.calDoneText}>Set Time</Text>
          </Pressable>
        </View>
      </Pressable>
    </Modal>
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
  const display = (() => {
    if (!value) return null;
    if (mode === 'date') {
      const d = new Date(`${value}T00:00:00`);
      return Number.isNaN(d.getTime()) ? value : toDisplayDateStr(d);
    }
    return value;
  })();
  return (
    <View style={[styles.fieldWrap, { flex: 1, minWidth: 0 }]}>
      <Text style={styles.fieldLabel}>
        {label}
        {required ? <Text style={{ color: colors.danger }}> *</Text> : null}
      </Text>
      <Pressable style={styles.pickerInput} onPress={() => setShow(true)}>
        <View style={styles.pickerIcon}>
          <Ionicons name={mode === 'date' ? 'calendar-outline' : 'time-outline'} size={16} color={colors.white} />
        </View>
        <Text style={[styles.inputText, !value && { color: colors.gray400 }, value && { color: colors.gray800, fontFamily: fonts.bodySemi }]}>
          {display || (mode === 'date' ? 'Select a date' : 'Select a time')}
        </Text>
        <Ionicons name="chevron-down" size={15} color={colors.gray400} />
      </Pressable>
      {mode === 'date' ? (
        <CalendarModal
          visible={show}
          initial={value ? new Date(`${value}T00:00:00`) : new Date()}
          onSelect={(d) => onChange(fmt(d))}
          onClose={() => setShow(false)}
        />
      ) : (
        <TimePickerModal
          visible={show}
          initial={value}
          onSelect={(d) => onChange(fmt(d))}
          onClose={() => setShow(false)}
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
    textTransform: 'uppercase',
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
  pickerIcon: {
    width: 30,
    height: 30,
    borderRadius: 9,
    backgroundColor: colors.primary,
    alignItems: 'center',
    justifyContent: 'center',
  },
  calBackdrop: {
    flex: 1,
    backgroundColor: colors.overlay,
    justifyContent: 'flex-end',
  },
  calSheet: {
    backgroundColor: colors.white,
    borderTopLeftRadius: 24,
    borderTopRightRadius: 24,
    paddingTop: 10,
    paddingBottom: 28,
    paddingHorizontal: 16,
    shadowColor: colors.gray900,
    shadowOffset: { width: 0, height: -4 },
    shadowOpacity: 0.12,
    shadowRadius: 16,
    elevation: 12,
  },
  calHandle: {
    alignSelf: 'center',
    width: 44,
    height: 5,
    borderRadius: 3,
    backgroundColor: colors.gray300,
    marginBottom: 10,
  },
  calHead: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingVertical: 6,
  },
  calNavBtn: {
    width: 36,
    height: 36,
    borderRadius: 18,
    backgroundColor: colors.gray100,
    alignItems: 'center',
    justifyContent: 'center',
  },
  calMonth: {
    fontFamily: fonts.displaySemi,
    fontSize: 16,
    color: colors.gray800,
  },
  calWeekRow: {
    flexDirection: 'row',
    marginTop: 10,
    marginBottom: 4,
  },
  calWeek: {
    flex: 1,
    textAlign: 'center',
    fontFamily: fonts.bodySemi,
    fontSize: 11.5,
    color: colors.gray500,
  },
  calRow: {
    flexDirection: 'row',
  },
  calCell: {
    flex: 1,
    height: 42,
    alignItems: 'center',
    justifyContent: 'center',
  },
  calDay: {
    width: 34,
    height: 34,
    borderRadius: 17,
    alignItems: 'center',
    justifyContent: 'center',
  },
  calDaySel: {
    backgroundColor: colors.primary,
  },
  calDayText: {
    fontFamily: fonts.bodyMedium,
    fontSize: 13.5,
    color: colors.gray800,
  },
  calDayWeekend: {
    color: colors.gray400,
  },
  calDayToday: {
    color: colors.primary,
  },
  calDayTextSel: {
    color: colors.white,
    fontFamily: fonts.bodySemi,
  },
  calDone: {
    marginTop: 14,
    backgroundColor: colors.primary,
    borderRadius: 12,
    paddingVertical: 13,
    alignItems: 'center',
  },
  calDoneText: {
    fontFamily: fonts.bodySemi,
    fontSize: 14.5,
    color: colors.white,
  },
  timeHead: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    paddingVertical: 6,
  },
  timeIcon: {
    width: 28,
    height: 28,
    borderRadius: 8,
    backgroundColor: colors.primary,
    alignItems: 'center',
    justifyContent: 'center',
  },
  timeTitle: {
    fontFamily: fonts.displaySemi,
    fontSize: 16,
    color: colors.gray800,
  },
  timeDisplay: {
    flexDirection: 'row',
    alignItems: 'baseline',
    justifyContent: 'center',
    backgroundColor: colors.navy900,
    borderRadius: 16,
    paddingVertical: 16,
    marginTop: 12,
  },
  timeDisplayMain: {
    fontFamily: fonts.display,
    fontSize: 44,
    color: colors.white,
    letterSpacing: 2,
  },
  timeColon: {
    color: colors.primary,
  },
  timeDisplayAp: {
    fontFamily: fonts.bodySemi,
    fontSize: 16,
    color: 'rgba(255,255,255,0.7)',
    marginLeft: 10,
  },
  timeGrid: {
    flexDirection: 'row',
    justifyContent: 'center',
    gap: 14,
    marginTop: 16,
  },
  timeCol: {
    alignItems: 'center',
    width: 96,
  },
  timeColLabel: {
    fontFamily: fonts.bodySemi,
    fontSize: 12,
    color: colors.gray500,
    marginBottom: 8,
  },
  timeStepBtn: {
    width: 44,
    height: 38,
    borderRadius: 10,
    backgroundColor: colors.primary50,
    alignItems: 'center',
    justifyContent: 'center',
  },
  timeValueInput: {
    width: 84,
    paddingVertical: 10,
    marginVertical: 6,
    borderRadius: 12,
    borderWidth: 1.5,
    borderColor: colors.primary,
    backgroundColor: colors.white,
    fontFamily: fonts.display,
    fontSize: 26,
    color: colors.gray800,
    textAlign: 'center',
  },
  apBox: {
    gap: 6,
    marginTop: 0,
  },
  apBtn: {
    width: 84,
    paddingVertical: 11,
    borderRadius: 10,
    borderWidth: 1.5,
    borderColor: colors.gray300,
    backgroundColor: colors.white,
    alignItems: 'center',
  },
  apBtnActive: {
    backgroundColor: colors.primary,
    borderColor: colors.primary,
  },
  apText: {
    fontFamily: fonts.bodySemi,
    fontSize: 13.5,
    color: colors.gray600,
  },
  apTextActive: {
    color: colors.white,
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
