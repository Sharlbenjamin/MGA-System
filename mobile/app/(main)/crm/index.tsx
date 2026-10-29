import React from 'react';
import { Browse } from '../../../src/components/Browse';
import { crmResources } from '../../../src/resources';

export default function CrmScreen() {
  return <Browse resources={Object.values(crmResources)} base="/crm" />;
}
