import React from 'react';
import { useToast } from './toast';

function fallbackCopy(text: string) {
  const ta = document.createElement('textarea');
  ta.value = text;
  ta.setAttribute('readonly', '');
  ta.style.position = 'fixed';
  ta.style.left = '-9999px';
  document.body.appendChild(ta);
  ta.select();
  try {
    document.execCommand('copy');
  } finally {
    document.body.removeChild(ta);
  }
}

function CopyIcon() {
  return (
    <svg
      className="h-5 w-5"
      style={{
        width: 16,
        height: 16,
        maxWidth: 16,
        maxHeight: 16,
        minWidth: 16,
        minHeight: 16,
        transform: 'translateX(0.5px)',
        display: 'block',
        flex: '0 0 auto',
      }}
      viewBox="0 0 24 24"
      aria-hidden="true"
      focusable="false"
    >
      <path fill="currentColor" d="M9 9h10v10H9V9zm-4 6H4V4h11v1H5v10z"></path>
    </svg>
  );
}

export type CopyButtonProps = {
  text: string;
  label: string;
  variant?: 'icon' | 'labeled';
  buttonText?: string;
};

export function CopyButton({ text, label, variant = 'icon', buttonText }: CopyButtonProps) {
  const { success, error } = useToast();
  const [done, setDone] = React.useState(false);

  const onCopy = React.useCallback(async () => {
    try {
      if (navigator.clipboard && navigator.clipboard.writeText) {
        await navigator.clipboard.writeText(text);
      } else {
        fallbackCopy(text);
      }
      setDone(true);
      window.setTimeout(() => setDone(false), 800);
      success(label, 'In die Zwischenablage kopiert.');
    } catch {
      try {
        fallbackCopy(text);
        setDone(true);
        window.setTimeout(() => setDone(false), 800);
        success(label, 'In die Zwischenablage kopiert.');
      } catch {
        error('Kopieren fehlgeschlagen', 'Bitte manuell markieren und kopieren.');
      }
    }
  }, [text, label, success, error]);

  const isLabeled = variant === 'labeled';
  const displayText = buttonText ?? label;

  return (
    <button
      type="button"
      className={[
        'inline-flex items-center justify-center flex-none transition focus-visible:outline-none',
        isLabeled ? 'btn-secondary !w-auto gap-2' : [
          '!w-9 !h-9 !p-0 !m-0 rounded-full border shadow-sm',
          'border-[color:var(--border-subtle)] bg-[color:var(--card-bg)] text-[color:var(--text-main)]',
          'hover:bg-[color:var(--surface-hover)]',
          done ? 'border-emerald-400' : '',
        ].join(' '),
      ].join(' ')}
      style={
        isLabeled
          ? { width: 'auto', maxWidth: 'none', minWidth: 0 }
          : { width: 36, height: 36, flex: '0 0 auto', maxWidth: 36, maxHeight: 36, minWidth: 36, minHeight: 36 }
      }
      aria-label={label}
      title={label}
      onClick={onCopy}
    >
      {isLabeled ? <span>{displayText}</span> : null}
      <CopyIcon />
    </button>
  );
}

