import React from 'react';
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

export default function RootNavigator() {
  const { status } = useAuth();

  if (status === 'loading') return <SplashScreen />;

  return (
    <NavigationContainer theme={navTheme}>
      {status === 'signedIn' ? <MainTabs /> : <LoginScreen />}
    </NavigationContainer>
  );
}
