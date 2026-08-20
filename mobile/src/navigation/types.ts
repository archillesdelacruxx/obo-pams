import type { NavigatorScreenParams } from '@react-navigation/native';

export type MainTabParamList = {
  Home: undefined;
  Inspections: NavigatorScreenParams<InspectionsStackParamList> | undefined;
  Profile: undefined;
};

export type InspectionsStackParamList = {
  InspectionsList: undefined;
  InspectionForm: { id?: number };
  InspectionDetail: { id: number };
  SitePhotos: undefined;
};
