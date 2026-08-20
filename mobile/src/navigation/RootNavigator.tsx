import React, { useEffect, useRef, useState } from 'react';
import { NavigationContainer, DefaultTheme } from '@react-navigation/native';
import { useAuth } from '../context/AuthContext';
import LoginScreen from '../screens/LoginScreen';
import MainTabs, { SplashScreen } from './MainTabs';
import { colors } from '../theme/tokens';

const navTheme = {
  ...DefaultTheme,
  colors: {
    ...DefaultTheme.colors,
    primary: colors.primary,
    background: colors.bg,
    card: colors.surface,
    text: colors.gray800,
    border: colors.gray200,
  },
};

const SPLASH_MIN_MS = 5000;

export default function RootNavigator() {
  const { status } = useAuth();
  const mountedAt = useRef(Date.now());
  const [minSplashElapsed, setMinSplashElapsed] = useState(false);

  useEffect(() => {
    const remaining = SPLASH_MIN_MS - (Date.now() - mountedAt.current);
    const t = setTimeout(() => setMinSplashElapsed(true), Math.max(remaining, 0));
    return () => clearTimeout(t);
  }, []);

  if (status === 'loading' || !minSplashElapsed) return <SplashScreen />;

  return (
    <NavigationContainer theme={navTheme}>
      {status === 'signedIn' ? <MainTabs /> : <LoginScreen />}
    </NavigationContainer>
  );
}
