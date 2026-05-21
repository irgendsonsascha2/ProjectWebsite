import React from 'react';
import { CopyButton } from './CopyButton';

export type CopyFieldProps = {
  kind: 'code' | 'link';
  value: string;
  copyLabel: string;
};

export function CopyField({ kind, value, copyLabel }: CopyFieldProps) {
  return (
    <div className="copy-field flex min-w-0 items-center gap-3">
      {kind === 'code' ? (
        <code className="copy-field__value min-w-0 flex-1 truncate rounded-lg bg-[color:var(--surface-muted)] px-3 py-1.5 text-xs text-[color:var(--text-main)]">
          {value}
        </code>
      ) : (
        <input
          className="copy-field__value min-w-0 flex-1 truncate rounded-lg border border-[color:var(--border-subtle)] bg-[color:var(--card-bg)] px-3 py-1.5 text-xs text-[color:var(--text-main)] focus:outline-none"
          type="text"
          value={value}
          readOnly
          onClick={(e) => (e.currentTarget as HTMLInputElement).select()}
        />
      )}

      <CopyButton text={value} label={copyLabel} />
    </div>
  );
}

