import React from 'react';
import { CopyButton } from './CopyButton';

export type CopyFieldProps = {
  kind: 'code' | 'link';
  value: string;
  copyLabel: string;
};

export function CopyField({ kind, value, copyLabel }: CopyFieldProps) {
  return (
    <div className="flex min-w-0 items-center">
      {kind === 'code' ? (
        <code className="min-w-0 flex-1 truncate rounded-lg bg-[color:var(--surface-muted)] px-2 py-1 text-xs text-[color:var(--text-main)]">
          {value}
        </code>
      ) : (
        <input
          className="min-w-0 flex-1 truncate rounded-lg border border-[color:var(--border-subtle)] bg-[color:var(--card-bg)] px-2 py-1 text-xs text-[color:var(--text-main)] focus:outline-none"
          type="text"
          value={value}
          readOnly
          onClick={(e) => (e.currentTarget as HTMLInputElement).select()}
        />
      )}

      <div className="ml-2 flex-none">
        <CopyButton text={value} label={copyLabel} />
      </div>
    </div>
  );
}

