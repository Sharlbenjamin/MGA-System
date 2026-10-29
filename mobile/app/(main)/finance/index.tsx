import React from 'react';
import { Browse } from '../../../src/components/Browse';
import { financeResources } from '../../../src/resources';

export default function FinanceScreen() {
  return <Browse resources={Object.values(financeResources)} base="/finance" />;
}
