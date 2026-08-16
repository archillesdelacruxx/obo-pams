import React from 'react';
import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { colors, fonts } from '../theme/tokens';
import StatusPill from './StatusPill';
import EmptyState from './EmptyState';
import Skeleton from './Skeleton';
import type { InspectionReportRow } from '../types';

const COLS = [
  { key: 'inspection_no', label: 'Inspection No.', width: 132 },
  { key: 'application_no', label: 'Application No.', width: 124 },
  { key: 'project_title', label: 'Project Title', width: 210 },
  { key: 'inspection_date', label: 'Inspection Date', width: 122 },
  { key: 'team', label: 'Inspecting Team', width: 205 },
  { key: 'status', label: 'Status', width: 112 },
];

const TABLE_WIDTH = COLS.reduce((sum, c) => sum + c.width, 0);

function formatDate(dateStr: string | null | undefined): string {
  if (!dateStr) return '—';
  const d = new Date(dateStr);
  if (isNaN(d.getTime())) return dateStr;
  return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

interface Props {
  rows: InspectionReportRow[];
  loading: boolean;
  error: boolean;
  onRowPress: (row: InspectionReportRow) => void;
  onRowLongPress?: (row: InspectionReportRow) => void;
}

export default function MonitoringTable({ rows, loading, error, onRowPress, onRowLongPress }: Props) {
  return (
    <View style={styles.card}>
      <ScrollView horizontal showsHorizontalScrollIndicator>
        <View style={{ width: TABLE_WIDTH }}>
          <View style={styles.headRow}>
            {COLS.map((c) => (
              <View key={c.key} style={[styles.headCell, { width: c.width }]}>
                <Text style={styles.headText}>{c.label}</Text>
              </View>
            ))}
          </View>
          {loading ? (
            <View>
              {[0, 1, 2].map((i) => (
                <View key={i} style={styles.row}>
                  <View style={styles.rowCells}>
                    {COLS.map((c) => (
                      <View key={c.key} style={{ width: c.width, paddingHorizontal: 8 }}>
                        <Skeleton width="90%" height={13} radius={6} />
                      </View>
                    ))}
                  </View>
                </View>
              ))}
            </View>
          ) : error ? (
            <EmptyState
              icon="cloud-offline-outline"
              title="Could not load inspections"
              message="Unable to reach the server. Check your Wi-Fi connection and try again."
            />
          ) : rows.length === 0 ? (
            <EmptyState
              icon="clipboard-outline"
              title="No inspection records"
              message="Start a new on-site inspection using the New button above."
            />
          ) : (
            rows.map((r) => (
              <Pressable
                key={r.id}
                style={styles.row}
                onPress={() => onRowPress(r)}
                onLongPress={onRowLongPress ? () => onRowLongPress(r) : undefined}
                delayLongPress={400}
              >
                <View style={styles.rowCells}>
                  <View style={[styles.cell, { width: COLS[0].width }]}>
                    <Text style={styles.cellId} numberOfLines={1}>
                      {r.inspection_no}
                    </Text>
                  </View>
                  <View style={[styles.cell, { width: COLS[1].width }]}>
                    <Text style={styles.cellId} numberOfLines={1}>
                      {r.application_no || '—'}
                    </Text>
                  </View>
                  <View style={[styles.cell, { width: COLS[2].width }]}>
                    <Text style={styles.cellTitle} numberOfLines={1}>
                      {r.project_title}
                    </Text>
                  </View>
                  <View style={[styles.cell, { width: COLS[3].width }]}>
                    <Text style={styles.cellText}>{formatDate(r.inspection_date)}</Text>
                  </View>
                  <View style={[styles.cell, { width: COLS[4].width }]}>
                    <Text style={styles.cellText} numberOfLines={1}>
                      {r.team_leader_1_name
                        ? r.team_leader_2_name
                          ? `${r.team_leader_1_name}, ${r.team_leader_2_name}`
                          : `${r.team_leader_1_name}, —`
                        : '—'}
                    </Text>
                  </View>
                  <View style={[styles.cell, { width: COLS[5].width }]}>
                    <StatusPill status={r.status} />
                  </View>
                </View>
              </Pressable>
            ))
          )}
        </View>
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  card: {
    backgroundColor: colors.white,
    borderRadius: 14,
    borderWidth: 1,
    borderColor: colors.gray200,
    overflow: 'hidden',
  },
  headRow: {
    flexDirection: 'row',
    backgroundColor: colors.navy900,
  },
  headCell: {
    paddingVertical: 11,
    paddingHorizontal: 12,
  },
  headText: {
    fontFamily: fonts.bodySemi,
    fontSize: 12,
    color: colors.white,
  },
  row: {
    borderTopWidth: 1,
    borderTopColor: colors.gray200,
  },
  rowCells: {
    flexDirection: 'row',
    alignItems: 'center',
    minHeight: 52,
  },
  cell: {
    paddingVertical: 9,
    paddingHorizontal: 12,
  },
  cellId: {
    fontFamily: fonts.bodySemi,
    fontSize: 12.5,
    color: colors.gray600,
  },
  cellTitle: {
    fontFamily: fonts.bodySemi,
    fontSize: 13,
    color: colors.gray800,
  },
  cellText: {
    fontFamily: fonts.body,
    fontSize: 12.5,
    color: colors.gray600,
  },
});
