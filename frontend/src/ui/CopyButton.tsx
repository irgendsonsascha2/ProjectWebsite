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

export type CopyButtonProps = {
  text: string;
  label: string;
};

export function CopyButton({ text, label }: CopyButtonProps) {
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

  return (
    <button
      type="button"
      className={[
        // Tailwind-first (mit harten Overrides gegen globale `button { width:100% }`)
        '!w-9 !h-9 !p-0 !m-0 inline-flex items-center justify-center flex-none',
        'rounded-full border border-[color:var(--border-subtle)] bg-[color:var(--surface-muted)] text-[color:var(--text-main)] shadow-sm',
        'hover:bg-[color:var(--surface-hover)]',
        'transition',
        'focus-visible:outline-none',
        done ? 'border-emerald-400 bg-emerald-50' : '',
      ].join(' ')}
      style={{ width: 36, height: 36, flex: '0 0 auto', maxWidth: 36, maxHeight: 36, minWidth: 36, minHeight: 36 }}
      aria-label={label}
      title={label}
      onClick={onCopy}
    >
      <svg
        className="h-5 w-5"
        // Minimal kleiner + 1px optischer Shift, damit links/rechts gleich wirkt.
        style={{
          width: 16,
          height: 16,
          maxWidth: 16,
          maxHeight: 16,
          minWidth: 16,
          minHeight: 16,
          transform: 'translateX(0.5px)',
          display: 'block',
        }}
        viewBox="0 0 24 24"
        aria-hidden="true"
        focusable="false"
      >
        <path fill="currentColor" d="M9 9h10v10H9V9zm-4 6H4V4h11v1H5v10z"></path>
      </svg>
    </button>
  );
}

