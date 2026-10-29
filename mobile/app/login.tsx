import { Redirect, router } from 'expo-router';
import React, { useEffect, useState } from 'react';
import { Image, KeyboardAvoidingView, Platform, Pressable, ScrollView, StyleSheet, Text, TextInput, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { DEFAULT_BASE_URL, getBaseUrl, setBaseUrl } from '../src/api';
import { useAuth } from '../src/auth';
import { colors } from '../src/theme';

export default function LoginScreen() {
  const { user, signIn } = useAuth();
  const insets = useSafeAreaInsets();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [base, setBase] = useState(DEFAULT_BASE_URL);
  const [showServer, setShowServer] = useState(false);
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    getBaseUrl().then(setBase).catch(() => undefined);
  }, []);

  if (user) return <Redirect href="/cases" />;

  return (
    <KeyboardAvoidingView style={styles.flex} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
      <ScrollView
        keyboardShouldPersistTaps="handled"
        contentContainerStyle={[styles.screen, { paddingTop: insets.top + 28, paddingBottom: insets.bottom + 24 }]}
      >
        <View style={styles.mark}>
          <Image source={require('../assets/logo.png')} style={styles.logo} resizeMode="contain" />
        </View>
        <Text style={styles.kicker}>Med Guard Assistance</Text>
        <Text style={styles.title}>Sign in</Text>
        <Text style={styles.sub}>Cases, billing, clients, and providers on your phone.</Text>
        <Text style={styles.label}>Email</Text>
        <TextInput
          value={email}
          onChangeText={setEmail}
          autoCapitalize="none"
          autoComplete="email"
          keyboardType="email-address"
          textContentType="username"
          placeholder="name@medguarda.com"
          placeholderTextColor={colors.muted}
          style={styles.input}
        />
        <Text style={styles.label}>Password</Text>
        <TextInput
          value={password}
          onChangeText={setPassword}
          secureTextEntry
          textContentType="password"
          placeholder="Password"
          placeholderTextColor={colors.muted}
          style={styles.input}
        />
        {error ? <Text style={styles.error}>{error}</Text> : null}
        <Pressable
          style={styles.button}
          disabled={busy}
          onPress={async () => {
            setBusy(true);
            setError('');
            try {
              await setBaseUrl(base);
              await signIn(email.trim(), password);
              router.replace('/cases');
            } catch (err) {
              setError(err instanceof Error ? err.message : 'Could not sign in');
            } finally {
              setBusy(false);
            }
          }}
        >
          <Text style={styles.buttonText}>{busy ? 'Signing in…' : 'Sign in'}</Text>
        </Pressable>
        <Pressable onPress={() => setShowServer((current) => !current)} hitSlop={8}>
          <Text style={styles.advanced}>{showServer ? 'Hide server address' : 'Server address'}</Text>
        </Pressable>
        {showServer ? (
          <TextInput
            value={base}
            onChangeText={setBase}
            autoCapitalize="none"
            autoCorrect={false}
            placeholder="https://mgastaging.medguarda.com"
            placeholderTextColor={colors.muted}
            style={styles.input}
          />
        ) : null}
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  flex: { flex: 1, backgroundColor: colors.paper },
  screen: { flexGrow: 1, paddingHorizontal: 24, gap: 10 },
  mark: {
    width: 88,
    height: 88,
    borderRadius: 24,
    backgroundColor: '#000000',
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 8,
  },
  logo: { width: 64, height: 64 },
  kicker: { color: colors.copper, fontWeight: '700', letterSpacing: 0.4 },
  title: { fontSize: 34, fontWeight: '700', color: colors.ink, letterSpacing: -0.8 },
  sub: { color: colors.muted, fontSize: 16, lineHeight: 22, marginBottom: 8 },
  label: { color: colors.ink, fontWeight: '700', marginTop: 4 },
  input: {
    backgroundColor: colors.card,
    borderWidth: 1,
    borderColor: colors.line,
    borderRadius: 14,
    minHeight: 52,
    paddingHorizontal: 16,
    color: colors.ink,
    fontSize: 17,
  },
  button: { backgroundColor: colors.teal, borderRadius: 14, minHeight: 54, alignItems: 'center', justifyContent: 'center', marginTop: 8 },
  buttonText: { color: colors.white, fontWeight: '700', fontSize: 17 },
  error: { color: colors.danger, fontSize: 15 },
  advanced: { color: colors.teal, fontWeight: '700', textAlign: 'center', marginTop: 8 },
});
