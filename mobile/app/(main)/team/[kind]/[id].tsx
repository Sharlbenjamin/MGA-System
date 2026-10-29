import React from 'react';
import { Editor } from '../../../../src/components/Editor';
import { teamResources } from '../../../../src/resources';

export default function TeamRecord() {
  return <Editor resources={teamResources} />;
}
