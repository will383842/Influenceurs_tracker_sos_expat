import React from 'react';
import { STATUS_MAP, PIPELINE_STATUSES } from '../lib/constants';

interface Props {
  status: string | undefined;
  size?: 'sm' | 'md';
}

export default function StatusBadge({ status, size = 'sm' }: Props) {
  if (!status) return null;
  const config = STATUS_MAP[status as keyof typeof STATUS_MAP] ?? { label: status, bg: 'bg-gray-500/20', text: 'text-gray-400' };
  const sizeClass = size === 'sm' ? 'text-[10px] px-1.5 py-0.5' : 'text-xs px-2 py-1';

  return (
    <span className={`${config.bg} ${config.text} ${sizeClass} rounded-full font-medium whitespace-nowrap inline-flex items-center`}>
      {config.label}
    </span>
  );
}
