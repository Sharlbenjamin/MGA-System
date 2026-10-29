import React from 'react';
import { Browse } from '../../../src/components/Browse';
import { prmResources } from '../../../src/resources';

export default function PrmScreen() {
  return <Browse resources={Object.values(prmResources)} base="/prm" />;
}
