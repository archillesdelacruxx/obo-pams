export const colors = {
  navy900: '#0A1F4D',
  navy700: '#123E8C',
  primary: '#1D5FD6',
  primary400: '#3E7BEA',
  primary100: '#DCE7FB',
  primary50: '#F3F7FE',
  white: '#FFFFFF',
  surface: '#FFFFFF',
  bg: '#F3F7FE',
  gray900: '#12151F',
  gray800: '#1F2534',
  gray700: '#333B4F',
  gray600: '#4B5468',
  gray500: '#6B7280',
  gray400: '#9AA2B1',
  gray300: '#CDD2DC',
  gray200: '#E3E6EC',
  gray100: '#EFF1F5',
  gray50: '#F7F8FA',
  success: '#1C8A4B',
  successLight: '#E4F6EC',
  warning: '#B5720A',
  warningBg: '#FDF0DC',
  danger: '#C22B2B',
  dangerLight: '#FBE7E7',
  overlay: 'rgba(10,31,77,0.45)',
} as const;

export const fonts = {
  display: 'Sora_700Bold',
  displayExtra: 'Sora_800ExtraBold',
  displaySemi: 'Sora_600SemiBold',
  body: 'Inter_400Regular',
  bodyMedium: 'Inter_500Medium',
  bodySemi: 'Inter_600SemiBold',
  bodyBold: 'Inter_700Bold',
} as const;

export const radii = {
  input: 12,
  card: 16,
  chip: 999,
} as const;

export const spacing = {
  xs: 4,
  sm: 8,
  md: 12,
  lg: 16,
  xl: 24,
  xxl: 32,
} as const;

export const shadows = {
  card: {
    shadowColor: '#12151F',
    shadowOffset: { width: 0, height: 3 },
    shadowOpacity: 0.08,
    shadowRadius: 12,
    elevation: 3,
  },
  header: {
    shadowColor: '#12151F',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.12,
    shadowRadius: 4,
    elevation: 4,
  },
} as const;
