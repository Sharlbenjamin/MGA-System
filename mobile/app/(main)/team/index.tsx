import React from 'react';
import { Browse } from '../../../src/components/Browse';
import { teamResources } from '../../../src/resources';

export default function TeamScreen() {
  return <Browse resources={Object.values(teamResources)} base="/team" />;
}
