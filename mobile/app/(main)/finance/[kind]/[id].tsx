import React from 'react';
import { Editor } from '../../../../src/components/Editor';
import { financeResources } from '../../../../src/resources';

export default function FinanceRecord() {
  return <Editor resources={financeResources} />;
}
