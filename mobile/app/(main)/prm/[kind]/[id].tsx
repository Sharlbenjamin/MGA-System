import React from 'react';
import { Editor } from '../../../../src/components/Editor';
import { prmResources } from '../../../../src/resources';

export default function PrmRecord() {
  return <Editor resources={prmResources} />;
}
