import React, { useMemo } from 'react';
import { View, StyleSheet } from 'react-native';
import Svg, { Circle, Defs, LinearGradient, Path, Stop } from 'react-native-svg';
import { colors } from '../theme';

const CHART_HEIGHT = 120;
const CHART_WIDTH = 320;

function normalizeSeries(values: number[], maxPoints = 14): number[] {
  const slice = values.slice(-maxPoints);
  if (slice.length === 0) return [0];
  return slice;
}

function seriesToPath(values: number[], width: number, height: number): string {
  const series = normalizeSeries(values);
  const max = Math.max(...series, 1);
  const step = series.length > 1 ? width / (series.length - 1) : width;
  const points = series.map((value, index) => {
    const x = index * step;
    const y = height - (value / max) * (height - 8) - 4;
    return { x, y };
  });
  if (points.length === 0) return '';
  const [first, ...rest] = points;
  return `M ${first.x} ${first.y}${rest.map((p) => ` L ${p.x} ${p.y}`).join('')}`;
}

function areaPath(values: number[], width: number, height: number): string {
  const series = normalizeSeries(values);
  const max = Math.max(...series, 1);
  const step = series.length > 1 ? width / (series.length - 1) : width;
  let path = `M 0 ${height}`;
  series.forEach((value, index) => {
    const x = index * step;
    const y = height - (value / max) * (height - 8) - 4;
    path += ` L ${x} ${y}`;
  });
  path += ` L ${(series.length - 1) * step} ${height} Z`;
  return path;
}

export function DualLineChart({ primary, secondary }: { primary: number[]; secondary: number[] }) {
  const primaryPath = useMemo(() => seriesToPath(primary, CHART_WIDTH, CHART_HEIGHT), [primary]);
  const secondaryPath = useMemo(() => seriesToPath(secondary, CHART_WIDTH, CHART_HEIGHT), [secondary]);

  return (
    <View style={styles.wrap}>
      <Svg width="100%" height={CHART_HEIGHT} viewBox={`0 0 ${CHART_WIDTH} ${CHART_HEIGHT}`}>
        <Path d={primaryPath} stroke={colors.primary} strokeWidth={2.5} fill="none" />
        <Path d={secondaryPath} stroke={colors.accent} strokeWidth={2} fill="none" strokeDasharray="4 4" />
      </Svg>
    </View>
  );
}

export function AreaWithLineChart({ area, line }: { area: number[]; line: number[] }) {
  const areaD = useMemo(() => areaPath(area, CHART_WIDTH, CHART_HEIGHT), [area]);
  const linePath = useMemo(() => seriesToPath(line, CHART_WIDTH, CHART_HEIGHT), [line]);
  const lineSeries = useMemo(() => normalizeSeries(line), [line]);

  return (
    <View style={styles.wrap}>
      <Svg width="100%" height={CHART_HEIGHT} viewBox={`0 0 ${CHART_WIDTH} ${CHART_HEIGHT}`}>
        <Defs>
          <LinearGradient id="areaFill" x1="0" y1="0" x2="0" y2="1">
            <Stop offset="0" stopColor="#C7CAFF" stopOpacity="0.85" />
            <Stop offset="1" stopColor="#C7CAFF" stopOpacity="0.05" />
          </LinearGradient>
        </Defs>
        <Path d={areaD} fill="url(#areaFill)" />
        <Path d={linePath} stroke={colors.primary} strokeWidth={2.5} fill="none" />
        {lineSeries.map((value, index, arr) => {
          const max = Math.max(...arr, 1);
          const step = arr.length > 1 ? CHART_WIDTH / (arr.length - 1) : CHART_WIDTH;
          const x = index * step;
          const y = CHART_HEIGHT - (value / max) * (CHART_HEIGHT - 8) - 4;
          return <Circle key={index} cx={x} cy={y} r={3} fill={colors.primary} />;
        })}
      </Svg>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { width: '100%', overflow: 'hidden' },
});
