import React, { useEffect, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Image,
  KeyboardAvoidingView,
  Modal,
  Platform,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { LinearGradient } from 'expo-linear-gradient';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Ionicons } from '@expo/vector-icons';
import { colors, fonts, radii, spacing } from '../theme/tokens';
import FormInput from '../components/FormInput';
import PressableScale from '../components/PressableScale';
import { useAuth } from '../context/AuthContext';
import { getServerHost, setServerHost } from '../config';

function LogoChip({ source, name }: { source: number; name: string }) {
  return (
    <View style={styles.logoChip}>
      <Image source={source} style={styles.logoImage} resizeMode="contain" />
      <Text style={styles.logoName}>{name}</Text>
    </View>
  );
}

export default function LoginScreen() {
  const { signIn } = useAuth();

  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [remember, setRemember] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [fieldError, setFieldError] = useState<{ username?: string; password?: string }>({});
  const [submitting, setSubmitting] = useState(false);
  const [lockUntil, setLockUntil] = useState<number | null>(null);
  const [lockNow, setLockNow] = useState<number | null>(null);
  const [remaining, setRemaining] = useState(0);
  const [serverHost, setServerHostLocal] = useState(getServerHost());
  const [showServer, setShowServer] = useState(false);

  const lockTimer = useRef<ReturnType<typeof setInterval> | null>(null);
  const passRef = useRef<TextInput>(null);

  const clearError = () => setError(null);

  const validate = (): boolean => {
    const fe: { username?: string; password?: string } = {};
    if (!username.trim()) fe.username = 'Enter your username.';
    else if (username.trim().length < 3) fe.username = 'Username is too short.';
    if (!password) fe.password = 'Enter your password.';
    else if (password.length < 5) fe.password = 'Password must be at least 5 characters.';
    setFieldError(fe);
    if (fe.username || fe.password) {
      setError(null);
      return false;
    }
    return true;
  };

  const stopLockTimer = () => {
    if (lockTimer.current) {
      clearInterval(lockTimer.current);
      lockTimer.current = null;
    }
  };

  const openLock = (untilSeconds: number) => {
    const untilMs = untilSeconds * 1000;
    setLockUntil(untilMs);
    setLockNow(Date.now());
    stopLockTimer();
    lockTimer.current = setInterval(() => {
      setLockNow(Date.now());
    }, 1000);
  };

  useEffect(() => {
    if (lockUntil == null) return;
    const ms = lockUntil - Date.now();
    if (ms <= 0) {
      setRemaining(0);
      setLockUntil(null);
      setLockNow(null);
      stopLockTimer();
      return;
    }
    setRemaining(Math.ceil(ms / 1000));
  }, [lockUntil, lockNow]);

  useEffect(() => stopLockTimer, []);

  const handleSubmit = async () => {
    clearError();
    if (!validate()) return;
    if (serverHost.trim()) await setServerHost(serverHost.trim());
    setSubmitting(true);
    try {
      await signIn(username.trim(), password, remember);
    } catch (err) {
      const e = err as Error & { locked?: boolean; locked_until?: number | null };
      if (e.locked && e.locked_until) {
        openLock(e.locked_until);
        setError(null);
      } else {
        setError(e.message || 'Invalid username or password.');
      }
      setSubmitting(false);
    }
  };

  const minutes = Math.floor(remaining / 60);
  const seconds = remaining % 60;

  return (
    <SafeAreaView style={styles.safe}>
      <LinearGradient colors={[colors.navy900, colors.navy700]} style={styles.gradient}>
        <KeyboardAvoidingView
          style={{ flex: 1 }}
          behavior={Platform.OS === 'ios' ? 'padding' : undefined}
        >
          <ScrollView
            contentContainerStyle={styles.scroll}
            keyboardShouldPersistTaps="handled"
            showsVerticalScrollIndicator={false}
          >
            <View style={styles.brandRow}>
              <LogoChip source={require('../../assets/images/obo-logo.png')} name="OBO" />
              <LogoChip source={require('../../assets/images/gensan-logo.png')} name="GENSAN" />
            </View>

            <Text style={styles.office}>Office of the Building Official</Text>

            <View style={styles.brandBlock}>
              <Text style={styles.brand}>PAMS</Text>
              <Text style={styles.brandSub}>Permit Application Management System</Text>
            </View>

            <View style={styles.formCard}>
              <Text style={styles.welcome}>Welcome back</Text>
              <Text style={styles.welcomeSub}>Sign in to continue</Text>

              {error ? (
                <View style={styles.errorBanner}>
                  <Ionicons name="alert-circle-outline" size={16} color={colors.danger} />
                  <Text style={styles.errorText}>{error}</Text>
                </View>
              ) : null}

              <FormInput
                value={username}
                onChangeText={(t) => {
                  setUsername(t);
                  if (fieldError.username) setFieldError((f) => ({ ...f, username: undefined }));
                }}
                placeholder="Username"
                icon="person-outline"
                autoFocus
                returnKeyType="next"
                onSubmitEditing={() => passRef.current?.focus()}
                error={fieldError.username}
                editable={!submitting}
                containerStyle={{ marginBottom: 12 }}
              />
              <FormInput
                ref={passRef}
                value={password}
                onChangeText={(t) => {
                  setPassword(t);
                  if (fieldError.password) setFieldError((f) => ({ ...f, password: undefined }));
                }}
                placeholder="Password"
                icon="lock-closed-outline"
                secure
                returnKeyType="done"
                onSubmitEditing={handleSubmit}
                error={fieldError.password}
                editable={!submitting}
                containerStyle={{ marginBottom: 8 }}
              />

              <PressableScale onPress={() => setRemember((r) => !r)} style={styles.rememberRow}>
                <View style={[styles.checkbox, remember && styles.checkboxOn]}>
                  {remember ? <Ionicons name="checkmark" size={14} color={colors.white} /> : null}
                </View>
                <Text style={styles.rememberText}>Remember me</Text>
              </PressableScale>

              <PressableScale onPress={() => setShowServer((s) => !s)} style={styles.serverToggle}>
                <Ionicons name={showServer ? 'chevron-up' : 'server-outline'} size={16} color="rgba(255,255,255,0.8)" />
                <Text style={styles.serverToggleText}>Server address: {getServerHost()}</Text>
              </PressableScale>

              {showServer ? (
                <TextInput
                  style={styles.serverInput}
                  value={serverHost}
                  onChangeText={setServerHostLocal}
                  placeholder="e.g. 192.168.1.100:8080"
                  placeholderTextColor="rgba(255,255,255,0.4)"
                  autoCapitalize="none"
                  autoCorrect={false}
                  keyboardType="url"
                  editable={!submitting}
                />
              ) : null}

              <PressableScale
                onPress={handleSubmit}
                disabled={submitting}
                style={[styles.signInBtn, submitting && { opacity: 0.8 }]}
              >
                {submitting ? (
                  <ActivityIndicator color={colors.navy900} />
                ) : (
                  <Text style={styles.signInText}>Sign In</Text>
                )}
              </PressableScale>
            </View>

            <Text style={styles.footer}>© 2026 OBO · PAMS</Text>
          </ScrollView>
        </KeyboardAvoidingView>
      </LinearGradient>

      <Modal visible={lockUntil != null} transparent animationType="fade">
        <View style={styles.backdrop}>
          <View style={styles.lockCard}>
            <View style={styles.lockIcon}>
              <Ionicons name="lock-closed" size={30} color={colors.navy900} />
            </View>
            <Text style={styles.lockTitle}>Account temporarily locked</Text>
            <Text style={styles.lockMsg}>
              Too many failed login attempts. Please wait before trying again.
            </Text>
            <Text style={styles.lockCountdown}>
              {String(minutes).padStart(2, '0')}:{String(seconds).padStart(2, '0')}
            </Text>
          </View>
        </View>
      </Modal>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: {
    flex: 1,
    backgroundColor: colors.navy900,
  },
  gradient: {
    flex: 1,
  },
  scroll: {
    flexGrow: 1,
    justifyContent: 'center',
    paddingHorizontal: 24,
    paddingVertical: 32,
  },
  brandRow: {
    flexDirection: 'row',
    justifyContent: 'center',
    gap: 18,
    marginBottom: 10,
  },
  logoChip: {
    width: 64,
    height: 64,
    borderRadius: 32,
    backgroundColor: colors.white,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 2,
    borderColor: 'rgba(255,255,255,0.85)',
    overflow: 'hidden',
  },
  logoImage: {
    width: 56,
    height: 56,
  },
  logoName: {
    display: 'none',
  },
  office: {
    fontFamily: fonts.bodyMedium,
    fontSize: 12,
    color: 'rgba(255,255,255,0.72)',
    textAlign: 'center',
    marginBottom: 22,
  },
  brandBlock: {
    alignItems: 'center',
    marginBottom: 30,
  },
  brand: {
    fontFamily: fonts.displayExtra,
    fontSize: 34,
    color: colors.white,
    letterSpacing: 6,
  },
  brandSub: {
    fontFamily: fonts.body,
    fontSize: 12,
    color: 'rgba(255,255,255,0.55)',
    marginTop: 4,
  },
  formCard: {
    backgroundColor: 'rgba(255,255,255,0.06)',
    borderRadius: radii.card,
    padding: 18,
  },
  welcome: {
    fontFamily: fonts.display,
    fontSize: 22,
    color: colors.white,
    marginBottom: 4,
  },
  welcomeSub: {
    fontFamily: fonts.body,
    fontSize: 13,
    color: 'rgba(255,255,255,0.7)',
    marginBottom: 18,
  },
  errorBanner: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    backgroundColor: 'rgba(194,43,43,0.18)',
    borderRadius: 10,
    padding: 10,
    marginBottom: 14,
    gap: 8,
  },
  errorText: {
    flex: 1,
    fontFamily: fonts.body,
    fontSize: 13,
    color: '#FFD7D7',
    lineHeight: 18,
  },
  rememberRow: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 18,
    marginTop: 4,
  },
  checkbox: {
    width: 22,
    height: 22,
    borderRadius: 6,
    borderWidth: 1.5,
    borderColor: 'rgba(255,255,255,0.6)',
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: 10,
  },
  checkboxOn: {
    backgroundColor: colors.primary400,
    borderColor: colors.primary400,
  },
  rememberText: {
    fontFamily: fonts.body,
    fontSize: 13,
    color: 'rgba(255,255,255,0.85)',
  },
  serverToggle: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    marginBottom: 14,
    marginTop: -6,
  },
  serverToggleText: {
    fontFamily: fonts.body,
    fontSize: 12.5,
    color: 'rgba(255,255,255,0.8)',
    flex: 1,
  },
  serverInput: {
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.35)',
    borderRadius: radii.input,
    paddingHorizontal: 14,
    paddingVertical: 12,
    fontFamily: fonts.body,
    fontSize: 14,
    color: colors.white,
    backgroundColor: 'rgba(255,255,255,0.08)',
    marginBottom: 16,
  },
  signInBtn: {
    backgroundColor: colors.white,
    borderRadius: radii.input,
    height: 54,
    alignItems: 'center',
    justifyContent: 'center',
  },
  signInText: {
    fontFamily: fonts.bodyBold,
    fontSize: 16,
    color: colors.navy900,
  },
  footer: {
    fontFamily: fonts.body,
    fontSize: 11,
    color: 'rgba(255,255,255,0.45)',
    textAlign: 'center',
    marginTop: 26,
  },
  backdrop: {
    flex: 1,
    backgroundColor: 'rgba(4,10,25,0.72)',
    alignItems: 'center',
    justifyContent: 'center',
    padding: 24,
  },
  lockCard: {
    backgroundColor: colors.white,
    borderRadius: radii.card,
    padding: 28,
    width: '100%',
    maxWidth: 340,
    alignItems: 'center',
  },
  lockIcon: {
    width: 64,
    height: 64,
    borderRadius: 32,
    backgroundColor: colors.warningBg,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 16,
  },
  lockTitle: {
    fontFamily: fonts.display,
    fontSize: 18,
    color: colors.gray900,
    marginBottom: 8,
    textAlign: 'center',
  },
  lockMsg: {
    fontFamily: fonts.body,
    fontSize: 13,
    color: colors.gray500,
    textAlign: 'center',
    lineHeight: 20,
    marginBottom: 18,
  },
  lockCountdown: {
    fontFamily: fonts.displaySemi,
    fontSize: 30,
    color: colors.warning,
    letterSpacing: 2,
  },
});
