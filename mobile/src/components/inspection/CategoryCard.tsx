import React, { useState } from 'react';
import { Pressable, StyleSheet, Text, TextInput, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { colors, fonts } from '../../theme/tokens';
import ResultSegmented from './ResultSegmented';
import type { ExtraFields, ItemResult, TemplateItem } from '../../types';

const CAT_COLORS: Record<string, string> = {
  'General Safety': '#7C3AED',
  'Architectural Works': '#0EA5E9',
  'Civil / Structural Works': '#D97706',
  'Electrical Works': '#CA8A04',
  'Mechanical Works': '#059669',
  'Sanitary / Plumbing Works': '#2563EB',
  'Electronics Works': '#DB2777',
};

const SETBACK_FIELDS = ['Front', 'Rear', 'Right Side', 'Left Side'];

interface Props {
  category: string;
  items: TemplateItem[];
  results: Record<number, ItemResult>;
  onResult: (itemId: number, value: ItemResult) => void;
  extra: ExtraFields;
  onExtra: (patch: Partial<ExtraFields>) => void;
  pct: string;
  onPctChange: (v: string) => void;
  remark: string;
  onRemarkChange: (v: string) => void;
  aiEnabled: boolean;
  aiLoading?: boolean;
  onAi?: () => void;
  disabled?: boolean;
}

export default function CategoryCard({
  category,
  items,
  results,
  onResult,
  extra,
  onExtra,
  pct,
  onPctChange,
  remark,
  onRemarkChange,
  aiEnabled,
  aiLoading,
  onAi,
  disabled,
}: Props) {
  const [open, setOpen] = useState(true);
  const accent = CAT_COLORS[category] ?? colors.primary;
  const answered = items.filter((it) => results[it.id]).length;
  const passes = items.filter((it) => results[it.id] === 'Pass').length;

  return (
    <View style={[styles.card, open && styles.cardOpen]}>
      <Pressable style={styles.head} onPress={() => setOpen((o) => !o)}>
        <View style={[styles.accent, { backgroundColor: accent }]} />
        <View style={styles.headText}>
          <Text style={styles.title}>{category}</Text>
          <Text style={styles.progress}>
            {answered}/{items.length} answered · {passes} pass
          </Text>
        </View>
        <View style={[styles.progressRing, { borderColor: accent }]}>
          <Text style={[styles.progressRingText, { color: accent }]}>
            {Math.round((answered / Math.max(items.length, 1)) * 100)}%
          </Text>
        </View>
        <Ionicons
          name={open ? 'chevron-up' : 'chevron-down'}
          size={18}
          color={colors.gray500}
          style={{ marginLeft: 8 }}
        />
      </Pressable>

      {open && (
        <View style={styles.body}>
          {category === 'Architectural Works' && (
            <View style={styles.specialBox}>
              <Text style={styles.specialLabel}>Setbacks (metro)</Text>
              <View style={styles.sbGrid}>
                {SETBACK_FIELDS.map((k) => (
                  <View key={k} style={styles.sbField}>
                    <Text style={styles.sbText}>{k}</Text>
                    <TextInput
                      style={styles.sbInput}
                      keyboardType="decimal-pad"
                      placeholder="(m)"
                      value={extra.setbacks?.[k] ?? ''}
                      onChangeText={(v) =>
                        onExtra({ setbacks: { ...(extra.setbacks ?? {}), [k]: v } })
                      }
                      editable={!disabled}
                    />
                  </View>
                ))}
              </View>
            </View>
          )}

          {category === 'Civil / Structural Works' && (
            <View style={styles.specialBox}>
              <Text style={styles.specialLabel}>Completed Floor Level</Text>
              <TextInput
                style={styles.input}
                placeholder="e.g. 2nd Floor"
                value={extra.floorLevel ?? ''}
                onChangeText={(v) => onExtra({ floorLevel: v.toUpperCase() })}
                editable={!disabled}
                autoCapitalize="characters"
              />
            </View>
          )}

          {items.map((it) => {
            const isOthers = it.item_text === 'Others';
            return (
              <View key={it.id} style={styles.itemRow}>
                <Text style={styles.itemText}>{it.item_text}</Text>
                {isOthers && (
                  <TextInput
                    style={[styles.input, { marginBottom: 8 }]}
                    placeholder="Specify"
                    value={extra.others ?? ''}
                    onChangeText={(v) => onExtra({ others: v.toUpperCase() })}
                    editable={!disabled}
                    autoCapitalize="characters"
                  />
                )}
                <ResultSegmented
                  value={results[it.id] ?? null}
                  onChange={(v) => onResult(it.id, v)}
                  disabled={disabled}
                />
              </View>
            );
          })}

          <View style={styles.row2}>
            <View style={styles.pctField}>
              <Text style={styles.specialLabel}>Percent (%)</Text>
              <TextInput
                style={styles.input}
                keyboardType="numeric"
                placeholder="%"
                value={pct}
                onChangeText={onPctChange}
                editable={!disabled}
              />
            </View>
            <View style={styles.pctField}>
              <Text style={styles.specialLabel}>Status</Text>
              <View style={styles.statusChip}>
                <View style={[styles.statusDot, { backgroundColor: answered === 0 ? colors.gray400 : passes === answered ? colors.success : colors.warning }]} />
                <Text style={styles.statusText}>
                  {answered === 0 ? 'Not started' : passes === answered ? 'All passed' : 'In progress'}
                </Text>
              </View>
            </View>
          </View>

          <View style={styles.remarkWrap}>
            <View style={styles.remarkHead}>
              <Text style={styles.specialLabel}>Remark/s</Text>
              <Pressable
                style={[styles.aiBtn, (!aiEnabled || disabled) && { opacity: 0.4 }]}
                disabled={!aiEnabled || disabled || aiLoading}
                onPress={onAi}
              >
                <Ionicons name={aiLoading ? 'hourglass-outline' : 'sparkles'} size={14} color={colors.primary} />
                <Text style={styles.aiText}>{aiLoading ? 'Generating...' : 'AI'}</Text>
              </Pressable>
            </View>
            <TextInput
              style={[styles.input, styles.remarkInput]}
              placeholder="Remarks (optional)"
              value={remark}
              onChangeText={(v) => onRemarkChange(v.toUpperCase())}
              multiline
              autoCapitalize="characters"
              editable={!disabled}
            />
          </View>
        </View>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  card: {
    backgroundColor: colors.white,
    borderRadius: 14,
    borderWidth: 1,
    borderColor: colors.gray200,
    marginBottom: 12,
    overflow: 'hidden',
  },
  cardOpen: {
    shadowColor: colors.gray900,
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.05,
    shadowRadius: 8,
    elevation: 2,
  },
  head: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 12,
    paddingHorizontal: 12,
  },
  accent: {
    width: 5,
    alignSelf: 'stretch',
    borderRadius: 3,
    marginRight: 10,
  },
  headText: {
    flex: 1,
  },
  title: {
    fontFamily: fonts.displaySemi,
    fontSize: 14.5,
    color: colors.gray800,
  },
  progress: {
    fontFamily: fonts.body,
    fontSize: 11.5,
    color: colors.gray500,
    marginTop: 2,
  },
  progressRing: {
    width: 44,
    height: 44,
    borderRadius: 22,
    borderWidth: 3,
    alignItems: 'center',
    justifyContent: 'center',
    marginLeft: 8,
  },
  progressRingText: {
    fontFamily: fonts.bodySemi,
    fontSize: 11,
  },
  body: {
    paddingHorizontal: 12,
    paddingBottom: 12,
  },
  specialBox: {
    backgroundColor: colors.gray50,
    borderRadius: 10,
    padding: 10,
    marginBottom: 10,
  },
  specialLabel: {
    fontFamily: fonts.bodySemi,
    fontSize: 12,
    color: colors.gray600,
    marginBottom: 6,
  },
  sbGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  sbField: {
    width: '47%',
  },
  sbText: {
    fontFamily: fonts.bodyMedium,
    fontSize: 11.5,
    color: colors.gray600,
    marginBottom: 4,
  },
  sbInput: {
    borderWidth: 1,
    borderColor: colors.gray300,
    borderRadius: 8,
    paddingHorizontal: 10,
    paddingVertical: 7,
    fontFamily: fonts.body,
    fontSize: 13,
    color: colors.gray800,
    backgroundColor: colors.white,
  },
  input: {
    borderWidth: 1,
    borderColor: colors.gray300,
    borderRadius: 9,
    paddingHorizontal: 11,
    paddingVertical: 9,
    fontFamily: fonts.body,
    fontSize: 13.5,
    color: colors.gray800,
    backgroundColor: colors.white,
    textTransform: 'uppercase',
  },
  itemRow: {
    paddingVertical: 10,
    borderBottomWidth: 1,
    borderBottomColor: colors.gray100,
  },
  itemText: {
    fontFamily: fonts.bodyMedium,
    fontSize: 13,
    color: colors.gray800,
    marginBottom: 8,
    lineHeight: 19,
  },
  row2: {
    flexDirection: 'row',
    gap: 10,
    marginTop: 10,
  },
  pctField: {
    flex: 1,
  },
  statusChip: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: colors.gray50,
    borderRadius: 9,
    paddingHorizontal: 10,
    paddingVertical: 10,
  },
  statusDot: {
    width: 8,
    height: 8,
    borderRadius: 4,
    marginRight: 6,
  },
  statusText: {
    fontFamily: fonts.bodyMedium,
    fontSize: 12,
    color: colors.gray700,
  },
  remarkWrap: {
    marginTop: 12,
  },
  remarkHead: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  remarkInput: {
    minHeight: 64,
    textAlignVertical: 'top',
  },
  aiBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    backgroundColor: colors.primary50,
    borderRadius: 8,
    paddingHorizontal: 10,
    paddingVertical: 6,
    marginBottom: 6,
  },
  aiText: {
    fontFamily: fonts.bodySemi,
    fontSize: 12,
    color: colors.primary,
  },
});
