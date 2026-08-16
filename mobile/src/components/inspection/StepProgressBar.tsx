import React from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { colors, fonts } from '../../theme/tokens';

const STEPS = ['Project', 'Checklist', 'Photos', 'Review'];

interface Props {
  current: number;
}

export default function StepProgressBar({ current }: Props) {
  return (
    <View style={styles.wrap}>
      <View style={styles.trackRow}>
        {STEPS.map((label, i) => {
          const done = i < current;
          const active = i === current;
          return (
            <React.Fragment key={label}>
              <View
                style={[
                  styles.circle,
                  done && styles.circleDone,
                  active && styles.circleActive,
                  !done && !active && styles.circleTodo,
                ]}
              >
                {done ? (
                  <Ionicons name="checkmark" size={13} color={colors.white} />
                ) : (
                  <Text style={[styles.circleNum, active && { color: colors.white }]}>{i + 1}</Text>
                )}
              </View>
              {i < STEPS.length - 1 && <View style={[styles.connector, done && styles.connectorDone]} />}
            </React.Fragment>
          );
        })}
      </View>
      <View style={styles.labelRow}>
        {STEPS.map((label, i) => (
          <Text key={label} style={[styles.label, i === current && styles.labelActive, i < current && styles.labelDone]}>
            {label}
          </Text>
        ))}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    paddingHorizontal: 20,
    paddingTop: 14,
    paddingBottom: 10,
    backgroundColor: colors.navy900,
  },
  trackRow: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  circle: {
    width: 26,
    height: 26,
    borderRadius: 13,
    alignItems: 'center',
    justifyContent: 'center',
  },
  circleDone: {
    backgroundColor: colors.success,
  },
  circleActive: {
    backgroundColor: colors.primary400,
    borderWidth: 2,
    borderColor: colors.white,
  },
  circleTodo: {
    backgroundColor: 'rgba(255,255,255,0.16)',
  },
  circleNum: {
    fontFamily: fonts.bodySemi,
    fontSize: 12,
    color: colors.white,
  },
  connector: {
    flex: 1,
    height: 3,
    borderRadius: 2,
    backgroundColor: 'rgba(255,255,255,0.16)',
    marginHorizontal: 6,
  },
  connectorDone: {
    backgroundColor: colors.success,
  },
  labelRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginTop: 6,
  },
  label: {
    fontFamily: fonts.bodyMedium,
    fontSize: 10.5,
    color: 'rgba(255,255,255,0.45)',
  },
  labelDone: {
    color: 'rgba(255,255,255,0.75)',
  },
  labelActive: {
    color: colors.white,
    fontFamily: fonts.bodySemi,
  },
});
