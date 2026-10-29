import { Redirect } from 'expo-router';
import React from 'react';
import { useAuth } from '../src/auth';

export default function Index() {
  const { ready, user } = useAuth();
  if (!ready) return null;
  if (!user) return <Redirect href="/login" />;
  return <Redirect href="/cases" />;
}
