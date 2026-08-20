import React from 'react';
import { Image, View, Text, ActivityIndicator } from 'react-native';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { Ionicons } from '@expo/vector-icons';
import { colors, fonts } from '../theme/tokens';
import DashboardScreen from '../screens/DashboardScreen';
import InspectionsStack from './InspectionsStack';
import ProfileScreen from '../screens/profile/ProfileScreen';
import type { MainTabParamList } from './types';

const Tab = createBottomTabNavigator<MainTabParamList>();

const TAB_ICONS: Record<keyof MainTabParamList, { active: keyof typeof Ionicons.glyphMap; inactive: keyof typeof Ionicons.glyphMap }> = {
  Home: { active: 'home', inactive: 'home-outline' },
  Inspections: { active: 'clipboard', inactive: 'clipboard-outline' },
  Profile: { active: 'person', inactive: 'person-outline' },
};

export default function MainTabs() {

  return (
    <Tab.Navigator
      screenOptions={({ route }) => ({
        headerShown: false,
        tabBarActiveTintColor: colors.primary,
        tabBarInactiveTintColor: colors.gray400,
        tabBarStyle: {
          backgroundColor: colors.white,
          borderTopColor: colors.gray200,
          borderTopWidth: 1,
          height: 62,
          paddingTop: 6,
          paddingBottom: 8,
        },
        tabBarLabelStyle: {
          fontFamily: fonts.bodyMedium,
          fontSize: 11,
        },
        tabBarIcon: ({ focused, color, size }) => {
          const icon = TAB_ICONS[route.name];
          return <Ionicons name={focused ? icon.active : icon.inactive} size={size ?? 22} color={color} />;
        },
      })}
    >
      <Tab.Screen name="Home" component={DashboardScreen} options={{ tabBarLabel: 'Home' }} />
      <Tab.Screen name="Inspections" component={InspectionsStack} />
      <Tab.Screen name="Profile" component={ProfileScreen} />
    </Tab.Navigator>
  );
}

export function SplashScreen() {
  return (
    <View style={{ flex: 1, backgroundColor: colors.navy900, alignItems: 'center', justifyContent: 'center' }}>
      <View
        style={{
          width: 96,
          height: 96,
          borderRadius: 48,
          backgroundColor: 'rgba(255,255,255,0.12)',
          alignItems: 'center',
          justifyContent: 'center',
          marginBottom: 18,
          overflow: 'hidden',
        }}
      >
        <Image
          source={require('../../assets/images/obo-logo.png')}
          style={{ width: 88, height: 88, borderRadius: 44 }}
          resizeMode="cover"
        />
      </View>
      <Text style={{ fontFamily: fonts.display, fontSize: 24, color: colors.white, letterSpacing: 2, marginBottom: 4, textAlign: 'center' }}>
        PAMS
      </Text>
      <Text style={{ fontFamily: fonts.body, fontSize: 12, color: 'rgba(255,255,255,0.6)', textAlign: 'center', paddingHorizontal: 40 }}>
        Permits Application System
      </Text>
      <ActivityIndicator color={colors.white} style={{ marginTop: 28 }} />
    </View>
  );
}
