import React from 'react';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { colors, fonts } from '../theme/tokens';
import InspectionsScreen from '../screens/inspections/InspectionsScreen';
import InspectionFormScreen from '../screens/inspections/InspectionFormScreen';
import InspectionDetailScreen from '../screens/inspections/InspectionDetailScreen';
import PhotosScreen from '../screens/PhotosScreen';
import type { InspectionsStackParamList } from './types';

const Stack = createNativeStackNavigator<InspectionsStackParamList>();

export default function InspectionsStack() {
  return (
    <Stack.Navigator
      screenOptions={{
        headerStyle: { backgroundColor: colors.navy900 },
        headerTintColor: colors.white,
        headerTitleStyle: { fontFamily: fonts.displaySemi, fontSize: 17 },
        contentStyle: { backgroundColor: colors.bg },
      }}
    >
      <Stack.Screen name="InspectionsList" component={InspectionsScreen} options={{ headerShown: false }} />
      <Stack.Screen name="InspectionForm" component={InspectionFormScreen} />
      <Stack.Screen name="InspectionDetail" component={InspectionDetailScreen} options={{ title: 'Inspection Details' }} />
      <Stack.Screen name="SitePhotos" component={PhotosScreen} options={{ title: 'Site Photos' }} />
    </Stack.Navigator>
  );
}
