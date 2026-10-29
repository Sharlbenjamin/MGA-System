import React from 'react';
import { Editor } from '../../../../src/components/Editor';
import { crmResources } from '../../../../src/resources';

export default function CrmRecord() {
  return <Editor resources={crmResources} />;
}
