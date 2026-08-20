import React, { useEffect, useState } from 'react';
import { Image, Modal, Pressable, StyleSheet, Text, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { colors, fonts } from '../theme/tokens';
import { resolvePhotoUri } from '../utils/media';
import type { InspectionPhoto } from '../types';

interface Props {
  visible: boolean;
  photos: InspectionPhoto[];
  initialIndex?: number;
  title?: string;
  onClose: () => void;
}

export default function PhotoViewerModal({ visible, photos, initialIndex = 0, title, onClose }: Props) {
  const [index, setIndex] = useState(0);

  useEffect(() => {
    if (visible) {
      const clamped = photos.length ? Math.min(Math.max(initialIndex, 0), photos.length - 1) : 0;
      setIndex(clamped);
    }
  }, [visible, photos, initialIndex]);

  const photo = photos[index];
  const uri = resolvePhotoUri(photo?.file_path);
  const total = photos.length;

  const prev = () => setIndex((i) => (total ? (i - 1 + total) % total : 0));
  const next = () => setIndex((i) => (total ? (i + 1) % total : 0));

  return (
    <Modal visible={visible} transparent animationType="fade" onRequestClose={onClose}>
      <View style={styles.backdrop}>
        {title || total > 0 ? (
          <View style={styles.head}>
            <Text style={styles.headText} numberOfLines={1}>
              {title ?? 'Site Photos'}
              {total > 0 ? ` (${index + 1} / ${total})` : ''}
            </Text>
          </View>
        ) : null}
        <Pressable style={styles.close} onPress={onClose} hitSlop={12}>
          <Ionicons name="close-circle" size={34} color={colors.white} />
        </Pressable>

        {uri ? (
          <Image source={{ uri }} style={styles.image} resizeMode="contain" />
        ) : (
          <View style={styles.imageFallback}>
            <Ionicons name="image-outline" size={40} color={colors.gray400} />
            <Text style={styles.imageFallbackText}>Unable to load photo</Text>
          </View>
        )}

        {total > 1 ? (
          <>
            <Pressable style={[styles.nav, styles.navLeft]} onPress={prev} hitSlop={8}>
              <Ionicons name="chevron-back" size={30} color={colors.white} />
            </Pressable>
            <Pressable style={[styles.nav, styles.navRight]} onPress={next} hitSlop={8}>
              <Ionicons name="chevron-forward" size={30} color={colors.white} />
            </Pressable>
          </>
        ) : null}

        {photo?.caption ? (
          <View style={styles.captionWrap}>
            <Text style={styles.caption}>{photo.caption}</Text>
          </View>
        ) : null}
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  backdrop: {
    flex: 1,
    backgroundColor: '#000000',
    justifyContent: 'center',
    alignItems: 'center',
  },
  head: {
    position: 'absolute',
    top: 44,
    left: 20,
    right: 20,
    alignItems: 'center',
  },
  headText: {
    fontFamily: fonts.bodySemi,
    fontSize: 14,
    color: colors.white,
  },
  close: {
    position: 'absolute',
    top: 44,
    right: 20,
    zIndex: 10,
  },
  image: {
    width: '100%',
    height: '80%',
  },
  imageFallback: {
    alignItems: 'center',
    justifyContent: 'center',
  },
  imageFallbackText: {
    fontFamily: fonts.body,
    fontSize: 13,
    color: colors.gray400,
    marginTop: 8,
  },
  nav: {
    position: 'absolute',
    top: '50%',
    marginTop: -24,
    width: 44,
    height: 44,
    borderRadius: 22,
    backgroundColor: 'rgba(255,255,255,0.14)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  navLeft: {
    left: 12,
  },
  navRight: {
    right: 12,
  },
  captionWrap: {
    position: 'absolute',
    bottom: 32,
    left: 24,
    right: 24,
    alignItems: 'center',
  },
  caption: {
    fontFamily: fonts.body,
    fontSize: 13,
    color: colors.white,
    textAlign: 'center',
  },
});